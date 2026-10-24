<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use Filament\Contracts\Plugin;
use ImranDevBd\AiOcr\Filament\AiOcrPlugin;
use ImranDevBd\AiOcr\Filament\ExtractionStats;
use ImranDevBd\AiOcr\Filament\Resources\ExtractionResource;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Tests\TestCase;

final class FilamentTest extends TestCase
{
    public function test_optional_plugin_resource_and_widget_load_with_installed_filament(): void
    {
        if (! interface_exists(Plugin::class)) {
            self::markTestSkipped('Optional Filament is not installed.');
        }
        self::assertSame('ai-ocr', AiOcrPlugin::make()->getId());
        self::assertSame(Extraction::class, ExtractionResource::getModel());
        self::assertFalse(ExtractionResource::canCreate());
        self::assertFalse(ExtractionStats::canView());
    }
}
