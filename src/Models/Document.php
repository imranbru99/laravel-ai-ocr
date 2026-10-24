<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Document extends Model
{
    use HasUlids;

    protected $table = 'ai_ocr_documents';

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<Extraction, $this> */
    public function extractions(): HasMany
    {
        return $this->hasMany(Extraction::class);
    }
}
