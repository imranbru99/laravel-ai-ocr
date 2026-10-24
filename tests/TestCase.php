<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests;

use Filament\FilamentServiceProvider;
use Illuminate\Support\Facades\Http;
use ImranDevBd\AiHub\AIHubManager;
use ImranDevBd\AiHub\AiHubServiceProvider;
use ImranDevBd\AiHub\Facades\AIHub;
use ImranDevBd\AiOcr\AiOcrServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    public function ignorePackageDiscoveriesFrom(): array
    {
        return [];
    }

    protected function getPackageProviders($app): array
    {
        $providers = [AiHubServiceProvider::class, AiOcrServiceProvider::class];
        if (class_exists(FilamentServiceProvider::class)) {
            $installed = json_decode(file_get_contents(__DIR__.'/../vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($installed['packages'] as $package) {
                foreach ($package['extra']['laravel']['providers'] ?? [] as $provider) {
                    $providers[] = $provider;
                }
            }
        }

        return array_values(array_unique($providers));
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('ai-hub.logging.enabled', false);
        $app['config']->set('ai-hub.settings.ui_enabled', false);
        $app['config']->set('ai-hub.retry.enabled', false);
        $app['config']->set('ai-ocr.ai.cache_ttl', 0);
        $app['config']->set('ai-ocr.ocr.enabled', false);
        $app['config']->set('ai-ocr.studio.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function hubFake(string|array $response): void
    {
        AIHub::fake($response);
        AIHub::clearResolvedInstance(AIHubManager::class);
        config(['ai-hub.providers.openai.api_key' => 'offline-key', 'ai-hub.default' => 'openai', 'ai-hub.defaults.openai' => 'test-model', 'ai-hub.pricing.openai.test-model' => ['input' => 0.1, 'output' => 0.1]]);
    }

    protected function migrate(): void
    {
        (require __DIR__.'/../database/migrations/2026_01_01_000000_create_ai_ocr_tables.php')->up();
    }

    protected function image(): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0)).$chunk('IDAT', gzcompress("\x00\xff\xff\xff\xff")).$chunk('IEND', '');
    }
}
