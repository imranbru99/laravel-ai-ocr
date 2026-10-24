<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use Filament\Contracts\Plugin;
use ImranDevBd\AiOcr\Contracts\ProviderReadiness;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser;

final class Doctor
{
    public function __construct(private readonly Binaries $binaries, private readonly ProviderReadiness $readiness) {}

    /** @return array<string, mixed> */
    public function report(): array
    {
        $binaries = [];
        foreach (['pdftotext', 'pdfinfo', 'pdftoppm', 'tesseract'] as $name) {
            $binaries[$name] = $this->binaries->find($name) ?? 'missing: install poppler-utils / tesseract-ocr or configure an allowed binary directory';
        } $languages = [];
        if ($this->binaries->find('tesseract')) {
            try {
                $languages = array_values(array_filter(explode("\n", trim($this->binaries->run('tesseract', ['--list-langs']))), fn ($s) => ! str_contains($s, 'List of')));
            } catch (\Throwable) {
                $languages = ['unavailable'];
            }
        }

        return ['binaries' => $binaries, 'languages' => $languages, 'extensions' => ['imagick' => extension_loaded('imagick'), 'gd' => extension_loaded('gd'), 'zip' => extension_loaded('zip'), 'curl' => extension_loaded('curl')], 'optional' => ['smalot/pdfparser' => class_exists(Parser::class), 'phpoffice/phpspreadsheet' => class_exists(IOFactory::class), 'phpoffice/phpword' => class_exists(\PhpOffice\PhpWord\IOFactory::class), 'filament/filament' => interface_exists(Plugin::class)], 'ready_providers' => $this->readiness->ready(), 'default_provider' => config('ai-hub.default'), 'limits' => config('ai-ocr.limits'), 'queue' => config('queue.default'), 'critical_failure' => ! extension_loaded('fileinfo') || ! extension_loaded('mbstring')];
    }
}
