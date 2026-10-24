<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use Illuminate\Contracts\Cache\Repository;
use ImranDevBd\AiOcr\DTO\ExtractionResult;

final class JobTracker
{
    public function __construct(private readonly Repository $cache) {}

    /** @param array<string, mixed> $metadata */
    public function update(string $id, array $metadata): void
    {
        $this->cache->put('ai-ocr:progress:'.$id, encrypt($metadata), 86400);
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        $value = $this->cache->get('ai-ocr:progress:'.$id);
        if (! is_string($value)) {
            return null;
        } $decoded = decrypt($value);

        return is_array($decoded) ? $decoded : null;
    }

    public function complete(string $id, ExtractionResult $result): void
    {
        $metadata = $this->get($id) ?? [];
        $this->update($id, [...$metadata, 'status' => 'completed', 'result' => $result->toArray()]);
    }

    public function failed(string $id, \Throwable $exception): void
    {
        $this->update($id, [...($this->get($id) ?? []), 'status' => 'failed', 'exception' => $exception::class]);
    }

    public function page(string $id, int $number, ExtractionResult $result): void
    {
        $this->cache->put('ai-ocr:parallel:'.$id.':'.$number, encrypt($result->toArray()), 86400);
    }

    public function pageResult(string $id, int $number): ExtractionResult
    {
        $value = $this->cache->get('ai-ocr:parallel:'.$id.':'.$number);
        if (! is_string($value)) {
            throw new \RuntimeException('Parallel page result expired or is missing.');
        } $decoded = decrypt($value);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid parallel page result.');
        }

        return ExtractionResult::fromArray($decoded);
    }

    public function forgetPage(string $id, int $number): void
    {
        $this->cache->forget('ai-ocr:parallel:'.$id.':'.$number);
    }
}
