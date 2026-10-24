<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\DTO;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;

/** @implements Arrayable<string, mixed> */
final class ExtractionResult implements \JsonSerializable, Arrayable, Jsonable
{
    use Macroable;

    /**
     * @param  list<Page>  $pageResults
     * @param  list<Table>  $tableResults
     * @param  array<string, mixed>  $structuredData
     * @param  array<string, FieldValue>  $fields
     * @param  list<string>  $warningMessages
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, int|float>  $usage
     * @param  array<string,mixed>  $metadata
     * @param  list<string>  $languages
     */
    public function __construct(private readonly array $pageResults = [], private readonly array $tableResults = [], private readonly array $structuredData = [], private readonly array $fields = [], private readonly ?string $type = null, private readonly array $warningMessages = [], private readonly array $steps = [], private readonly array $usage = ['usd' => 0.0, 'tokens' => 0, 'latency_ms' => 0], private readonly bool $cached = false, public readonly bool $failed = false, private readonly array $languages = [], public readonly ?string $id = null, private readonly bool $aiConsulted = false, private readonly array $metadata = []) {}

    public function text(): string
    {
        return implode("\n\n", array_map(fn ($p) => $p->text, $this->pageResults));
    }

    /** @return list<Page> */
    public function pages(): array
    {
        return $this->pageResults;
    }

    /** @return list<Table> */
    public function tables(): array
    {
        return $this->tableResults;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->structuredData;
    }

    public function field(string $key): FieldValue
    {
        return $this->fields[$key] ?? new FieldValue(data_get($this->structuredData, $key));
    }

    /** @return array<string,mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function documentType(): ?string
    {
        return $this->type;
    }

    /** @return list<string> */
    public function language(): array
    {
        return $this->languages;
    }

    public function confidence(): float
    {
        $pageScore = $this->pageResults === [] ? 0.0 : array_sum(array_map(fn ($p) => $p->confidence, $this->pageResults)) / count($this->pageResults);

        return $this->fields === [] ? $pageScore : min($pageScore, array_sum(array_map(fn ($f) => $f->confidence, $this->fields)) / count($this->fields));
    }

    /** @return array<string, int> */
    public function engineSummary(): array
    {
        return array_count_values(array_map(fn ($p) => $p->engine, $this->pageResults));
    }

    public function usedAi(): bool
    {
        return $this->aiConsulted || ($this->engineSummary()['ai'] ?? 0) > 0 || count(array_filter($this->fields, fn ($f) => $f->engine === 'ai')) > 0;
    }

    /** @return array<string, int|float> */
    public function cost(): array
    {
        return $this->usage;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warningMessages;
    }

    /** @return list<array<string, mixed>> */
    public function trace(): array
    {
        return $this->steps;
    }

    public function fromCache(): bool
    {
        return $this->cached;
    }

    public function cachedCopy(): self
    {
        return new self($this->pageResults, $this->tableResults, $this->structuredData, $this->fields, $this->type, $this->warningMessages, $this->steps, ['usd' => 0.0, 'tokens' => 0, 'latency_ms' => 0], true, $this->failed, $this->languages, $this->id, $this->aiConsulted, $this->metadata);
    }

    public function toMarkdown(): string
    {
        return $this->text().($this->tableResults ? "\n\n".implode("\n\n", array_map(fn ($t) => $t->toMarkdown(), $this->tableResults)) : '');
    }

    public function toHtml(): string
    {
        return '<pre>'.htmlspecialchars($this->text(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</pre>';
    }

    public function toCsv(): string
    {
        return implode("\n", array_map(fn ($t) => $t->toCsv(), $this->tableResults));
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['metadata' => $this->metadata, 'id' => $this->id, 'text' => $this->text(), 'pages' => array_map(fn ($p) => $p->jsonSerialize(), $this->pageResults), 'tables' => array_map(fn ($t) => $t->jsonSerialize(), $this->tableResults), 'data' => $this->structuredData, 'fields' => array_map(fn ($f) => $f->jsonSerialize(), $this->fields), 'document_type' => $this->type, 'languages' => $this->languages, 'confidence' => $this->confidence(), 'engines' => $this->engineSummary(), 'used_ai' => $this->usedAi(), 'cost' => $this->usage, 'warnings' => $this->warningMessages, 'trace' => $this->steps, 'from_cache' => $this->cached, 'failed' => $this->failed];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function save(mixed $target): void
    {
        if ($target instanceof Model) {
            $target->setAttribute('ocr_data', $this->toArray());
            $target->save();
        } elseif (is_string($target)) {
            if (file_put_contents($target, $this->toJson(JSON_PRETTY_PRINT)) === false) {
                throw new \RuntimeException('Cannot save OCR result.');
            }
        } else {
            throw new \InvalidArgumentException('Expected an Eloquent model or output path.');
        }
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        return new self(array_values(array_map(fn ($p) => new Page(...$p), $value['pages'] ?? [])), array_values(array_map(fn ($t) => new Table(...$t), $value['tables'] ?? [])), $value['data'] ?? [], array_map(fn ($f) => new FieldValue(...$f), $value['fields'] ?? []), $value['document_type'] ?? null, $value['warnings'] ?? [], $value['trace'] ?? [], $value['cost'] ?? [], $value['from_cache'] ?? false, $value['failed'] ?? false, $value['languages'] ?? [], $value['id'] ?? null, $value['used_ai'] ?? false, $value['metadata'] ?? []);
    }
}
