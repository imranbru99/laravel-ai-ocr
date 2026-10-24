<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

final class Temp
{
    private readonly string $directory;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir().'/ai-ocr-'.bin2hex(random_bytes(16));
        if (! mkdir($this->directory, 0700)) {
            throw new \RuntimeException('Cannot create private OCR temporary directory.');
        }
        $path = $this->directory;
        register_shutdown_function(static fn () => self::remove($path));
    }

    public function path(string $name): string
    {
        if (basename($name) !== $name || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('Invalid temporary filename.');
        }

        return $this->directory.'/'.$name;
    }

    public function cleanup(): void
    {
        self::remove($this->directory);
    }

    private static function remove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        } foreach (new \FilesystemIterator($path) as $file) {
            if (! $file instanceof \SplFileInfo) {
                continue;
            } if ($file->isDir() && ! $file->isLink()) {
                self::remove($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        } @rmdir($path);
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
