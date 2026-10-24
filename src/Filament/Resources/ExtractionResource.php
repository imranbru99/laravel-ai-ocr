<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Filament\Resources;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use ImranDevBd\AiOcr\AiOcrManager;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Support\Owner;

final class ExtractionResource extends Resource
{
    public static function getModel(): string
    {
        return Extraction::class;
    }

    public static function getNavigationLabel(): string
    {
        return 'OCR extractions';
    }

    public static function canViewAny(): bool
    {
        return config('ai-ocr.store.enabled', false) && app(AiOcrManager::class)->authorized(request());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** @return Builder<Extraction> */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->guard()->user();

        return Extraction::query()->whereHas('document', fn ($q) => $q->where('owner_id', $user?->getAuthIdentifier())->where('owner_type', Owner::type($user)));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Extraction')->searchable(),
            TextColumn::make('type')->searchable()->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('confidence')->numeric(decimalPlaces: 2)->sortable(),
            TextColumn::make('cost')->numeric(decimalPlaces: 6)->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ExtractionResource\Pages\ListExtractions::route('/')];
    }
}
