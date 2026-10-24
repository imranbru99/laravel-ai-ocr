<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use Illuminate\Support\Facades\Route;
use ImranDevBd\AiHub\Support\ProviderCatalog;
use ImranDevBd\AiOcr\Contracts\ProviderReadiness;

final class AiProviderPanel
{
    public function __construct(private readonly ProviderReadiness $readiness) {}

    /** @return array{enabled: bool, default: string, providers: list<array{key: string, label: string, model: string, configured: bool, enabled: bool, allowed: bool, is_default: bool, status: string}>, hub_url: ?string, save_url: ?string} */
    public function data(): array
    {
        $ready = $this->readiness->ready();
        $default = (string) config('ai-hub.default', 'openai');
        $allowed = config('ai-ocr.ai.allowed_providers');
        $providers = [];
        foreach (ProviderCatalog::keys() as $key) {
            $enabled = config('ai-hub.providers.'.$key.'.enabled', true) !== false;
            $configured = $key === 'ollama' || trim((string) config('ai-hub.providers.'.$key.'.api_key', '')) !== '';
            $permitted = ! is_array($allowed) || in_array($key, $allowed, true);
            $status = ! $enabled ? 'Disabled' : (! $configured ? 'Needs API key' : (! $permitted ? 'Blocked for OCR' : (in_array($key, $ready, true) ? 'Configured' : 'Not ready')));
            $providers[] = ['key' => $key, 'label' => ProviderCatalog::label($key), 'model' => (string) config('ai-hub.defaults.'.$key, ''), 'configured' => $configured, 'enabled' => $enabled, 'allowed' => $permitted, 'is_default' => $default === $key, 'status' => $status];
        }
        $hubEnabled = (bool) config('ai-hub.settings.ui_enabled', true);

        return [
            'enabled' => (bool) config('ai-ocr.ai.enabled', true) && (bool) config('ai-ocr.ai.allow', true) && config('ai-ocr.ai.mode') !== 'never',
            'default' => $default,
            'providers' => $providers,
            'hub_url' => $hubEnabled && Route::has('ai-hub.index') ? route('ai-hub.index') : null,
            'save_url' => $hubEnabled && Route::has('ai-hub.api.provider') ? route('ai-hub.api.provider') : null,
        ];
    }
}
