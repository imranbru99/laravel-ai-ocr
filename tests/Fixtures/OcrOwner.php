<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use ImranDevBd\AiOcr\Concerns\HasOcrDocuments;
use ImranDevBd\AiOcr\Contracts\MediaSource;

final class OcrOwner extends Model implements MediaSource
{
    use HasOcrDocuments;

    public function ocrPath(): string
    {
        return (string) $this->getAttribute('document_path');
    }

    public function ocrDisk(): ?string
    {
        return null;
    }
}
