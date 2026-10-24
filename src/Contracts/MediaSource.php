<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Contracts;

interface MediaSource
{
    public function ocrPath(): string;

    public function ocrDisk(): ?string;
}
