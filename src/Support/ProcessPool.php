<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

final class ProcessPool
{
    public function __construct(private readonly Binaries $binaries) {}

    /**
     * @param  array<int,array{binary:string,args:list<string>}>  $commands
     * @return array<int,array{output:?string,error:?string}>
     */
    public function run(array $commands, int $concurrency): array
    {
        $results = [];
        foreach (array_chunk($commands, max(1, $concurrency), true) as $chunk) {
            $running = [];
            $sizes = [];
            $tooLarge = [];
            try {
                foreach ($chunk as $key => $command) {
                    try {
                        $process = $this->binaries->process($command['binary'], $command['args']);
                        $running[$key] = $process;
                        $sizes[$key] = 0;
                        $process->start(function (string $type, string $buffer) use ($process, $key, &$sizes, &$tooLarge): void {
                            $sizes[$key] += strlen($buffer);
                            if ($sizes[$key] > config('ai-ocr.limits.max_size_mb', 25) * 1048576) {
                                $tooLarge[$key] = true;
                                $process->stop(0);
                            }
                        });
                    } catch (\Throwable $error) {
                        $results[$key] = ['output' => null, 'error' => $error->getMessage()];
                    }
                }
                foreach ($running as $key => $process) {
                    try {
                        $process->wait();
                        $results[$key] = isset($tooLarge[$key]) ? ['output' => null, 'error' => 'OCR output exceeded size limit.'] : ($process->isSuccessful() ? ['output' => $process->getOutput(), 'error' => null] : ['output' => null, 'error' => 'Tesseract process failed; verify installed language packs.']);
                    } catch (\Throwable $error) {
                        $results[$key] = ['output' => null, 'error' => $error->getMessage()];
                    }
                }
            } finally {
                foreach ($running as $process) {
                    if ($process->isRunning()) {
                        $process->stop(0);
                    }
                }
            }
        }

        return $results;
    }
}
