<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use Illuminate\Contracts\Cache\Repository;

final class WebhookVerifier
{
    public function __construct(private readonly Repository $cache) {}

    public function verify(string $body, string $timestamp, string $eventId, string $signature, string $secret, int $tolerance = 300): bool
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $tolerance || ! preg_match('/^[A-Za-z0-9_-]{10,100}$/D', $eventId)) {
            return false;
        }
        if (! hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), $signature)) {
            return false;
        }

        return $this->cache->add('ai-ocr:webhook-received:'.hash('sha256', $eventId), true, $tolerance * 2);
    }
}
