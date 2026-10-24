<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Console;

use Illuminate\Console\Command;
use ImranDevBd\AiOcr\AiOcrManager;

final class OcrCommand extends Command
{
    protected $signature = 'ai-ocr:extract {file} {--strategy=auto} {--type=} {--lang=eng} {--json} {--no-ai}';

    protected $description = 'Extract document text and structured data';

    public function handle(AiOcrManager $ocr): int
    {
        try {
            $strategy = $this->option('strategy');
            $language = $this->option('lang');
            $type = $this->option('type');
            if (! is_string($strategy) || ! is_string($language) || ($type !== null && ! is_string($type))) {
                throw new \InvalidArgumentException('Invalid CLI options.');
            }
            $request = $ocr->file($this->argument('file'))->using($strategy)->languages(explode(',', $language));
            if ($this->option('no-ai')) {
                $request->manualOnly();
            } if ($type) {
                $request->as($type);
            } $result = $request->extract();
            $this->line($this->option('json') ? $result->toJson(JSON_PRETTY_PRINT) : $result->text());
            foreach ($result->warnings() as $warning) {
                $this->warn($warning);
            }

            return $result->failed ? 1 : 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
