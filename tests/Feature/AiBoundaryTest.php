<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use ImranDevBd\AiHub\Facades\AIHub;
use ImranDevBd\AiHub\Jobs\TrackAiUsageJob;
use ImranDevBd\AiHub\Support\BudgetGuard;
use ImranDevBd\AiOcr\Drivers\Ai\AiHubDriver;
use ImranDevBd\AiOcr\Exceptions\AiUnavailableException;
use ImranDevBd\AiOcr\Exceptions\BudgetExceededException;
use ImranDevBd\AiOcr\Tests\TestCase;

final class AiBoundaryTest extends TestCase
{
    private function schema(): array
    {
        return ['type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'required' => ['text'], 'additionalProperties' => false];
    }

    public function test_ai_disabled_and_provider_allowlist_are_enforced_before_requests(): void
    {
        $this->hubFake('{"text":"hello"}');
        $driver = app(AiHubDriver::class);
        foreach ([['ai-ocr.ai.enabled' => false], ['ai-ocr.ai.enabled' => true, 'ai-ocr.ai.allowed_providers' => []]] as $settings) {
            config($settings);
            try {
                $driver->generate('document', $this->schema(), []);
                self::fail('AI request should have been blocked');
            } catch (AiUnavailableException) {
                self::assertTrue(true);
            }
        }
        AIHub::assertSentCount(0);
    }

    public function test_strong_model_is_used_after_semantic_repair_attempt_fails(): void
    {
        $this->hubFake(['{"text":1}', '{"text":2}', '{"text":"Recovered"}']);
        config(['ai-ocr.ai.models.strong' => 'strong-model', 'ai-hub.pricing.openai.strong-model' => ['input' => 0.2, 'output' => 0.2]]);
        $result = app(AiHubDriver::class)->generate('document', $this->schema(), ['provider' => 'openai']);
        self::assertSame('strong-model', $result['model']);
        AIHub::assertSentCount(3);
    }

    public function test_budget_guard_breach_uses_package_budget_exception(): void
    {
        $this->hubFake('{}');
        app()->instance(BudgetGuard::class, new class extends BudgetGuard
        {
            public function evaluate(?string $provider = null, ?string $job = null): array
            {
                return [['level' => 'breach', 'key' => 'monthly', 'message' => 'Monthly cap reached']];
            }
        });
        $this->expectException(BudgetExceededException::class);
        app(AiHubDriver::class)->generate('document', $this->schema(), []);
    }

    public function test_private_hub_cache_returns_zero_new_usage(): void
    {
        $this->hubFake('{"text":"cached private answer"}');
        $driver = app(AiHubDriver::class);
        $first = $driver->generate('document', $this->schema(), ['cache' => 60]);
        $second = $driver->generate('document', $this->schema(), ['cache' => 60]);
        self::assertSame($first['data'], $second['data']);
        self::assertSame(0.0, $second['usage']['usd']);
        self::assertSame(0, $second['usage']['tokens']);
        AIHub::assertSentCount(1);
    }

    public function test_corrupt_or_wrongly_typed_private_cache_is_ignored(): void
    {
        $this->hubFake(array_fill(0, 5, '{"text":"recovered"}'));
        $driver = app(AiHubDriver::class);
        $driver->generate('document', $this->schema(), ['cache' => 60]);
        $store = Cache::store('array')->getStore();
        $reflection = new \ReflectionProperty($store, 'storage');
        foreach (['broken', encrypt('wrong shape')] as $broken) {
            foreach ($reflection->getValue($store) as $key => $entry) {
                if (str_starts_with($key, 'ai-ocr:hub:')) {
                    Cache::put($key, $broken, 60);
                }
            }
            self::assertSame('recovered', $driver->generate('document', $this->schema(), ['cache' => 60])['data']['text']);
        }
    }

    public function test_explicit_content_logging_opt_in_uses_hub_logging(): void
    {
        $this->hubFake('{"text":"explicit log opt-in"}');
        config(['ai-ocr.privacy.log_content' => true, 'ai-hub.logging.enabled' => true, 'ai-hub.logging.async' => 'queue']);
        Bus::fake([TrackAiUsageJob::class]);
        app(AiHubDriver::class)->generate('document', $this->schema(), []);
        Bus::assertDispatched(TrackAiUsageJob::class, fn ($job) => str_contains($job->payload['content'], 'explicit log opt-in'));
    }

    public function test_public_host_named_like_private_ip_is_not_a_local_provider(): void
    {
        $this->hubFake('{}');
        config(['ai-hub.providers.ollama.base_url' => 'https://10.example.com/v1']);
        $this->expectException(AiUnavailableException::class);
        app(AiHubDriver::class)->generate('document', $this->schema(), ['type' => 'bd_nid']);
    }
}
