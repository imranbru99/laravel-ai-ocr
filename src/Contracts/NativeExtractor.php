<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Contracts;

use ImranDevBd\AiOcr\DTO\Page;
use ImranDevBd\AiOcr\Input\Document;

interface NativeExtractor
{
    /** @return list<Page> */
    public function extract(Document $document): array;
}
