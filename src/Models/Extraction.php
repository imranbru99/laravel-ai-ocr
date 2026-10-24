<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ImranDevBd\AiOcr\DTO\ExtractionResult;

final class Extraction extends Model
{
    use HasUlids, MassPrunable;

    protected $table = 'ai_ocr_extractions';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['engine_summary' => 'array', 'options' => 'array', 'review_correct' => 'boolean'];

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays((int) config('ai-ocr.store.prune_days', 90)));
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function extractionResult(): ExtractionResult
    {
        $value = json_decode((string) $this->getAttribute('result'), true, 512, JSON_THROW_ON_ERROR);
        if (isset($value['encrypted'])) {
            $value = json_decode(decrypt($value['encrypted']), true, 512, JSON_THROW_ON_ERROR);
        }

        return ExtractionResult::fromArray($value);
    }
}
