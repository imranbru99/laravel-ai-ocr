<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Filament;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Contracts\Routing\UrlGenerator;
use ImranDevBd\AiOcr\AiOcrManager;

final class AiOcrPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'ai-ocr';
    }

    public function register(Panel $panel): void
    {
        if (config('ai-ocr.filament.enabled', true) && config('ai-ocr.store.enabled', false)) {
            $panel->resources([Resources\ExtractionResource::class])->widgets([ExtractionStats::class]);
        }
        if (config('ai-ocr.filament.enabled', true) && config('ai-ocr.studio.enabled', app()->environment('local'))) {
            $panel->navigationItems([
                NavigationItem::make('AI OCR')->url(app(UrlGenerator::class)->to((string) config('ai-ocr.studio.path', 'ai-ocr')), shouldOpenInNewTab: (bool) config('ai-ocr.filament.new_tab', true))->visible(fn (): bool => app(AiOcrManager::class)->authorized(request())),
            ]);
        }
    }

    public function boot(Panel $panel): void {}
}
