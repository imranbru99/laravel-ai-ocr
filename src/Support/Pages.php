<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

final class Pages
{
    /** @return list<int> */
    public static function parse(string $selection): array
    {
        if (! preg_match('/^\d+(?:-\d+)?(?:,\d+(?:-\d+)?)*$/D', $selection)) {
            throw new \InvalidArgumentException('Invalid page selection.');
        }
        $pages = [];
        foreach (explode(',', $selection) as $range) {
            $parts = array_map('intval', explode('-', $range));
            $last = $parts[1] ?? $parts[0];
            if ($parts[0] < 1 || $last < $parts[0] || $last > (int) config('ai-ocr.limits.max_pages', 200)) {
                throw new \InvalidArgumentException('Page selection exceeds configured limits.');
            }
            foreach (range($parts[0], $last) as $number) {
                $pages[$number] = true;
            }
        }
        $numbers = array_keys($pages);
        sort($numbers);

        return $numbers;
    }
}
