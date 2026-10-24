<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Concerns;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use ImranDevBd\AiOcr\AiOcrManager;
use ImranDevBd\AiOcr\Contracts\MediaSource;
use ImranDevBd\AiOcr\ExtractionRequest;
use ImranDevBd\AiOcr\Models\Document;
use ImranDevBd\AiOcr\Models\Extraction;

trait HasOcrDocuments
{
    /** @return MorphMany<Document, $this> */
    public function ocrDocuments(): MorphMany
    {
        return $this->morphMany(Document::class, 'owner');
    }

    /** @return HasManyThrough<Extraction,Document,$this> */
    public function ocrExtractions(): HasManyThrough
    {
        return $this->hasManyThrough(Extraction::class, Document::class, 'owner_id', 'document_id', $this->getKeyName(), 'id')->where('ai_ocr_documents.owner_type', $this->getMorphClass());
    }

    public function ocr(): ExtractionRequest
    {
        if (! $this instanceof MediaSource) {
            throw new \LogicException('Implement MediaSource::ocrPath() and ocrDisk() on this model.');
        }

        return app(AiOcrManager::class)->file($this)->ownedBy($this);
    }
}
