<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Pipeline;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ImranDevBd\AiOcr\Contracts;
use ImranDevBd\AiOcr\Contracts\AiExtractor;
use ImranDevBd\AiOcr\Contracts\NativeExtractor;
use ImranDevBd\AiOcr\Contracts\OcrEngine;
use ImranDevBd\AiOcr\Contracts\Rasterizer;
use ImranDevBd\AiOcr\Drivers;
use ImranDevBd\AiOcr\Drivers\Native\Extractor;
use ImranDevBd\AiOcr\Drivers\Ocr\Preprocessor;
use ImranDevBd\AiOcr\Drivers\Ocr\Tesseract;
use ImranDevBd\AiOcr\DTO\ExtractionResult;
use ImranDevBd\AiOcr\DTO\FieldValue;
use ImranDevBd\AiOcr\DTO\Page;
use ImranDevBd\AiOcr\Enums\Strategy;
use ImranDevBd\AiOcr\Events\AiFallbackTriggered;
use ImranDevBd\AiOcr\Events\BudgetBlocked;
use ImranDevBd\AiOcr\Events\ExtractionCompleted;
use ImranDevBd\AiOcr\Events\ExtractionFailed;
use ImranDevBd\AiOcr\Events\ExtractionStarted;
use ImranDevBd\AiOcr\Events\PageExtracted;
use ImranDevBd\AiOcr\Events\QualityGateFailed;
use ImranDevBd\AiOcr\Exceptions\AiOcrException;
use ImranDevBd\AiOcr\Exceptions\AiUnavailableException;
use ImranDevBd\AiOcr\Exceptions\BudgetExceededException;
use ImranDevBd\AiOcr\Exceptions\ExtractionFailedException;
use ImranDevBd\AiOcr\Exceptions\FileTooLargeException;
use ImranDevBd\AiOcr\Exceptions\UnsupportedFileException;
use ImranDevBd\AiOcr\Input\Resolver;
use ImranDevBd\AiOcr\Jobs\SendWebhookJob;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Normalizers\Normalizer;
use ImranDevBd\AiOcr\Quality;
use ImranDevBd\AiOcr\Quality\QualityScorer;
use ImranDevBd\AiOcr\Schema\ManualMapper;
use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Support;
use ImranDevBd\AiOcr\Support\Persistence;
use ImranDevBd\AiOcr\Support\Temp;
use ImranDevBd\AiOcr\Types\DocumentType;
use ImranDevBd\AiOcr\Types\TypeRegistry;
use Symfony\Component\Process\Exception\ExceptionInterface;

/** @internal Orchestrates security, native/OCR recovery, validation and persistence. */
final class Pipeline
{
    public function __construct(private readonly Resolver $resolver, private readonly NativeExtractor $native, private readonly OcrEngine $ocr, private readonly Rasterizer $rasterizer, private readonly Preprocessor $preprocessor, private readonly QualityScorer $quality, private readonly AiExtractor $ai, private readonly TypeRegistry $types, private readonly Repository $cache, private readonly Factory $validation, private readonly Dispatcher $events) {}

