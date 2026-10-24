<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use ImranDevBd\AiOcr\AiOcrManager;
use ImranDevBd\AiOcr\Support\JobTracker;

final class ExtractPageJob implements ShouldBeEncrypted, ShouldQueue
{
    use Batchable, Dispatchable, \Illuminate\Queue\InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $options */
    public function __construct(public readonly string $path, public readonly int $number, public readonly string $trackingId, public readonly array $options) {}

    public function handle(AiOcrManager $ocr, JobTracker $tracker): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $result = $ocr->extract($this->path, [...$this->options, 'pages' => (string) $this->number, 'internal_page_task' => true, 'cache' => 0]);
        $tracker->page($this->trackingId, $this->number, $result);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 180];
    }
}
