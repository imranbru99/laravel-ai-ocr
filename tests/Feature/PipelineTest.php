<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use ImranDevBd\AiHub\Facades\AIHub;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Tests\Fixtures\Fixtures;
use ImranDevBd\AiOcr\Tests\TestCase;

final class PipelineTest extends TestCase
{
    public function test_good_native_text_never_calls_ai(): void
    {
        $this->hubFake('{}');
        $result = AiOcr::file('This is a readable English document with enough content for the quality gate.')->extract();
        self::assertFalse($result->usedAi());
        self::assertSame(1.0, $result->confidence());
        AIHub::assertSentCount(0);
    }

    public function test_text_pdf_extracts_real_native_pages(): void
    {
        $this->hubFake('{}');
        $pdf = Fixtures::pdf(['This first page has sufficient readable native text.', 'This second page also has readable native text.']);
        $result = AiOcr::file($pdf)->extract();
        self::assertCount(2, $result->pages());
        self::assertStringContainsString('first page', $result->pages()[0]->text);
        self::assertSame(['native' => 2], $result->engineSummary());
        AIHub::assertSentCount(0);
    }

    public function test_scanned_document_without_ai_key_returns_warnings(): void
    {
        config(['ai-hub.providers' => []]);
        $result = AiOcr::file(Fixtures::pdf(['']))->extract();
        self::assertFalse($result->failed);
        self::assertFalse($result->usedAi());
        self::assertNotEmpty($result->warnings());
    }

    public function test_only_failed_pdf_page_is_sent_and_order_is_preserved(): void
    {
        $this->hubFake('{"text":"Recovered second page has enough readable text to pass quality.","confidence":0.95}');
        $result = AiOcr::file(Fixtures::pdf(['This first page already has enough readable native text.', '']))->extract();
        self::assertSame('native', $result->pages()[0]->engine);
        self::assertSame('ai', $result->pages()[1]->engine);
        self::assertSame(2, $result->pages()[1]->number);
        AIHub::assertSentCount(1);
        AIHub::assertSent(fn ($request) => count($request->getImages()) === 1);
    }

    public function test_missing_fields_use_text_only_and_validate_source_quotes(): void
    {
        $this->hubFake('{"data":{"total":12500},"evidence":[{"field":"total","confidence":0.95,"page":1,"source_quote":"Grand total is 125.00"}]}');
        $schema = Schema::make('bill')->money('total')->required();
        $result = AiOcr::file('Customer invoice with readable English text. Grand total is 125.00')->as($schema)->extract();
        self::assertSame(12500, $result->data()['total']);
        self::assertTrue($result->field('total')->validated);
        AIHub::assertSent(fn ($request) => $request->getImages() === []);
    }

    public function test_hallucinated_quote_is_rejected(): void
    {
        $this->hubFake('{"data":{"total":12500},"evidence":[{"field":"total","confidence":0.95,"page":1,"source_quote":"invented quotation"}]}');
        $result = AiOcr::file('A readable invoice with sufficient English text but no known total.')->as(Schema::make()->money('total')->required())->extract();
        self::assertSame([], $result->data());
        self::assertStringContainsString('Rejected ungrounded', implode(' ', $result->warnings()));
    }

    public function test_manual_strategy_overrides_all_ai_fallback(): void
    {
        $this->hubFake('{}');
        AiOcr::file('short')->manualOnly()->extract();
        AIHub::assertSentCount(0);
    }

    public function test_zero_budget_blocks_requests(): void
    {
        $this->hubFake('{}');
        $result = AiOcr::file($this->image())->maxCost(0)->extract();
        AIHub::assertSentCount(0);
        self::assertNotEmpty($result->warnings());
    }

    public function test_local_only_nid_never_reaches_cloud(): void
    {
        $this->hubFake('{}');
        config(['ai-hub.providers.ollama.enabled' => false]);
        $result = AiOcr::file('National identity readable document with missing identifying fields.')->as('bd_nid')->extract();
        AIHub::assertSentCount(0);
        self::assertNotEmpty($result->warnings());
    }

    public function test_cache_second_call_is_free(): void
    {
        $this->hubFake('{"text":"Recovered image text has sufficient readable content for quality.","confidence":0.96}');
        $first = AiOcr::file($this->image())->cache(60)->extract();
        $second = AiOcr::file($this->image())->cache(60)->extract();
        self::assertTrue($first->usedAi());
        self::assertTrue($second->fromCache());
        self::assertSame(0.0, $second->cost()['usd']);
        AIHub::assertSentCount(1);
    }

    public function test_extra_ai_keys_are_rejected(): void
    {
        $this->hubFake('{"text":"Readable recovered text of sufficient length.","confidence":1,"execute":"bad"}');
        $result = AiOcr::file($this->image())->extract();
        self::assertFalse($result->usedAi());
        self::assertNotEmpty($result->warnings());
    }

    public function test_safe_mode_returns_failure_for_unsupported_document(): void
    {
        $result = AiOcr::file('<svg xmlns="http://www.w3.org/2000/svg"></svg>')->safe()->extract();
        self::assertTrue($result->failed);
    }

    public function test_fake_records_consumer_calls(): void
    {
        AiOcr::fake();
        AiOcr::file('test')->manualOnly()->extract();
        AiOcr::assertExtractedCount(1);
        AiOcr::assertExtracted(fn ($entry) => $entry['options']['strategy'] === 'manual_only');
        AiOcr::assertNotUsedAi();
    }

    public function test_storage_encrypts_content_and_restores_result(): void
    {
        $this->migrate();
        config(['ai-ocr.store.enabled' => true]);
        $text = 'Private document content with enough readable text for native extraction.';
        $result = AiOcr::file($text)->manualOnly()->extract();
        $stored = Extraction::findOrFail($result->id);
        self::assertStringNotContainsString($text, $stored->getRawOriginal('result'));
        self::assertSame($text, $stored->extractionResult()->text());
    }

    public function test_studio_is_denied_without_authorization(): void
    {
        $this->get('/ai-ocr')->assertForbidden();
    }

    public function test_studio_renders_when_authorized(): void
    {
        AiOcr::auth(fn ($request) => true);
        $this->get('/ai-ocr')->assertOk()->assertSee('AI OCR Studio');
    }
}