    /** @param array<string, mixed> $options */
    public function extract(mixed $input, array $options = []): ExtractionResult
    {
        $temp = new Temp;
        $started = microtime(true);
        $warnings = $options['initial_warnings'] ?? [];
        $trace = [];
        $usage = ['usd' => 0.0, 'tokens' => 0, 'latency_ms' => 0.0];
        $aiUsed = false;
        $traceStep = static function (string $step, array $detail = []) use (&$trace, $started): void {
            $trace[] = ['step' => $step, 'elapsed_ms' => round((microtime(true) - $started) * 1000, 2), ...$detail];
        };
        try {
            $document = $this->resolver->resolve($input, $temp, $options['disk'] ?? null);
            if (isset($options['pages'])) {
                $document->select(Support\Pages::parse($options['pages']));
            }
            if (! ($options['internal_page_task'] ?? false)) {
                $this->events->dispatch(new ExtractionStarted($document->hash));
            }
            $traceStep('resolved', ['hash' => $document->hash, 'mime' => $document->mime, 'size' => $document->size]);
            $ttl = config('ai-ocr.store.store_text', true) ? (int) ($options['cache'] ?? config('ai-ocr.ai.cache_ttl', 86400)) : 0;
            $cacheOptions = $options;
            if (($cacheOptions['schema'] ?? null) instanceof Schema && $cacheOptions['schema']->cacheDefinition() !== null) {
                $cacheOptions['schema'] = $cacheOptions['schema']->cacheDefinition();
            }
            unset($cacheOptions['tap'], $cacheOptions['safe'], $cacheOptions['page_callback'], $cacheOptions['prepared_pages'], $cacheOptions['initial_usage'], $cacheOptions['tracking_id'], $cacheOptions['initial_warnings'], $cacheOptions['initial_ai_used'], $cacheOptions['callbacks'], $cacheOptions['parallel']);
            $cacheable = $this->cacheable($cacheOptions);
            $scope = ($options['owner_type'] ?? (auth()->guard()->user() ? auth()->guard()->user()::class : '')).':'.(string) ($options['owner_id'] ?? auth()->guard()->id() ?? 'guest');
            $cacheKey = 'ai-ocr:'.hash('sha256', $document->hash.serialize([$cacheable ? $cacheOptions : [], config('ai-ocr'), config('ai-hub.default'), config('ai-hub.defaults'), app(Contracts\ProviderReadiness::class)->ready(), $scope]));
            if ($ttl > 0 && $cacheable && is_string($cached = $this->cache->get($cacheKey))) {
                try {
                    $decoded = decrypt($cached);
                    if (is_array($decoded)) {
                        return ExtractionResult::fromArray($decoded)->cachedCopy();
                    }
                } catch (DecryptException) {
                    $this->cache->forget($cacheKey);
                }
            }
            if ($ttl > 0 && $cacheable && config('ai-ocr.store.enabled')) {
                $stored = Extraction::query()->where('fingerprint', $cacheKey)->where('created_at', '>=', now()->subSeconds($ttl))->first();
                if ($stored) {
                    return $stored->extractionResult()->cachedCopy();
                }
            }
            $strategy = $options['strategy'] ?? config('ai-ocr.strategy', 'auto');
            Strategy::from($strategy);
            /** @var list<string> $languages */ $languages = $options['languages'] ?? config('ai-ocr.ocr.languages', ['eng']);
            $script = in_array('ben', $languages, true) && ! in_array('eng', $languages, true) ? 'bengali' : null;
            $threshold = (float) ($options['min_confidence'] ?? config('ai-ocr.quality.min_score', 0.75));
            $pages = $options['prepared_pages'] ?? ($strategy === 'ai_only' && ($document->mime === 'application/pdf' || str_starts_with($document->mime, 'image/')) ? array_map(fn ($n) => new Page($n, '', confidence: 0), range(1, app(Extractor::class)->pageCount($document))) : $this->native->extract($document));
            if (count($pages) > config('ai-ocr.limits.max_pages', 200)) {
                throw new FileTooLargeException('Too many pages.');
            }
            if (isset($options['pages'])) {
                $selected = [];
                foreach (explode(',', $options['pages']) as $range) {
                    $parts = array_map('intval', explode('-', $range));
                    $end = $parts[1] ?? $parts[0];
                    if ($end < $parts[0] || $end > config('ai-ocr.limits.max_pages', 200) || $parts[0] < 1) {
                        throw new \InvalidArgumentException('Page range is outside configured limits.');
                    } foreach (range($parts[0], $end) as $number) {
                        $selected[$number] = true;
                    }
                } $pages = array_values(array_filter($pages, fn ($p) => isset($selected[$p->number])));
                if ($pages === []) {
                    throw new \InvalidArgumentException('No requested pages exist.');
                }
            }
            $traceStep('native', ['pages' => count($pages)]);
            $type = $options['schema'] ?? null;
            if ($type === null && (($options['auto_detect'] ?? false) || ($options['privacy_detect'] ?? false))) {
                $type = $this->types->detect(implode("\n", array_map(fn ($p) => $p->text, $pages)));
            }
            if (is_string($type)) {
                $type = is_subclass_of($type, DocumentType::class) ? new $type : $this->types->get($type);
            }
            $documentType = $type instanceof DocumentType ? $type : null;
            $schema = $type instanceof Schema ? $type : ($documentType?->schema() ?? (is_array($type) ? Schema::fromJson($type) : null));
            $typeName = $documentType?->name() ?? ($options['privacy_type'] ?? $schema?->name);
            $options['type'] = $options['privacy_type'] ?? $typeName;
            $options['hints'] = $documentType?->promptHints() ?? '';
            if ($options['internal_page_task'] ?? false) {
                $schema = null;
            }
            $options['cache'] = $ttl;
            $options['job'] ??= config('ai-ocr.ai.job_tag', 'ai-ocr');
            $usage = $options['initial_usage'] ?? $usage;
            $aiUsed = ($options['initial_ai_used'] ?? false) || ($usage['tokens'] ?? 0) > 0;
            $cap = min((float) ($options['max_cost'] ?? INF), (float) config('ai-ocr.ai.max_cost_per_document', 0.25));
            $aiCalls = 0;
            $pageScores = [];
            $pooled = [];
            $concurrency = max(1, min(16, (int) config('ai-ocr.ocr.concurrency', 1)));
            foreach ($pages as $index => $page) {
                if ($concurrency > 1 && $index % $concurrency === 0 && $this->ocr instanceof Tesseract && $strategy !== 'ai_only' && ! isset($options['prepared_pages']) && config('ai-ocr.ocr.enabled', true)) {
                    $paths = [];
                    foreach (array_slice($pages, $index, $concurrency) as $candidatePage) {
                        if ($this->quality->score($candidatePage, $script)['score'] >= $threshold) {
                            continue;
                        }
                        try {
                            $path = $this->rasterizer->page($document, $candidatePage->number, $options['dpi'] ?? config('ai-ocr.ocr.dpi', 300), $temp);
                            $paths[$candidatePage->number] = $this->preprocessor->process($path, $options['preprocess'] ?? config('ai-ocr.ocr.preprocess', []), $temp);
                        } catch (ExceptionInterface|AiOcrException $error) {
                            $pooled[$candidatePage->number] = $error->getMessage();
                        }
                    }
                    $pooled += $this->ocr->recognizeMany($paths, $languages, $concurrency);
                    foreach ($paths as $path) {
                        if ($path !== $document->path) {
                            @unlink($path);
                        }
                    }
                }

                $score = $this->quality->score($page, $script);
                $imagePath = null;
                if ($score['score'] < $threshold && ! isset($options['prepared_pages']) && $strategy !== 'ai_only' && config('ai-ocr.ocr.enabled', true)) {
                    try {
                        if (array_key_exists($page->number, $pooled)) {
                            if (is_string($pooled[$page->number])) {
                                throw new ExtractionFailedException($pooled[$page->number]);
                            }
                            $local = $pooled[$page->number];
                        } else {
                            $imagePath = $this->rasterizer->page($document, $page->number, $options['dpi'] ?? config('ai-ocr.ocr.dpi', 300), $temp);
                            $imagePath = $this->preprocessor->process($imagePath, $options['preprocess'] ?? config('ai-ocr.ocr.preprocess', []), $temp);
                            $local = $this->ocr->recognize($imagePath, $page->number, $languages);
                        }
                        $localScore = $this->quality->score($local, $script);
                        if ($localScore['score'] > $score['score']) {
                            $page = $local;
                            $score = $localScore;
                        } $traceStep('local_ocr', ['page' => $page->number, 'score' => $localScore]);
                    } catch (ExceptionInterface|AiOcrException $e) {
                        $warnings[] = 'Local OCR unavailable for page '.$page->number.': '.$e->getMessage();
                    }
                }
                $failed = $score['score'] < $threshold;
                if ($failed) {
                    $this->events->dispatch(new QualityGateFailed($document->hash, ['page' => $page->number, 'reasons' => $score['reasons']]));
                }
                $shouldAi = ! isset($options['prepared_pages']) && $strategy !== 'manual_only' && ($failed || in_array($strategy, ['ai_only', 'ai_verify', 'consensus'], true) || config('ai-ocr.ai.mode') === 'always');
                if ($shouldAi) {
                    try {
                        if (++$aiCalls > config('ai-ocr.ai.max_pages_per_document', 30)) {
                            throw new AiUnavailableException('AI page limit reached.');
                        }
                        $pageMime = $document->source($page->number)['document']->mime;
                        $vision = $failed && ($pageMime === 'application/pdf' || str_starts_with($pageMime, 'image/'));
                        if ($vision && $imagePath === null) {
                            $imagePath = $this->rasterizer->page($document, $page->number, $options['dpi'] ?? config('ai-ocr.ocr.dpi', 300), $temp);
                        }
                        $image = $vision ? $this->packImage((string) $imagePath, $temp) : null;
                        $this->events->dispatch(new AiFallbackTriggered($document->hash, ['page' => $page->number]));
                        $responseSchema = ['type' => 'object', 'properties' => ['text' => ['type' => 'string'], 'confidence' => ['type' => 'number']], 'required' => ['text', 'confidence'], 'additionalProperties' => false];
                        $response = $this->ai->generate($page->text, $responseSchema, [...$options, 'remaining_cost' => $cap - $usage['usd']], $image);
                        foreach ($usage as $k => $v) {
                            $usage[$k] += $response['usage'][$k];
                        }
                        $aiUsed = true;
                        $candidate = new Page($page->number, $response['data']['text'], 'ai', max(0, min(1, (float) $response['data']['confidence'])));
                        $traceStep('ai_page', ['page' => $page->number, 'provider' => $response['provider'], 'model' => $response['model'], 'different' => trim($page->text) !== trim($candidate->text)]);
                        if ($strategy !== 'ai_verify' && ($strategy !== 'consensus' || $candidate->confidence > $score['score'])) {
                            $page = $candidate;
                            $score = $this->quality->score($page, $script);
                        }
                    } catch (AiOcrException $e) {
                        foreach ($e->usage as $k => $v) {
                            $usage[$k] = ($usage[$k] ?? 0) + $v;
                        }
                        if ($options['require_ai'] ?? false) {
                            throw $e;
                        } $warnings[] = $e->getMessage();
                        if ($e instanceof BudgetExceededException) {
                            $this->events->dispatch(new BudgetBlocked($document->hash));
                        }
                    }
                }
                if ($score['score'] < $threshold) {
                    $warnings[] = 'Page '.$page->number.' remains below quality threshold: '.implode(', ', $score['reasons']);
                }
                $pages[$index] = new Page($page->number, Normalizer::text($page->text), $page->engine, min($page->confidence, $score['score']), $page->words, $page->blocks, $score['garbled']);
                if (isset($options['page_callback'])) {
                    ($options['page_callback'])($pages[$index]);
                }
                $this->events->dispatch(new PageExtracted($document->hash, ['page' => $page->number, 'engine' => $page->engine]));
                $pageScores[$page->number] = $score['score'];
                $traceStep('quality_gate', ['page' => $page->number, ...$score]);
            }
            $text = implode("\n\n", array_map(fn ($p) => $p->text, $pages));
            if ($schema === null && ($options['auto_detect'] ?? false) && $strategy !== 'manual_only' && trim($text) !== '') {
                try {
                    $classification = $this->ai->generate(mb_substr($text, 0, 8000), ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'enum' => [...$this->types->names(), 'unknown']]], 'required' => ['type'], 'additionalProperties' => false], [...$options, 'hints' => 'Classify the document using the registered type names. Choose unknown when uncertain.', 'remaining_cost' => $cap - $usage['usd']]);
                    foreach ($usage as $k => $v) {
                        $usage[$k] += $classification['usage'][$k];
                    } $aiUsed = true;
                    if ($classification['data']['type'] !== 'unknown') {
                        $documentType = $this->types->get($classification['data']['type']);
                        $schema = $documentType->schema();
                        $typeName = $documentType->name();
                        $options['type'] = $typeName;
                        $options['hints'] = $documentType->promptHints();
                    }
                    $traceStep('classification', ['type' => $typeName, 'model' => $classification['model']]);
                } catch (AiOcrException $error) {
                    foreach ($error->usage as $k => $v) {
                        $usage[$k] = ($usage[$k] ?? 0) + $v;
                    } if ($options['require_ai'] ?? false) {
                        throw $error;
                    } $warnings[] = $error->getMessage();
                }
            }
            $data = [];
            $fields = [];
            $errors = [];
            if ($schema !== null) {
                $mapper = new ManualMapper;
                $data = $mapper->extract($text, $schema->toJsonSchema());
                $nativeEvidence = $mapper->evidence();
                $data = $schema->normalize($documentType?->normalize($data) ?? $data);
                $rules = array_merge($schema->rules(), $documentType?->rules() ?? [], $options['rules'] ?? []);
                $errors = array_unique([...$schema->errors($data), ...array_keys($this->validation->make($data, $rules)->errors()->toArray()), ...($documentType?->crossChecks($data) ?? [])]);
                $traceStep('schema_validation', ['failed_fields' => array_values($errors)]);
                if (($errors !== [] || in_array($strategy, ['consensus', 'ai_verify'], true)) && $strategy !== 'manual_only') {
                    try {
                        $properties = $schema->toJsonSchema()['properties'] ?? [];
                        $requested = [];
                        foreach (in_array($strategy, ['consensus', 'ai_verify'], true) ? array_keys($properties) : $errors as $key) {
                            $root = explode('.', (string) $key)[0];
                            if (isset($properties[$root])) {
                                $requested[$root] = $properties[$root];
                            }
                        }
                        if ($requested !== []) {
                            $envelope = ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'properties' => $requested, 'required' => array_values(array_intersect($schema->toJsonSchema()['required'] ?? [], array_keys($requested))), 'additionalProperties' => false], 'evidence' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['field' => ['type' => 'string'], 'confidence' => ['type' => 'number'], 'page' => ['type' => 'integer'], 'source_quote' => ['type' => 'string']], 'required' => ['field', 'confidence', 'page', 'source_quote'], 'additionalProperties' => false]]], 'required' => ['data', 'evidence'], 'additionalProperties' => false];
                            $votes = [];
                            foreach (Arr::dot($data) as $path => $value) {
                                $votes[$path][] = new FieldValue($value, Quality\Validity::failed((string) $path, $errors) ? 0.0 : 0.8, engine: 'native', validated: ! Quality\Validity::failed((string) $path, $errors));
                            }
                            $models = $strategy === 'consensus' ? [null, ...(array) config('ai-ocr.ai.consensus_models', [])] : ($strategy === 'auto' && config('ai-ocr.ai.models.strong') ? [null, config('ai-ocr.ai.models.strong')] : [null]);
                            foreach ($models as $consensusModel) {
                                $response = $this->ai->generate($text, $envelope, [...$options, ...($consensusModel !== null ? ['model' => $consensusModel] : []), 'remaining_cost' => $cap - $usage['usd']]);
                                foreach ($usage as $k => $v) {
                                    $usage[$k] += $response['usage'][$k];
                                }
                                $aiUsed = true;
                                $accepted = [];
                                foreach ($response['data']['evidence'] as $evidence) {
                                    $quote = $evidence['source_quote'];
                                    $source = null;
                                    foreach ($pages as $p) {
                                        if ($p->number === $evidence['page']) {
                                            $source = $p;
                                        }
                                    }
                                    $root = explode('.', $evidence['field'])[0];
                                    if (isset($requested[$root]) && $quote !== '' && $source && str_contains($source->text, $quote) && is_scalar(data_get($response['data']['data'], $evidence['field']))) {
                                        $accepted[$evidence['field']] = data_get($response['data']['data'], $evidence['field']);
                                        $candidateField = new FieldValue(data_get($response['data']['data'], $evidence['field']), max(0, min(1, $evidence['confidence'])), $evidence['page'], $quote, 'ai', true);
                                        $votes[$evidence['field']][] = $candidateField;
                                    } else {
                                        $warnings[] = 'Rejected ungrounded AI evidence for '.$evidence['field'];
                                    }
                                }
                                $traceStep('ai_fields', ['fields' => array_keys($accepted), 'model' => $response['model']]);
                                if ($strategy === 'auto') {
                                    $provisional = $data;
                                    $uncertain = false;
                                    foreach ($votes as $path => $candidates) {
                                        $winner = (new Quality\FieldConsensus)->choose($candidates);
                                        data_set($provisional, $path, $winner->value);
                                        if ($winner->engine === 'ai' && $winner->confidence < $threshold) {
                                            $uncertain = true;
                                        }
                                    }
                                    if (! $uncertain && $schema->errors($provisional) === [] && ! $this->validation->make($provisional, $rules)->fails() && ($documentType?->crossChecks($provisional) ?? []) === []) {
                                        break;
                                    }
                                }

                            }
                            foreach ($votes as $fieldPath => $candidates) {
                                $chosen = (new Quality\FieldConsensus)->choose($candidates);
                                $different = count(array_unique(array_map(fn ($f) => json_encode($f->value, JSON_THROW_ON_ERROR), $candidates))) > 1;
                                if ($different) {
                                    $traceStep('field_difference', ['field' => $fieldPath, 'candidates' => array_map(fn ($f) => $f->jsonSerialize(), $candidates), 'selected_engine' => $strategy === 'ai_verify' ? 'native' : $chosen->engine]);
                                }
                                if ($strategy !== 'ai_verify' && $chosen->engine === 'ai') {
                                    data_set($data, $fieldPath, $chosen->value);
                                    $fields[$fieldPath] = $chosen;
                                }
                            }
                        }
                    } catch (AiOcrException $e) {
                        foreach ($e->usage as $k => $v) {
                            $usage[$k] = ($usage[$k] ?? 0) + $v;
                        }
                        if ($options['require_ai'] ?? false) {
                            throw $e;
                        } $warnings[] = $e->getMessage();
                    }
                }
                $errors = array_values(array_unique([...$schema->errors($data), ...array_keys($this->validation->make($data, $rules)->errors()->toArray()), ...($documentType?->crossChecks($data) ?? [])]));
                if ($errors !== []) {
                    foreach ($errors as $missing) {
                        $fields[$missing] ??= new FieldValue(data_get($data, $missing), 0.0, validated: false);
                    }
                    $warnings[] = 'Fields requiring review: '.implode(', ', $errors);
                }
                foreach ($fields as $key => $field) {
                    if (Quality\Validity::failed((string) $key, $errors)) {
                        $fields[$key] = new FieldValue($field->value, 0.0, $field->page, $field->source_quote, $field->engine, false);
                    }
                }
                foreach (Arr::dot($data) as $key => $value) {
                    $quote = $nativeEvidence[$key] ?? ($typeName === 'passport' ? ($nativeEvidence['mrz'] ?? null) : null);
                    $sourceNumber = null;
                    $sourceEngine = 'native';
                    if ($quote !== null) {
                        foreach ($pages as $sourcePage) {
                            if (str_contains($sourcePage->text, $quote)) {
                                $sourceNumber = $sourcePage->number;
                                $sourceEngine = $sourcePage->engine;
                                break;
                            }
                        }
                    }
                    $fields[$key] ??= new FieldValue($value, Quality\Validity::failed((string) $key, $errors) ? 0.0 : 0.8, $sourceNumber, $quote, $sourceEngine, ! Quality\Validity::failed((string) $key, $errors));
                }
            }
            if (($options['require_ai'] ?? false) && ! $aiUsed) {
                throw new AiUnavailableException('AI was required but no AI extraction ran.');
            }
            if ($usage['usd'] > $cap) {
                $warnings[] = 'Actual AI cost exceeded the preflight heuristic; later AI calls were blocked.';
            }
            $traceStep('gate_summary', (new Quality\Gate)->evaluate($pageScores, $errors, $threshold));
            $traceStep('completed', ['ai_used' => $aiUsed]);
            $tables = app(Extractor::class)->tables($pages);
            $id = (string) Str::ulid();
            $result = new ExtractionResult($pages, $tables, $data, $fields, $typeName, array_values(array_unique($warnings)), $trace, $usage, languages: $languages, id: $id, aiConsulted: $aiUsed, metadata: ($options['internal_page_task'] ?? false) ? [] : app(Drivers\Native\PdfMetadata::class)->extract($document, $temp));
            if (config('ai-ocr.store.enabled', false) && ! ($options['internal_page_task'] ?? false)) {
                app(Persistence::class)->save($document, $result, $strategy, $cacheable ? $cacheKey : null, $options);
            }
            if ($ttl > 0 && $cacheable) {
                $this->cache->put($cacheKey, encrypt($result->toArray()), $ttl);
            }
            if (! ($options['internal_page_task'] ?? false)) {
                $this->events->dispatch(new ExtractionCompleted($document->hash, ['id' => $id]));
            }
            if (config('ai-ocr.webhooks.enabled') && ! ($options['internal_page_task'] ?? false)) {
                SendWebhookJob::dispatch($id);
            }
            if (isset($options['tap'])) {
                ($options['tap'])($result->trace());
            }

            return $result;
        } catch (\Throwable $e) {
            if (! $e instanceof AiOcrException && ! $e instanceof \InvalidArgumentException && ! $e instanceof \TypeError && ! $e instanceof \LogicException) {
                $e = new ExtractionFailedException('Document extraction failed; run ai-ocr:doctor and verify the configured storage.', 0, $e);
            }
            $this->events->dispatch(new ExtractionFailed('', ['exception' => $e::class]));
            if ($options['safe'] ?? false) {
                return new ExtractionResult(warningMessages: [$e instanceof AiOcrException ? $e->getMessage() : 'Extraction failed: '.$e::class], steps: $trace, failed: true);
            }
            throw $e;
        } finally {
            $temp->cleanup();
        }
    }

    private function cacheable(mixed $value): bool
    {
        if ($value instanceof \Closure || $value instanceof Schema || $value instanceof DocumentType) {
            return false;
        } if (is_array($value)) {
            foreach ($value as $item) {
                if (! $this->cacheable($item)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function packImage(string $path, Temp $temp): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
            throw new UnsupportedFileException('AI requires a supported raster image.');
        }
        if (config('ai-ocr.ai.image_driver', 'auto') !== 'gd' && class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick($path);
            } catch (\ImagickException $error) {
                throw new UnsupportedFileException('AI image packing could not decode the raster.', 0, $error);
            }
            try {
                $max = (int) config('ai-ocr.ai.image_max_side', 1800);
                $image->thumbnailImage($max, $max, true);
                $format = (string) config('ai-ocr.ai.image_format', 'jpeg');
                if (! in_array($format, ['jpeg', 'png'], true)) {
                    throw new \InvalidArgumentException('AI image format must be jpeg or png.');
                }
                $image->stripImage();
                $image->setImageFormat($format);
                $image->setImageCompressionQuality((int) config('ai-ocr.ai.image_quality', 85));
                $path = $temp->path('ai-image.'.$format);
                $image->writeImage($path);
                $mime = 'image/'.$format;
            } catch (\ImagickException $error) {
                throw new UnsupportedFileException('Cannot encode the AI image.', 0, $error);
            } finally {
                $image->clear();
            }
        } elseif (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring((string) file_get_contents($path));
            if ($image === false) {
                throw new UnsupportedFileException('GD cannot decode this image.');
            }
            try {
                $max = (int) config('ai-ocr.ai.image_max_side', 1800);
                $ratio = min(1, $max / max(imagesx($image), imagesy($image)));
                $width = max(1, (int) floor(imagesx($image) * $ratio));
                $height = max(1, (int) floor(imagesy($image) * $ratio));
                $output = imagecreatetruecolor($width, $height);
                if ($output === false) {
                    throw new UnsupportedFileException('Cannot allocate the resized image.');
                }
                imagefill($output, 0, 0, (int) imagecolorallocate($output, 255, 255, 255));
                imagecopyresampled($output, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
                $format = (string) config('ai-ocr.ai.image_format', 'jpeg');
                $path = $temp->path('ai-image.'.$format);
                if ($format === 'png') {
                    imagepng($output, $path);
                } elseif ($format === 'jpeg') {
                    imagejpeg($output, $path, (int) config('ai-ocr.ai.image_quality', 85));
                } else {
                    throw new \InvalidArgumentException('AI image format must be jpeg or png.');
                }
                $mime = 'image/'.$format;
                unset($output);
            } finally {
                unset($image);
            }
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
