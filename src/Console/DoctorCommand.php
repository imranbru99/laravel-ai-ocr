<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Console;

use Illuminate\Console\Command;
use ImranDevBd\AiOcr\Support\Doctor;

final class DoctorCommand extends Command
{
    protected $signature = 'ai-ocr:doctor';

    protected $description = 'Check OCR binaries, languages, optional dependencies and AI readiness';

    public function handle(Doctor $doctor): int
    {
        $report = $doctor->report();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $report['critical_failure'] ? 1 : 0;
    }
}
