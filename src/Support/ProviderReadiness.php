<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use ImranDevBd\AiHub\Support\ProviderCatalog;

final class ProviderReadiness implements \ImranDevBd\AiOcr\Contracts\ProviderReadiness
{
    /** @return list<string> */
    public function ready(): array
    {
        return array_values(array_filter(ProviderCatalog::keys(), static function (string $provider): bool {
            return config("ai-hub.providers.{$provider}.enabled", true) !== false && ($provider === 'ollama' || trim((string) config("ai-hub.providers.{$provider}.api_key", '')) !== '');
        }));
    }
}
