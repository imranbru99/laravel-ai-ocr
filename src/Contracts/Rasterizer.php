<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Contracts;

use ImranDevBd\AiOcr\Input\Document;
use ImranDevBd\AiOcr\Support\Temp;

interface Rasterizer
{
    public function page(Document $document, int $page, int $dpi, Temp $temp): string;
}
