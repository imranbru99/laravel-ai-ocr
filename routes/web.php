<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ImranDevBd\AiOcr\Http\Controllers\ExtractionController;
use ImranDevBd\AiOcr\Http\Controllers\SchemaController;
use ImranDevBd\AiOcr\Http\Controllers\StudioController;
use ImranDevBd\AiOcr\Http\Middleware\AuthorizeStudio;

if (config('ai-ocr.studio.enabled') ?? app()->environment('local')) {
    Route::prefix(config('ai-ocr.studio.path', 'ai-ocr'))->middleware([...config('ai-ocr.studio.middleware', ['web']), AuthorizeStudio::class])->group(function (): void {
        Route::get('/', [StudioController::class, 'index'])->name('ai-ocr.studio');
        Route::get('/extract/{id}', [ExtractionController::class, 'show']);
        Route::post('/extractions/{id}/rerun', [ExtractionController::class, 'rerun']);
        Route::post('/extractions/{id}/review', [ExtractionController::class, 'review']);
        Route::get('/extractions/{id}', [ExtractionController::class, 'show'])->name('ai-ocr.studio.result');
        Route::get('/schemas', [SchemaController::class, 'index'])->name('ai-ocr.schemas');
        Route::post('/schemas', [SchemaController::class, 'store']);
        Route::delete('/schemas/{id}', [SchemaController::class, 'destroy']);
        Route::post('/extract', [ExtractionController::class, 'store'])->middleware('throttle:10,1')->name('ai-ocr.studio.extract');
    });
}
if (config('ai-ocr.api.enabled', false)) {
    Route::prefix(config('ai-ocr.api.prefix', 'api/ai-ocr'))->middleware(config('ai-ocr.api.middleware', ['api', 'auth:sanctum', 'throttle:30,1']))->group(function (): void {
        Route::get('/extract/{id}', [ExtractionController::class, 'show']);
        Route::post('/extract', [ExtractionController::class, 'store']);
        Route::post('/extractions/{id}/rerun', [ExtractionController::class, 'rerun']);
        Route::post('/extractions/{id}/review', [ExtractionController::class, 'review']);
        Route::get('/extractions/{id}', [ExtractionController::class, 'show']);
    });
}
