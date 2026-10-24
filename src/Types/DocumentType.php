<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Types;

use ImranDevBd\AiOcr\Schema\Schema;

abstract class DocumentType
{
    abstract public function name(): string;

    abstract public function schema(): Schema;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function crossChecks(array $data): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        return $data;
    }

    public function promptHints(): string
    {
        return '';
    }
}
