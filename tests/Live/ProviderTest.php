<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Live;

use Illuminate\Support\Facades\Http;
use ImranDevBd\AiOcr\Contracts\AiExtractor;
use ImranDevBd\AiOcr\Contracts\ProviderReadiness;
use ImranDevBd\AiOcr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('live')]
final class ProviderTest extends TestCase
{
    public function test_configured_provider_extracts_synthetic_text(): void
    {
        if (! config('ai-ocr.testing.live', false)) {
            self::markTestSkipped('Set AI_OCR_LIVE=true explicitly to allow provider spending.');
        }
        if (app(ProviderReadiness::class)->ready() === []) {
            self::markTestSkipped('No configured provider is ready.');
        }
        Http::preventStrayRequests(false);
        $result = app(AiExtractor::class)->generate('Reference: SYNTHETIC-123', [
            'type' => 'object', 'properties' => ['reference' => ['type' => 'string']],
            'required' => ['reference'], 'additionalProperties' => false,
        ], []);
        self::assertSame('SYNTHETIC-123', $result['data']['reference']);
        self::assertGreaterThanOrEqual(0, $result['usage']['usd']);
    }
}
