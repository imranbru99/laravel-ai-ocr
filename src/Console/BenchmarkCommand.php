<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Console;

use Illuminate\Console\Command;
use ImranDevBd\AiOcr\AiOcrManager;

final class BenchmarkCommand extends Command
{
    protected $signature = 'ai-ocr:benchmark {dir} {--ai : Permit billable AI strategies}';

    protected $description = 'Measure extraction throughput on a document folder';

    public function handle(AiOcrManager $ocr): int
    {
        $directory = $this->argument('dir');
        if (! is_string($directory) || ! is_dir($directory)) {
            $this->error('Folder does not exist.');

            return 1;
        } $rows = [];
        foreach (new \DirectoryIterator($directory) as $file) {
            if (! $file->isFile()) {
                continue;
            } foreach ($this->option('ai') ? ['manual_only', 'auto', 'ai_only'] : ['manual_only'] as $strategy) {
                $start = microtime(true);
                $result = $ocr->file($file->getPathname())->using($strategy)->noCache()->safe()->extract();
                $seconds = microtime(true) - $start;
                $rows[] = [$file->getFilename(), $strategy, count($result->pages()), round($seconds, 3), round(count($result->pages()) / max(0.001, $seconds), 2), $result->cost()['usd'] ?? 0, $result->confidence(), $result->failed ? 'failed' : 'completed'];
            }
        } $this->table(['File', 'Strategy', 'Pages', 'Seconds', 'Pages/sec', 'USD', 'Confidence', 'Status'], $rows);

        return 0;
    }
}
