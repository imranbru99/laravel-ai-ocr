<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Tests\TestCase;

final class ApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('ai-ocr.api.enabled', true);
        $app['config']->set('ai-ocr.api.middleware', ['api', 'auth', 'throttle:30,1']);
    }

    public function test_api_requires_authentication(): void
    {
        $this->postJson('/api/ai-ocr/extract')->assertUnauthorized();
    }

    public function test_api_extracts_authenticated_upload(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'email' => 'test@example.com']));
        $file = UploadedFile::fake()->createWithContent('document.txt', 'Readable uploaded document with enough text to pass the quality gate.');
        $this->postJson('/api/ai-ocr/extract', ['file' => $file, 'strategy' => 'manual_only'])->assertOk()->assertJsonPath('used_ai', false)->assertJsonPath('failed', false);
    }

    public function test_api_validates_strategy_and_limits(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));
        $this->postJson('/api/ai-ocr/extract', ['strategy' => 'execute_shell'])->assertUnprocessable()->assertJsonValidationErrors(['file', 'strategy']);
        $this->postJson('/api/ai-ocr/extract', ['file' => UploadedFile::fake()->create('huge.pdf', 30000)])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_api_does_not_allow_cross_owner_result_access(): void
    {
        $this->migrate();
        config(['ai-ocr.store.enabled' => true]);
        $this->actingAs(new GenericUser(['id' => 1]));
        $result = AiOcr::file('Private readable document that belongs to the first user.')->manualOnly()->extract();
        $this->actingAs(new GenericUser(['id' => 2]));
        $this->getJson('/api/ai-ocr/extractions/'.$result->id)->assertNotFound();
    }
}
