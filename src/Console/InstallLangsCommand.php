<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Console;

use Illuminate\Console\Command;

final class InstallLangsCommand extends Command
{
    protected $signature = 'ai-ocr:install-langs {languages*}';

    protected $description = 'Print platform-specific Tesseract language installation commands';

    public function handle(): int
    { /** @var list<string> $languages */ $languages = (array) $this->argument('languages');
        foreach ($languages as $language) {
            if (! preg_match('/^[a-z_]+$/D', $language)) {
                $this->error('Invalid language identifier.');

                return 1;
            }
        } $this->line('Debian/Ubuntu: sudo apt-get install '.implode(' ', array_map(fn ($l) => 'tesseract-ocr-'.str_replace('_', '-', $l), $languages)));
        $this->line('macOS: brew install tesseract-lang');
        $this->line('Windows: install language .traineddata files into the Tesseract tessdata directory.');

        return 0;
    }
}
