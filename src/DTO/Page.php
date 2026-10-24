<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\DTO;

final readonly class Page implements \JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $words
     * @param  list<array<string, mixed>>  $blocks
     */
    public function __construct(public int $number, public string $text, public string $engine = 'native', public float $confidence = 1.0, public array $words = [], public array $blocks = [], public bool $garbled = false) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
