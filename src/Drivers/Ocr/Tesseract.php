<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Drivers\Ocr;

use ImranDevBd\AiOcr\Contracts\OcrEngine;
use ImranDevBd\AiOcr\DTO\Page;
use ImranDevBd\AiOcr\Support\Binaries;
use ImranDevBd\AiOcr\Support\ProcessPool;
use Symfony\Component\Process\Exception\ExceptionInterface;

final class Tesseract implements OcrEngine
{
    public function __construct(private readonly Binaries $binaries) {}

    /** @param list<string> $languages */
    public function recognize(string $path, int $number, array $languages): Page
    {
        if (in_array('auto', $languages, true)) {
            try {
                $orientation = $this->binaries->run('tesseract', [$path, 'stdout', '--psm', '0']);
                preg_match('/Script:\s*(\w+)/', $orientation, $script);
                $languages = [match ($script[1] ?? '') {
                    'Bengali' => 'ben', 'Arabic' => 'ara', 'Devanagari' => 'hin', 'Han' => 'chi_sim', default => 'eng'
                }];
            } catch (ExceptionInterface) {
                $languages = ['eng'];
            }
        }
        foreach ($languages as $language) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/D', $language)) {
                throw new \InvalidArgumentException('Invalid Tesseract language identifier.');
            }
        }
        $output = $this->binaries->run('tesseract', [$path, 'stdout', '-l', implode('+', $languages ?: ['eng']), '--psm', (string) config('ai-ocr.ocr.psm', 3), '--oem', (string) config('ai-ocr.ocr.oem', 1), 'tsv']);

        return $this->parse($output, $number);
    }

    /**
     * @param  array<int,string>  $paths
     * @param  list<string>  $languages
     * @return array<int,Page|string>
     */
    public function recognizeMany(array $paths, array $languages, int $concurrency): array
    {
        if (in_array('auto', $languages, true)) {
            $results = [];
            foreach ($paths as $number => $path) {
                try {
                    $results[$number] = $this->recognize($path, $number, $languages);
                } catch (\Throwable $error) {
                    $results[$number] = $error->getMessage();
                }
            }

            return $results;
        }
        foreach ($languages as $language) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/D', $language)) {
                throw new \InvalidArgumentException('Invalid OCR language.');
            }
        }
        $commands = [];
        foreach ($paths as $number => $path) {
            $commands[$number] = ['binary' => 'tesseract', 'args' => [$path, 'stdout', '-l', implode('+', $languages ?: ['eng']), '--psm', (string) config('ai-ocr.ocr.psm', 3), '--oem', (string) config('ai-ocr.ocr.oem', 1), 'tsv']];
        }
        $results = [];
        foreach (app(ProcessPool::class)->run($commands, $concurrency) as $number => $result) {
            $results[$number] = $result['error'] ?? $this->parse($result['output'] ?? '', $number);
        }

        return $results;
    }

    public function parse(string $tsv, int $number): Page
    {
        $words = [];
        $lines = [];
        $confidence = [];
        foreach (array_slice(explode("\n", trim($tsv)), 1) as $line) {
            $row = explode("\t", $line, 12);
            if (count($row) < 12 || $row[0] !== '5' || trim($row[11]) === '') {
                continue;
            } $key = $row[2].'-'.$row[3].'-'.$row[4];
            $lines[$key][] = $row[11];
            $conf = max(0, (float) $row[10]) / 100;
            $confidence[] = $conf;
            $words[] = ['text' => $row[11], 'confidence' => $conf, 'bbox' => ['x' => (int) $row[6], 'y' => (int) $row[7], 'width' => (int) $row[8], 'height' => (int) $row[9]]];
        }

        return new Page($number, implode("\n", array_map(fn ($r) => implode(' ', $r), array_values($lines))), 'tesseract', $confidence ? array_sum($confidence) / count($confidence) : 0.0, $words);
    }
}
