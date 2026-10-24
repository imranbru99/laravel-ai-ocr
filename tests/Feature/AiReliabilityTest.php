<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use ImranDevBd\AiHub\Facades\AIHub;
use ImranDevBd\AiHub\Jobs\TrackAiUsageJob;
use ImranDevBd\AiOcr\Exceptions\AiUnavailableException;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Tests\TestCase;

final class AiReliabilityTest extends TestCase
{
    public function test_semantic_retry_repairs_invalid_schema_response(): void
    {
        $this->hubFake(['{"text":12,"confidence":0.8}', '{"text":"Corrected recovered document content with enough readable text.","confidence":0.95}']);
        $result = AiOcr::file($this->image())->extract();
        self::assertTrue($result->usedAi());
        AIHub::assertSentCount(2);
        self::assertGreaterThan(30, $result->cost()['tokens']);
    }

    public function test_ai_verify_records_ai_consultation_without_replacing_native_text(): void
    {
        $this->hubFake('{"text":"Different AI transcription of this readable document content.","confidence":0.9}');
        $original = 'Original readable document content containing enough text for the quality gate.';
        $result = AiOcr::file($original)->using('ai_verify')->extract();
        self::assertTrue($result->usedAi());
        self::assertSame($original, $result->text());
        self::assertSame('native', $result->pages()[0]->engine);
    }

    public function test_logging_removes_content_but_preserves_spend_metrics(): void
    {
        $this->hubFake('{"text":"Private recovered document content must not appear in logs.","confidence":0.9}');
        config(['ai-hub.logging.enabled' => true, 'ai-hub.logging.async' => 'queue']);
        Bus::fake([TrackAiUsageJob::class]);
        AiOcr::file($this->image())->extract();
        Bus::assertDispatched(TrackAiUsageJob::class, fn ($job) => $job->payload['content'] === '' && $job->payload['total_tokens'] > 0);
    }

    public function test_provider_failure_degrades_and_require_ai_throws(): void
    {
        $this->hubFake('{}');
        config(['ai-hub.providers' => []]);
        $partial = AiOcr::file($this->image())->extract();
        self::assertFalse($partial->failed);
        $this->expectException(AiUnavailableException::class);
        AiOcr::file($this->image())->requireAi()->extract();
    }

    public function test_cache_contains_encrypted_results(): void
    {
        $text = 'Private native readable document content should never be cached as plaintext.';
        AiOcr::file($text)->manualOnly()->cache(60)->extract();
        $store = app('cache')->store('array')->getStore();
        self::assertStringNotContainsString($text, serialize($store));
    }
}
