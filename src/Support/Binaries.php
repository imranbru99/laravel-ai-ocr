<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use ImranDevBd\AiOcr\Exceptions\ExtractionFailedException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class Binaries
{
    public function find(string $name): ?string
    {
        if (! in_array($name, ['pdftotext', 'pdftoppm', 'pdfinfo', 'pdfimages', 'prlimit', 'tesseract'], true)) {
            return null;
        }
        $configured = config('ai-ocr.binaries.'.$name);
        $directories = (array) config('ai-ocr.binaries.whitelist', ['/usr/bin', '/usr/local/bin']);
        foreach ($configured ? [$configured] : array_map(fn ($d) => rtrim($d, '/').'/'.$name, $directories) as $candidate) {
            $real = realpath($candidate);
            if ($real && is_executable($real) && in_array(dirname($real), array_filter(array_map('realpath', $directories)), true)) {
                return $real;
            }
        }

        return null;
    }

    /** @param list<string> $arguments */
    public function process(string $name, array $arguments): Process
    {
        $binary = $this->find($name);
        if (! $binary) {
            throw new ExtractionFailedException("Missing or disallowed {$name}; run php artisan ai-ocr:doctor.");
        }
        $command = [$binary, ...$arguments];
        $memory = (int) config('ai-ocr.limits.process_memory_mb', 512);
        $limiter = PHP_OS_FAMILY === 'Linux' ? $this->find('prlimit') : null;
        if ($limiter !== null && $memory > 0) {
            $command = [$limiter, '--as='.(string) ($memory * 1048576), '--', ...$command];
        }
        $process = new Process($command);
        $process->setEnv(['OMP_THREAD_LIMIT' => '1']);
        $process->setTimeout((float) config('ai-ocr.limits.process_timeout', 120));

        return $process;
    }

    /** @param list<string> $arguments */
    public function run(string $name, array $arguments): string
    {
        $process = $this->process($name, $arguments);
        $oversized = false;
        $size = 0;
        $process->run(function (string $type, string $buffer) use ($process, &$oversized, &$size): void {
            $size += strlen($buffer);
            if ($size > (int) config('ai-ocr.limits.max_size_mb', 25) * 1048576) {
                $oversized = true;
                $process->stop(0);
            }
        });
        if ($oversized) {
            throw new ExtractionFailedException('Process output exceeded the document size limit.');
        }
        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
