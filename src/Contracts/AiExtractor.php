<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Contracts;

interface AiExtractor
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $options
     * @return array{data: array<string, mixed>, usage: array<string, int|float>, provider: string, model: string}
     */
    public function generate(string $text, array $schema, array $options, ?string $image = null): array;
}
