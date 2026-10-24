<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Events;

final readonly class PageExtracted
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $hash, public array $metadata = []) {}
}
