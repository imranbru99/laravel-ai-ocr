<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use ImranDevBd\AiHub\AiHubServiceProvider;
use ImranDevBd\AiHub\Facades\AIHub;
use ImranDevBd\AiHub\Providers\ClaudeProvider;
use ImranDevBd\AiHub\Providers\GeminiProvider;
use ImranDevBd\AiHub\Support\CostCalculator;
use ImranDevBd\AiHub\Support\SettingsStore;
use Orchestra\Testbench\TestCase;

final class AiHubContractTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AiHubServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('ai-hub.settings.ui_enabled', false);
        $app['config']->set('ai-hub.logging.enabled', false);
        $app['config']->set('ai-hub.retry.enabled', false);
    }

    public function test_fake_preserves_request_inspection_and_structured_json(): void
    {
        Http::preventStrayRequests();
        AIHub::fake('{"text":"hello"}');
        // Hub fake() replaces container bindings but leaves Laravel's facade root cached.
        AIHub::clearResolvedInstance(\ImranDevBd\AiHub\AIHubManager::class);
        $schema = ['type' => 'object', 'properties' => ['text' => ['type' => 'string']], 'additionalProperties' => false];
        $response = AIHub::openai('contract-test')->prompt('transcribe')
            ->asJsonSchema($schema, 'Transcription')->recoverJson()
            ->withoutThinking()->forJob('ai-ocr')->generate();

        self::assertSame(['text' => 'hello'], $response->json());
        AIHub::assertSent(fn ($request) => $request->getProvider() === 'openai'
            && $request->getModel() === 'contract-test'
            && $request->getPrompt() === 'transcribe'
            && $request->getResponseSchema() === $schema
            && $request->hasJobTrace() === 'ai-ocr');
        AIHub::assertSentCount(1);
        Http::assertNothingSent();
    }

    public function test_data_uri_survives_openai_payload(): void
    {
        Http::preventStrayRequests();
        config(['ai-hub.providers.openai.api_key' => 'offline-test-key']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"text":"ok"}']]]])]);
        $uri = 'data:image/png;base64,'.base64_encode('payload-contract-only');
        AIHub::openai('contract-test')->failover(false)->prompt('transcribe')->image($uri)->send();
        Http::assertSent(fn ($request) => data_get($request->data(), 'messages.0.content.1.image_url.url') === $uri);
        Http::assertSentCount(1);
    }

    public function test_gemini_and_claude_translate_data_uris(): void
    {
        $uri = 'data:image/png;base64,YQ==';
        $gemini = new GeminiProvider;
        $claude = new ClaudeProvider;
        $part = (new ReflectionMethod($gemini, 'imagePart'))->invoke($gemini, $uri);
        $block = (new ReflectionMethod($claude, 'imageBlock'))->invoke($claude, $uri);
        self::assertSame(['inline_data' => ['mime_type' => 'image/png', 'data' => 'YQ==']], $part);
        self::assertSame('base64', $block['source']['type']);
        self::assertSame('image/png', $block['source']['media_type']);
        self::assertSame('YQ==', $block['source']['data']);
    }

    public function test_saved_settings_overlay_environment_config_without_exposing_keys(): void
    {
        $store = new class extends SettingsStore {
            public function all(): array
            {
                return ['providers' => ['openai' => ['api_key' => 'offline-saved-key', 'enabled' => false]]];
            }
        };
        config(['ai-hub.providers.openai.api_key' => null]);
        $store->applyToConfig();
        self::assertTrue(is_string(config('ai-hub.providers.openai.api_key')));
        self::assertFalse(config('ai-hub.providers.openai.enabled'));
    }

    public function test_unknown_pricing_is_reported_as_zero_by_hub(): void
    {
        $calculator = new CostCalculator([]);
        self::assertNull($calculator->ratesFor('openai', 'unknown-model'));
        self::assertSame(0.0, $calculator->calculate('openai', 'unknown-model', 1000, 1000));
    }
}
