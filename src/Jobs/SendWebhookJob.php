<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use ImranDevBd\AiOcr\Input\UrlFetcher;

final class SendWebhookJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries;

    public function __construct(public readonly string $extractionId)
    {
        $this->tries = (int) config('ai-ocr.webhooks.tries', 5);
    }

    public function handle(): void
    {
        $url = config('ai-ocr.webhooks.url');
        $secret = config('ai-ocr.webhooks.secret');
        if (! is_string($url) || ! is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException('Webhook URL and secret (at least 32 bytes) are required.');
        }
        $target = app(UrlFetcher::class)->validate($url);
        $timestamp = (string) time();
        $eventId = $this->extractionId;
        $body = json_encode(['event' => 'extraction.completed', 'id' => $eventId, 'timestamp' => (int) $timestamp], JSON_THROW_ON_ERROR);
        Http::withOptions(['proxy' => '', 'curl' => [CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.(str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'])]]])->withoutRedirecting()->timeout(15)->withHeaders(['X-Ai-Ocr-Timestamp' => $timestamp, 'X-Ai-Ocr-Event' => $eventId, 'X-Ai-Ocr-Signature' => hash_hmac('sha256', $timestamp.'.'.$body, $secret)])->withBody($body, 'application/json')->post($url)->throw();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }
}
