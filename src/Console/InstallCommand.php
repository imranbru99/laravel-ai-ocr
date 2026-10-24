<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'ai-ocr:install';

    protected $description = 'Publish configuration and migrations and check system dependencies';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'ai-ocr-config']);
        $this->call('vendor:publish', ['--tag' => 'ai-ocr-migrations']);
        $status = $this->call('ai-ocr:doctor');
        $this->info('Run migrations only if enabling storage. Install poppler-utils and tesseract-ocr-eng/tesseract-ocr-ben. Configure AI keys in AI Hub.');

        return $status;
    }
}
