<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr;

use Illuminate\Support\ServiceProvider;

final class AiOcrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-ocr.php', 'ai-ocr');
        $this->app->bind(Contracts\ProviderReadiness::class, Support\ProviderReadiness::class);
        $this->app->bind(Contracts\NativeExtractor::class, Drivers\Native\Extractor::class);
        $this->app->bind(Contracts\OcrEngine::class, Drivers\Ocr\Tesseract::class);
        $this->app->bind(Contracts\Rasterizer::class, Drivers\Ocr\Rasterizer::class);
        $this->app->bind(Contracts\AiExtractor::class, Drivers\Ai\AiHubDriver::class);
        $this->app->singleton(Types\TypeRegistry::class);
        $this->app->singleton(AiOcrManager::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/ai-ocr.php' => config_path('ai-ocr.php')], 'ai-ocr-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'ai-ocr-migrations');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/ai-ocr')], 'ai-ocr-views');
        $this->publishes([__DIR__.'/../resources/lang' => lang_path('vendor/ai-ocr')], 'ai-ocr-lang');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-ocr');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'ai-ocr');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        if ($this->app->runningInConsole()) {
            $this->commands([Console\OcrCommand::class, Console\DoctorCommand::class, Console\InstallCommand::class, Console\TypesCommand::class, Console\PruneCommand::class, Console\EstimateCommand::class, Console\BenchmarkCommand::class, Console\InstallLangsCommand::class]);
        }
    }
}
