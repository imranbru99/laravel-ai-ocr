<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Drivers\Native;

use ImranDevBd\AiOcr\DTO\Page;

/** @internal Conservative fallback for simple, unencrypted PDFs with literal text operators. */
final class MinimalPdf
{
    /** @return list<Page> */
    public function extract(string $bytes, int $count): array
    {
        preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)endobj/s', $bytes, $matches, PREG_SET_ORDER);
        $objects = [];
        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }
        $pages = [];
        foreach ($objects as $object) {
            if (! preg_match('#/Type\s*/Page\b#', $object)) {
                continue;
            }
            $text = '';
            if (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R/', $object, $reference)) {
                $content = $objects[(int) $reference[1]] ?? '';
                if (preg_match('/stream\r?\n(.*?)\r?\nendstream/s', $content, $stream)) {
                    $decoded = str_contains($content, '/FlateDecode') ? @gzuncompress($stream[1], (int) (config('ai-ocr.limits.max_size_mb', 25) * 1048576)) : $stream[1];
                    if (is_string($decoded)) {
                        preg_match_all('/\(((?:\\\\.|[^()\\\\])*)\)\s*Tj/s', $decoded, $texts);
                        $text = implode("\n", array_map(fn ($value) => strtr($value, ['\\(' => '(', '\\)' => ')', '\\n' => "\n", '\\\\' => '\\']), $texts[1]));
                        if (! mb_check_encoding($text, 'UTF-8')) {
                            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
                        }
                    }
                }
            }
            // A simple literal extraction cannot establish reliable glyph mapping or layout.
            $pages[] = new Page(count($pages) + 1, $text, confidence: 0.5);
        }
        while (count($pages) < $count) {
            $pages[] = new Page(count($pages) + 1, '', confidence: 0);
        }

        return array_slice($pages, 0, $count);
    }
}
