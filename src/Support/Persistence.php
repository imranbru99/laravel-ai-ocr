<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ImranDevBd\AiOcr\DTO\ExtractionResult;
use ImranDevBd\AiOcr\Input\Document;
use ImranDevBd\AiOcr\Models\Document as DocumentModel;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Types\DocumentType;

final class Persistence
{
    /** @param array<string, mixed> $options */
    public function save(Document $document, ExtractionResult $result, string $strategy, ?string $fingerprint = null, array $options = []): void
    {
        DB::transaction(function () use ($document, $result, $strategy, $fingerprint, $options): void {
            $owner = auth()->guard()->user();
            $stored = DocumentModel::query()->firstOrCreate(['hash' => $document->hash, 'owner_id' => $options['owner_id'] ?? $owner?->getAuthIdentifier(), 'owner_type' => $options['owner_type'] ?? (Owner::type($owner))], ['mime' => $document->mime, 'size' => $document->size, 'original_name' => mb_substr($document->name, 0, 255)]);
            app(Sources::class)->retain($stored, $document->path);
            $savedOptions = array_intersect_key($options, array_flip(['schema', 'strategy', 'languages', 'dpi', 'preprocess', 'provider', 'model', 'redact', 'max_cost', 'min_confidence']));
            if (($savedOptions['schema'] ?? null) instanceof Schema) {
                $savedOptions['schema'] = $savedOptions['schema']->toJsonSchema();
            }
            if (($savedOptions['schema'] ?? null) instanceof DocumentType) {
                $savedOptions['schema'] = $savedOptions['schema']->name();
            }
            $payload = $result->toArray();
            if (! config('ai-ocr.store.store_text', true)) {
                $payload['text'] = '';
                $payload['pages'] = array_map(fn ($p) => [...$p, 'text' => '', 'words' => [], 'blocks' => []], $payload['pages']);
                foreach ($payload['fields'] as &$field) {
                    $field['source_quote'] = null;
                } unset($field);
                $payload['tables'] = [];
            }
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (config('ai-ocr.store.encrypt', true)) {
                $json = json_encode(['encrypted' => encrypt($json)], JSON_THROW_ON_ERROR);
            }
            Extraction::query()->create(['id' => $result->id, 'fingerprint' => $fingerprint, 'document_id' => $stored->getKey(), 'strategy' => $strategy, 'type' => $result->documentType(), 'status' => $result->failed ? 'failed' : 'completed', 'confidence' => $result->confidence(), 'engine_summary' => $result->engineSummary(), 'cost' => $result->cost()['usd'], 'tokens' => $result->cost()['tokens'], 'duration' => (int) ($result->trace()[array_key_last($result->trace()) ?? 0]['elapsed_ms'] ?? 0), 'result' => $json, 'options' => $savedOptions]);
            foreach ($result->pages() as $page) {
                $text = config('ai-ocr.store.store_text', true) ? $page->text : null;
                DB::table('ai_ocr_pages')->insert(['id' => (string) Str::ulid(), 'extraction_id' => $result->id, 'number' => $page->number, 'text' => $text !== null && config('ai-ocr.store.encrypt', true) ? encrypt($text) : $text, 'engine' => $page->engine, 'confidence' => $page->confidence, 'garbled' => $page->garbled, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}
