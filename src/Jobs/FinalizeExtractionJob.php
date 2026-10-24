<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use ImranDevBd\AiOcr\AiOcrManager;
use ImranDevBd\AiOcr\Support\JobTracker;

final class FinalizeExtractionJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    /**
     * @param  list<int>  $numbers
     * @param  array<string,mixed>  $options
     */
    public function __construct(public readonly string $path, public readonly array $numbers, public readonly string $trackingId, public readonly array $options) {}

    public function handle(AiOcrManager $ocr, JobTracker $tracker): void
    {
        $pages = [];
        $warnings = [];
        $usedAi = false;
        $usage = ['usd' => 0.0, 'tokens' => 0, 'latency_ms' => 0.0];
        foreach ($this->numbers as $number) {
            $result = $tracker->pageResult($this->trackingId, $number);
            array_push($pages, ...$result->pages());
            array_push($warnings, ...$result->warnings());
            $usedAi = $usedAi || $result->usedAi();
            foreach ($usage as $key => $value) {
                $usage[$key] += $result->cost()[$key];
            }
        }
        usort($pages, fn ($a, $b) => $a->number <=> $b->number);
        $result = $ocr->extract($this->path, [...$this->options, 'prepared_pages' => $pages, 'initial_usage' => $usage, 'initial_warnings' => $warnings, 'initial_ai_used' => $usedAi]);
        $tracker->complete($this->trackingId, $result);
        ($this->options['callbacks'] ?? null)?->completed($result);
        if ($this->options['delete_source'] ?? false) {
            @unlink($this->path);
        }
        foreach ($this->numbers as $number) {
            $tracker->forgetPage($this->trackingId, $number);
        }
    }

    public function failed(\Throwable $error): void
    {
        app(JobTracker::class)->failed($this->trackingId, $error);
        ($this->options['callbacks'] ?? null)?->failed($error);
        if ($this->options['delete_source'] ?? false) {
            @unlink($this->path);
        }
    }
}
