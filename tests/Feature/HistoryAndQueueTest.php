<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Support\Analytics;
use ImranDevBd\AiOcr\Tests\TestCase;

final class HistoryAndQueueTest extends TestCase
{
    public function test_queued_api_upload_tracks_result_and_keeps_its_owner(): void
    {
        $this->actingAs(new GenericUser(['id' => 11]));
        AiOcr::auth(fn () => true);
        config(['queue.default' => 'sync']);
        $response = $this->postJson('/ai-ocr/extract', ['file' => UploadedFile::fake()->createWithContent('input.txt', 'Readable uploaded document to exercise queued extraction and private progress.'), 'queued' => true, 'strategy' => 'manual_only']);
        $response->assertStatus(202);
        $url = $response->json('poll_url');
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'completed')->assertJsonPath('result.used_ai', false);
        $this->actingAs(new GenericUser(['id' => 12]));
        $this->getJson($url)->assertNotFound();
    }

    public function test_retained_original_is_encrypted_and_can_be_rerun_and_reviewed(): void
    {
        $this->migrate();
        Storage::fake('local');
        config(['ai-ocr.store.enabled' => true, 'ai-ocr.store.retain_original' => true]);
        AiOcr::auth(fn () => true);
        $this->actingAs(new GenericUser(['id' => 21]));
        $text = 'Readable original document for retained source replay with new extraction settings.';
        $result = AiOcr::file($text)->manualOnly()->extract();
        $record = Extraction::findOrFail($result->id);
        $document = $record->document;
        self::assertStringNotContainsString($text, Storage::disk('local')->get($document->path));
        $this->postJson('/ai-ocr/extractions/'.$result->id.'/rerun', ['strategy' => 'manual_only'])->assertOk()->assertJsonPath('text', $text);
        $this->postJson('/ai-ocr/extractions/'.$result->id.'/review', ['correct' => true])->assertOk();
        $stats = app(Analytics::class)->summary();
        self::assertEquals(1.0, $stats['per_type']['text']['reviewed_accuracy']);
        $this->actingAs(new GenericUser(['id' => 22]));
        $this->postJson('/ai-ocr/extractions/'.$result->id.'/rerun')->assertNotFound();
    }

    public function test_saved_schema_templates_are_owner_scoped(): void
    {
        $this->migrate();
        config(['ai-ocr.store.enabled' => true]);
        AiOcr::auth(fn () => true);
        $this->actingAs(new GenericUser(['id' => 31]));
        $this->postJson('/ai-ocr/schemas', ['name' => 'Reference', 'schema' => ['type' => 'object', 'properties' => ['reference' => ['type' => 'string']]]])->assertCreated();
        $this->getJson('/ai-ocr/schemas')->assertJsonCount(1);
        $this->actingAs(new GenericUser(['id' => 32]));
        $this->getJson('/ai-ocr/schemas')->assertExactJson([]);
    }
}
