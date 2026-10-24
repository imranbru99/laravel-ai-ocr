<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Quality;

final class GarbledDetector
{
    /** @return list<string> */
    public function reasons(string $text, ?string $script = null): array
    {
        if ($text === '') {
            return [];
        }
        $length = max(1, mb_strlen($text));
        $reasons = [];
        if (preg_match('/(?:Ã.|Â.|â€|à¦|à§){2,}/u', $text)) {
            $reasons[] = 'mojibake';
        }
        preg_match_all('/[\x{00a1}-\x{00ff}]/u', $text, $symbols);
        if (count($symbols[0]) / $length > 0.12) {
            $reasons[] = 'abnormal_latin_symbols';
        }
        if (str_contains($text, '�')) {
            $reasons[] = 'replacement_characters';
        }
        if ($script === 'bengali' && ! preg_match('/\p{Bengali}/u', $text) && preg_match('/[A-Za-z]{3}/', $text)) {
            $reasons[] = 'expected_bengali_script_missing';
        }
        if ($script === 'arabic' && ! preg_match('/\p{Arabic}/u', $text)) {
            $reasons[] = 'expected_arabic_script_missing';
        }
        // Repeated Bijoy glyph-code sequences are evidence only; Latin text alone is not.
        if (preg_match('/(?:[A-Za-z][ªÖ‡†©®µ¶][A-Za-z]?){2,}|(?:evsjv|cÖ|Kvh©|weÁ|wkÿ)/u', $text)) {
            $reasons[] = 'possible_legacy_bangla_encoding';
        }

        return array_values(array_unique([...$reasons, ...(new TextStatistics)->reasons($text)]));
    }
}
