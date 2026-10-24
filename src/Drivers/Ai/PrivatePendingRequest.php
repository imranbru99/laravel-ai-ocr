<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Drivers\Ai;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use ImranDevBd\AiHub\Data\AiResponse;
use ImranDevBd\AiHub\PendingRequest;

/** @internal v1.6-compatible hooks preserving Hub analytics without document previews. */
final class PrivatePendingRequest extends PendingRequest
{
    protected function log(AiResponse $response): void
    {
        if (config('ai-ocr.privacy.log_content', false)) {
            parent::log($response);

            return;
        }
        $safe = $response->toArray();
        $safe['content'] = '';
        $safe['error'] = $response->error === null ? null : 'AI OCR request failed; content logging disabled.';
        $safe['tool_calls'] = [];
        $safe['meta'] = array_intersect_key($response->meta, array_flip(['priority_rank', 'failover_tried', 'type', 'cache']));
        parent::log(AiResponse::fromArray($safe));
    }

    /** @param array<string, mixed> $payload */
    protected function remember(AiResponse $response, string $model, array $payload): void
    {
        if (! $this->cacheTtl || ! $response->success) {
            return;
        }
        Cache::put($this->cacheKey($model, $payload), encrypt($response->toArray()), $this->cacheTtl);
    }

    /** @param array<string, mixed> $payload */
    protected function cachedResponse(string $model, array $payload): ?AiResponse
    {
        if (! $this->cacheTtl) {
            return null;
        }
        $value = Cache::get($this->cacheKey($model, $payload));
        if (! is_string($value)) {
            return null;
        }
        try {
            $data = decrypt($value);
        } catch (DecryptException) {
            return null;
        }
        if (! is_array($data)) {
            return null;
        }
        $result = AiResponse::fromArray([...$data, 'cost_usd' => 0.0, 'total_tokens' => 0, 'meta' => ['type' => 'cache_hit', 'cache' => true]]);
        $this->log($result);

        return $result;
    }

    /** @param array<string, mixed> $payload */
    protected function cacheKey(string $model, array $payload): string
    {
        return 'ai-ocr:hub:'.hash('sha256', parent::cacheKey($model, $payload).(string) config('ai-ocr.ai.prompt_version', 'v1'));
    }
}
