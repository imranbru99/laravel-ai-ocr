<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Filament\Resources\ExtractionResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Routing\UrlGenerator;
use ImranDevBd\AiOcr\Filament\Resources\ExtractionResource;

final class ListExtractions extends ListRecords
{
    protected static string $resource = ExtractionResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [Action::make('upload')->label('Upload document')->url(app(UrlGenerator::class)->to((string) config('ai-ocr.studio.path', 'ai-ocr')))];
    }
}
