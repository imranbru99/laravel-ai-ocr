<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Contracts;

use ImranDevBd\AiOcr\DTO\Page;

interface OcrEngine
{
    /** @param list<string> $languages */
    public function recognize(string $path, int $number, array $languages): Page;
}
