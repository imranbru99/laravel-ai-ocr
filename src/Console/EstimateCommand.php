<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Console;

use Illuminate\Console\Command;
use ImranDevBd\AiOcr\Support\Estimator;

final class EstimateCommand extends Command
{
    protected $signature = 'ai-ocr:estimate {file}';

    protected $description = 'Estimate worst-case AI token cost from configured Hub pricing';

    public function handle(Estimator $estimator): int
    {
        $this->line(json_encode($estimator->estimate($this->argument('file')), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return 0;
    }
}
