<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Quality;

final class Validity
{
    /** @param array<array-key, string> $errors */
    public static function failed(string $path, array $errors): bool
    {
        foreach ($errors as $error) {
            if ($path === $error || str_starts_with($path, $error.'.') || str_starts_with($error, $path.'.')) {
                return true;
            }
        }

        return false;
    }
}
