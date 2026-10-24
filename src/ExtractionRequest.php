<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr;

use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\PendingDispatch;
use ImranDevBd\AiOcr\DTO\ExtractionResult;
use ImranDevBd\AiOcr\DTO\Page;
use ImranDevBd\AiOcr\Enums\Strategy;
use ImranDevBd\AiOcr\Jobs\ExtractDocumentJob;
use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Types\DocumentType;

final class ExtractionRequest
{
    /** @var array<string, mixed> */
    private array $options = [];

    public function __construct(private readonly AiOcrManager $manager, private readonly mixed $input) {}

    public function using(string|Strategy $strategy): self
    {
        $this->options['strategy'] = $strategy instanceof Strategy ? $strategy->value : Strategy::from($strategy)->value;

        return $this;
    }

    public function ownedBy(Model $owner): self
    {
        $this->options['owner_id'] = $owner->getKey();
        $this->options['owner_type'] = $owner->getMorphClass();

        return $this;
    }

    public function manualOnly(): self
    {
        return $this->using('manual_only');
    }

    public function withoutAi(): self
    {
        return $this->manualOnly();
    }

    public function aiOnly(): self
    {
        return $this->using('ai_only');
    }

    public function requireAi(): self
    {
        $this->options['require_ai'] = true;

        return $this;
    }

    public function provider(string $provider): self
    {
        $this->options['provider'] = $provider;

        return $this;
    }

    public function model(string $model): self
    {
        $this->options['model'] = $model;

        return $this;
    }

    public function disk(string $disk): self
    {
        $this->options['disk'] = $disk;

        return $this;
    }

    /** @param list<string> $languages */
    public function languages(array $languages): self
    {
        $this->options['languages'] = $languages;

        return $this;
    }

    public function pages(string $pages): self
    {
        if (! preg_match('/^\d+(?:-\d+)?(?:,\d+(?:-\d+)?)*$/D', $pages)) {
            throw new \InvalidArgumentException('Pages must be ranges such as 1-3,5.');
        } $this->options['pages'] = $pages;

        return $this;
    }

    public function dpi(int $dpi): self
    {
        if ($dpi < 72 || $dpi > 600) {
            throw new \InvalidArgumentException('DPI must be between 72 and 600.');
        } $this->options['dpi'] = $dpi;

        return $this;
    }

    /** @param list<string> $steps */
    public function preprocess(array $steps): self
    {
        $this->options['preprocess'] = $steps;

        return $this;
    }

    /** @param Schema|DocumentType|array<string, mixed>|class-string<DocumentType>|string $schema */
    public function as(Schema|DocumentType|array|string $schema): self
    {
        $this->options['schema'] = $schema;

        return $this;
    }

    public function autoDetectType(): self
    {
        $this->options['auto_detect'] = true;

        return $this;
    }

    /** @param array<string, mixed> $rules */
    public function validate(array $rules): self
    {
        $this->options['rules'] = $rules;

        return $this;
    }

    public function minConfidence(float $confidence): self
    {
        if ($confidence < 0 || $confidence > 1) {
            throw new \InvalidArgumentException('Confidence must be in [0,1].');
        } $this->options['min_confidence'] = $confidence;

        return $this;
    }

    public function maxCost(float $usd): self
    {
        if ($usd < 0) {
            throw new \InvalidArgumentException('Cost must be nonnegative.');
        } $this->options['max_cost'] = $usd;

        return $this;
    }

    /** @param list<string> $fields */
    public function redact(array $fields): self
    {
        $this->options['redact'] = $fields;

        return $this;
    }

    public function cache(int $seconds): self
    {
        $this->options['cache'] = max(0, $seconds);

        return $this;
    }

    public function noCache(): self
    {
        return $this->cache(0);
    }

    public function forJob(string $tag): self
    {
        $this->options['job'] = $tag;

        return $this;
    }

    public function onQueue(string $queue): self
    {
        $this->options['queue'] = $queue;

        return $this;
    }

    public function safe(): self
    {
        $this->options['safe'] = true;

        return $this;
    }

    public function tap(callable $callback): self
    {
        $this->options['tap'] = $callback;

        return $this;
    }

    public function extract(): ExtractionResult
    {
        return $this->manager->extract($this->input, $this->options);
    }

    public function text(): string
    {
        return $this->extract()->text();
    }

    /** @return \Generator<int, Page> */
    public function stream(): \Generator
    {
        $fiber = new \Fiber(fn () => $this->manager->extract($this->input, [...$this->options, 'cache' => 0, 'page_callback' => static fn ($page) => \Fiber::suspend($page)]));
        $page = $fiber->start();
        while ($fiber->isSuspended()) {
            yield $page;
            $page = $fiber->resume();
        }
    }

    public function deleteSourceAfterCompletion(): self
    {
        $this->options['delete_source'] = true;

        return $this;
    }

    public function then(\Closure $callback): self
    {
        $this->callbacks()->then($callback);

        return $this;
    }

    public function catch(\Closure $callback): self
    {
        $this->callbacks()->catch($callback);

        return $this;
    }

    public function finally(\Closure $callback): self
    {
        $this->callbacks()->finally($callback);

        return $this;
    }

    private function callbacks(): Support\QueuedCallbacks
    {
        return $this->options['callbacks'] ??= new Support\QueuedCallbacks;
    }

    public function trackingId(string $id): self
    {
        if (! preg_match('/^[A-Za-z0-9_-]{10,100}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid tracking ID.');
        } $this->options['tracking_id'] = $id;

        return $this;
    }

    public function parallel(bool $enabled = true): self
    {
        $this->options['parallel'] = $enabled;

        return $this;
    }

    public function dispatch(): PendingDispatch|Batch
    {
        if (! is_string($this->input) || ! is_file($this->input)) {
            throw new \InvalidArgumentException('Queued extraction requires a durable local file path. Copy uploads to private persistent storage first.');
        }
        $options = Support\JobOptions::prepare($this->options);
        if (($options['parallel'] ?? false) && config('ai-ocr.queue.batch', true)) {
            return app(Support\PageBatchDispatcher::class)->dispatch($this->input, $options);
        }
        $job = new ExtractDocumentJob($this->input, $options);
        $job->onQueue($this->options['queue'] ?? config('ai-ocr.queue.queue', 'ocr'));
        $job->onConnection(config('ai-ocr.queue.connection'));

        return dispatch($job);
    }
}
