<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\DTO;

final readonly class FieldValue implements \JsonSerializable
{
    public function __construct(public mixed $value, public float $confidence = 0.0, public ?int $page = null, public ?string $source_quote = null, public string $engine = 'native', public bool $validated = false) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
