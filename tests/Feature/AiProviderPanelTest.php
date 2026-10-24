<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ImranDevBd\AiHub\Facades\AIHub;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Tests\TestCase;

final class AiProviderPanelTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('ai-hub.settings.ui_enabled', true);
        $app['config']->set('ai-hub.settings.route_prefix', 'model-settings');
        $app['config']->set('ai-hub.settings.database', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('ai_hub_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function test_visual_panel_has_password_fields_and_uses_the_configured_hub_routes_without_exposing_keys(): void
    {
        AiOcr::auth(fn () => true);
        config(['ai-hub.providers.openai.api_key' => 'private-test-secret', 'ai-hub.defaults.openai' => 'model-<script>']);
        $response = $this->get('/ai-ocr')->assertOk()
            ->assertSee('AI Providers')->assertSee('Connect your AI providers')
            ->assertSee('type="password"', false)
            ->assertViewHas('aiPanel', fn ($panel) => str_ends_with($panel['save_url'], '/model-settings/api/provider'))
            ->assertSee('model-&lt;script&gt;', false)
            ->assertDontSee('private-test-secret', false);
        self::assertStringNotContainsString('value="private-test-secret"', $response->getContent());
        $this->getJson('/ai-ocr/providers')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('providers.0.configured', true)
            ->assertDontSee('private-test-secret', false);
    }

    public function test_provider_status_respects_disabled_ai_and_allowlists_without_making_inference_calls(): void
    {
        AiOcr::auth(fn () => true);
        config(['ai-ocr.ai.enabled' => false, 'ai-ocr.ai.allowed_providers' => ['gemini'], 'ai-hub.providers.openai.api_key' => 'offline-secret', 'ai-hub.providers.gemini.enabled' => false]);
        $this->getJson('/ai-ocr/providers')->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('providers.0.status', 'Blocked for OCR')
            ->assertJsonPath('providers.1.status', 'Disabled');
    }

    public function test_provider_status_requires_ocr_studio_authorization(): void
    {
        $this->getJson('/ai-ocr/providers')->assertForbidden();
    }

    public function test_saving_a_provider_uses_hub_storage_and_updates_ocr_readiness_without_exposing_the_key(): void
    {
        AiOcr::auth(fn () => true);
        AIHub::auth(fn () => true);
        $this->postJson('/model-settings/api/provider', ['provider' => 'openai', 'api_key' => 'synthetic-private-123', 'model' => 'offline-model', 'enabled' => true, 'make_default' => true])
            ->assertOk()->assertJsonPath('success', true)->assertDontSee('synthetic-private-123', false);
        $this->getJson('/ai-ocr/providers')->assertOk()
            ->assertJsonPath('default', 'openai')->assertJsonPath('providers.0.configured', true)
            ->assertJsonPath('providers.0.model', 'offline-model');
        $payload = json_decode(DB::table('ai_hub_settings')->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('synthetic-private-123', $payload['providers']['openai']['api_key']);
        self::assertFalse(Schema::hasTable('ai_ocr_provider_keys'));
    }

    public function test_empty_key_on_model_update_preserves_the_saved_credential(): void
    {
        AIHub::auth(fn () => true);
        $this->postJson('/model-settings/api/provider', ['provider' => 'gemini', 'api_key' => 'synthetic-preserved-key', 'model' => 'model-one'])->assertOk();
        $this->postJson('/model-settings/api/provider', ['provider' => 'gemini', 'model' => 'model-two'])->assertOk();
        $payload = json_decode(DB::table('ai_hub_settings')->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('synthetic-preserved-key', $payload['providers']['gemini']['api_key']);
        self::assertSame('model-two', $payload['defaults']['gemini']);
    }

    public function test_ocr_authorization_does_not_grant_permission_to_modify_hub_settings(): void
    {
        AiOcr::auth(fn () => true);
        AIHub::auth(fn () => false);
        $this->get('/ai-ocr')->assertOk();
        $this->postJson('/model-settings/api/provider', ['provider' => 'openai', 'api_key' => 'must-not-save'])->assertForbidden();
        self::assertSame(0, DB::table('ai_hub_settings')->count());
    }

    public function test_disabled_hub_ui_explains_environment_setup_instead_of_exposing_a_broken_save_action(): void
    {
        AiOcr::auth(fn () => true);
        config(['ai-hub.settings.ui_enabled' => false]);
        $this->get('/ai-ocr')->assertOk()->assertSee('Visual key management is unavailable.');
        $this->getJson('/ai-ocr/providers')->assertOk()->assertJsonPath('save_url', null)->assertJsonPath('hub_url', null);
    }
}
