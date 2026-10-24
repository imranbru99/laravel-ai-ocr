<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use ImranDevBd\AiOcr\AiOcrManager;
use ImranDevBd\AiOcr\Support\JobTracker;

final class ExtractDocumentJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public readonly string $trackingId;

    private readonly string $fingerprint;

    public int $uniqueFor = 3600;

    /** @param array<string, mixed> $options */
    public function __construct(public readonly string $path, public readonly array $options = [])
    {
        $uniqueOptions = $options;
        unset($uniqueOptions['callbacks'],$uniqueOptions['tap'],$uniqueOptions['tracking_id']);
        $this->fingerprint = hash('sha256', (hash_file('sha256', $path) ?: $path).serialize($uniqueOptions));
        $this->trackingId = $options['tracking_id'] ?? (string) Str::ulid();
        app(JobTracker::class)->update($this->trackingId, ['status' => 'queued', 'owner_id' => $options['owner_id'] ?? null, 'owner_type' => $options['owner_type'] ?? null]);
    }

    public function handle(AiOcrManager $ocr): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $tracker = app(JobTracker::class);
        $tracker->update($this->trackingId, [...($tracker->get($this->trackingId) ?? []), 'status' => 'processing']);
        $result = $ocr->extract($this->path, $this->options);
        $tracker->complete($this->trackingId, $result);
        ($this->options['callbacks'] ?? null)?->completed($result);
        if ($this->options['delete_source'] ?? false) {
            @unlink($this->path);
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

    public function uniqueId(): string
    {
        return $this->fingerprint;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 180];
    }
}
