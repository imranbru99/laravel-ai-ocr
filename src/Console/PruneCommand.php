<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Console;

use Illuminate\Console\Command;
use ImranDevBd\AiOcr\Models\Document;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Support\Sources;

final class PruneCommand extends Command
{
    protected $signature = 'ai-ocr:prune {--days=}';

    protected $description = 'Delete expired extraction records';

    public function handle(): int
    {
        $days = filter_var($this->option('days') ?? config('ai-ocr.store.prune_days', 90), FILTER_VALIDATE_INT);
        if ($days === false || $days < 1) {
            $this->error('Days must be a positive integer.');

            return 1;
        } $count = Extraction::query()->where('created_at', '<', now()->subDays($days))->delete();
        foreach (Document::query()->whereDoesntHave('extractions')->where('created_at', '<', now()->subDays($days))->cursor() as $document) {
            app(Sources::class)->forget($document);
            $document->delete();
        }
        $this->info('Pruned '.$count.' extractions.');

        return 0;
    }
}
