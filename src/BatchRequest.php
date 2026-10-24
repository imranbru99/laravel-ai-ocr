<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use ImranDevBd\AiOcr\DTO\ExtractionResult;
use ImranDevBd\AiOcr\Enums\Strategy;
use ImranDevBd\AiOcr\Jobs\ExtractDocumentJob;

final class BatchRequest
{
    /** @var array<string, mixed> */
    private array $options = [];

    private ?\Closure $then = null;

    private ?\Closure $catch = null;

    private ?\Closure $finally = null;

    /** @param list<mixed> $inputs */
    public function __construct(private readonly AiOcrManager $manager, private readonly array $inputs) {}

    public function using(string $strategy): self
    {
        $this->options['strategy'] = Strategy::from($strategy)->value;

        return $this;
    }

    public function then(\Closure $callback): self
    {
        $this->then = $callback;

        return $this;
    }

    public function catch(\Closure $callback): self
    {
        $this->catch = $callback;

        return $this;
    }

    public function finally(\Closure $callback): self
    {
        $this->finally = $callback;

        return $this;
    }

    /** @return list<ExtractionResult> */
    public function extract(): array
    {
        $results = [];
        try {
            foreach ($this->inputs as $input) {
                $results[] = $this->manager->extract($input, $this->options);
            } if ($this->then) {
                ($this->then)($results);
            }

            return $results;
        } catch (\Throwable $e) {
            if ($this->catch) {
                ($this->catch)($e);
            } throw $e;
        } finally {
            if ($this->finally) {
                ($this->finally)($results);
            }
        }
    }

    public function dispatch(): Batch
    {
        if (! config('ai-ocr.queue.batch', true)) {
            throw new \LogicException('Document batches are disabled by ai-ocr.queue.batch.');
        }
        $jobs = [];
        foreach ($this->inputs as $input) {
            if (! is_string($input) || ! is_file($input)) {
                throw new \InvalidArgumentException('Queued batches require durable local file paths.');
            } $jobs[] = new ExtractDocumentJob($input, Support\JobOptions::prepare($this->options));
        } $batch = Bus::batch($jobs)->name('ai-ocr')->onQueue(config('ai-ocr.queue.queue', 'ocr'));
        if ($this->then) {
            $batch->then($this->then);
        } if ($this->catch) {
            $batch->catch($this->catch);
        } if ($this->finally) {
            $batch->finally($this->finally);
        }

        return $batch->dispatch();
    }
}
