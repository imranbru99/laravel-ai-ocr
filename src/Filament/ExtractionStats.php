<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Filament;

use Filament\Widgets\Widget;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use ImranDevBd\AiOcr\AiOcrManager;
use ImranDevBd\AiOcr\Support\Analytics;

final class ExtractionStats extends Widget
{
    public static function canView(): bool
    {
        return config('ai-ocr.store.enabled', false) && app(AiOcrManager::class)->authorized(request());
    }

    public function render(): View
    {
        /** @var view-string $template */
        $template = 'ai-ocr::filament-widget';

        return app(Factory::class)->make($template, ['stats' => app(Analytics::class)->summary()]);
    }
}
