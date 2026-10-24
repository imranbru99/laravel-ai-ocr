<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use ImranDevBd\AiOcr\Drivers\Native\Extractor;
use ImranDevBd\AiOcr\Input\Resolver;
use ImranDevBd\AiOcr\Jobs\ExtractPageJob;
use ImranDevBd\AiOcr\Jobs\FinalizeExtractionJob;

final class PageBatchDispatcher
{
    public function __construct(private readonly Resolver $resolver, private readonly Extractor $native, private readonly JobTracker $tracker) {}

    /** @param array<string,mixed> $options */
    public function dispatch(string $path, array $options): Batch
    {
        $temp = new Temp;
        try {
            $document = $this->resolver->resolve($path, $temp);
            $count = $this->native->pageCount($document);
        } finally {
            $temp->cleanup();
        }
        $numbers = isset($options['pages']) ? array_values(array_filter(Pages::parse($options['pages']), fn ($n) => $n <= $count)) : range(1, $count);
        if ($numbers === []) {
            throw new \InvalidArgumentException('No selected pages exist.');
        }
        $id = $options['tracking_id'] ?? (string) Str::ulid();
        $this->tracker->update($id, ['status' => 'queued', 'owner_id' => $options['owner_id'] ?? null, 'owner_type' => $options['owner_type'] ?? null, 'pages' => count($numbers)]);
        $jobs = [];
        $pageOptions = $options;
        $pageOptions['privacy_detect'] = $options['auto_detect'] ?? false;
        $pageOptions['auto_detect'] = false;
        unset($pageOptions['schema'],$pageOptions['require_ai'],$pageOptions['callbacks'],$pageOptions['tap']);
        // Partition the document allowance so independent workers cannot each spend the full document cap.
        $pageOptions['max_cost'] = min((float) ($options['max_cost'] ?? INF), (float) config('ai-ocr.ai.max_cost_per_document', 0.25)) / count($numbers);
        foreach ($numbers as $index => $number) {
            $perPage = $pageOptions;
            if ($index >= (int) config('ai-ocr.ai.max_pages_per_document', 30)) {
                $perPage['strategy'] = 'manual_only';
            } $jobs[] = new ExtractPageJob($path, $number, $id, $perPage);
        }
        $queue = $options['queue'] ?? config('ai-ocr.queue.queue', 'ocr');
        $connection = config('ai-ocr.queue.connection');
        $batch = Bus::batch($jobs)->name('ai-ocr:'.$id)->onQueue($queue);
        if ($connection !== null) {
            $batch->onConnection($connection);
        }

        return $batch->then(static function (Batch $batch) use ($path, $numbers, $id, $options, $queue, $connection): void {
            FinalizeExtractionJob::dispatch($path, $numbers, $id, $options)->onQueue($queue)->onConnection($connection);
        })
            ->catch(static function (Batch $batch, \Throwable $error) use ($id, $options, $path): void {
                app(JobTracker::class)->failed($id, $error);
                ($options['callbacks'] ?? null)?->failed($error);
                if ($options['delete_source'] ?? false) {
                    @unlink($path);
                }
            })->dispatch();
    }
}
