<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use ImranDevBd\AiHub\Facades\AIHub;
use ImranDevBd\AiOcr\Contracts\Rasterizer;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Input\Resolver;
use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Support\Temp;
use ImranDevBd\AiOcr\Tests\Fixtures\Fixtures;
use ImranDevBd\AiOcr\Tests\TestCase;

final class RemainingPipelineTest extends TestCase
{
    public function test_real_local_ocr_recovers_a_raster_without_ai(): void
    {
        $this->hubFake('{}');
        config(['ai-ocr.ocr.enabled' => true]);
        $temp = new Temp;
        $document = app(Resolver::class)->resolve(Fixtures::pdf(['This printed sentence is readable by local Tesseract without using any AI provider.']), $temp);
        $image = app(Rasterizer::class)->page($document, 1, 150, $temp);
        $result = AiOcr::file($image)->languages(['eng'])->dpi(150)->preprocess([])->minConfidence(0.6)->manualOnly()->extract();
        self::assertSame('tesseract', $result->pages()[0]->engine);
        self::assertStringContainsString('Tesseract', $result->text());
        AIHub::assertSentCount(0);
    }

    public function test_local_ocr_failure_returns_actionable_warning(): void
    {
        config(['ai-ocr.ocr.enabled' => true, 'ai-ocr.binaries.whitelist' => []]);
        $result = AiOcr::file($this->image())->manualOnly()->extract();
        self::assertStringContainsString('Local OCR unavailable', implode(' ', $result->warnings()));
    }

    public function test_page_selection_applies_before_native_pdf_work_and_rejects_bad_ranges(): void
    {
        $result = AiOcr::file(Fixtures::pdf(['First native page with enough readable document content.', 'Second native page with enough readable document content.']))->pages('2')->manualOnly()->extract();
        self::assertSame(2, $result->pages()[0]->number);
        self::assertCount(1, $result->pages());
        foreach (['2-1', '0', '9'] as $selection) {
            try {
                AiOcr::file(Fixtures::pdf(['One native page with enough readable document text.']))->pages($selection)->extract();
                self::fail('Invalid selection accepted');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_heuristic_and_ai_classification_use_registered_types(): void
    {
        $manual = AiOcr::file('invoice_number: EXAMPLE\ntotal: 10.00 Invoice document content.')->autoDetectType()->manualOnly()->extract();
        self::assertSame('invoice', $manual->documentType());
        $this->hubFake('{"type":"handwritten_note"}');
        $result = AiOcr::file('text: This is an unclassified long readable document with enough native text.')->autoDetectType()->extract();
        self::assertSame('handwritten_note', $result->documentType());
        self::assertTrue($result->usedAi());
    }

    public function test_ai_page_limit_stops_later_page_requests(): void
    {
        $this->hubFake('{"text":"Recovered first page with enough readable native text.","confidence":0.9}');
        config(['ai-ocr.ai.max_pages_per_document' => 1]);
        $result = AiOcr::file(Fixtures::pdf(['', '']))->extract();
        AIHub::assertSentCount(1);
        self::assertStringContainsString('AI page limit reached', implode(' ', $result->warnings()));
    }

    public function test_corrupt_result_cache_is_evicted_and_pipeline_recovers(): void
    {
        $text = 'Readable native document content for testing authenticated encrypted cache recovery.';
        AiOcr::file($text)->manualOnly()->cache(60)->extract();
        $store = Cache::store('array')->getStore();
        $reflection = new \ReflectionProperty($store, 'storage');
        $data = $reflection->getValue($store);
        foreach ($data as $key => $entry) {
            if (str_starts_with($key, 'ai-ocr:')) {
                Cache::put($key, 'not-an-encrypted-value', 60);
            }
        }
        self::assertFalse(AiOcr::file($text)->manualOnly()->cache(60)->extract()->fromCache());
    }

    public function test_public_support_methods_and_trace_tap_work(): void
    {
        $trace = [];
        $result = ai_ocr('Readable document content to exercise helper and trace delivery.')->withoutAi()->forJob('support-test')->tap(function ($steps) use (&$trace) {
            $trace = $steps;
        })->extract();
        self::assertSame($trace, $result->trace());
        AiOcr::extend('custom', fn ($app) => new \stdClass);
        self::assertInstanceOf(\stdClass::class, AiOcr::driver('custom'));
        $temp = new Temp;
        $path = $temp->path('estimate.pdf');
        file_put_contents($path, Fixtures::pdf(['Readable native document content used for a cost estimate.']));
        self::assertSame(1, AiOcr::estimate($path)['pages']);
        self::assertCount(2, AiOcr::files(['Readable first document content for a batch.', 'Readable second document content for a batch.'])->using('manual_only')->extract());
    }

    public function test_studio_email_and_role_authorization(): void
    {
        config(['ai-ocr.studio.emails' => 'allowed@example.com']);
        $request = Request::create('/ai-ocr');
        $request->setUserResolver(fn () => new GenericUser(['id' => 1, 'email' => 'allowed@example.com']));
        self::assertTrue(AiOcr::authorized($request));
        config(['ai-ocr.studio.emails' => null, 'ai-ocr.studio.roles' => 'reviewer']);
        $request->setUserResolver(fn () => new class(['id' => 2]) extends GenericUser
        {
            public function hasRole(string $role): bool
            {
                return $role === 'reviewer';
            }
        });
        self::assertTrue(AiOcr::authorized($request));
    }

    public function test_manual_missing_required_fields_lower_field_and_overall_confidence(): void
    {
        $result = AiOcr::file('Readable native document with no required total printed anywhere.')->as(Schema::make()->money('total')->required())->manualOnly()->extract();
        self::assertFalse($result->field('total')->validated);
        self::assertSame(0.0, $result->confidence());
    }
}
