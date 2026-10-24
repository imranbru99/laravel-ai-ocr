<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Normalizers;

final class Passport
{
    /** @return array<string, string> */
    public static function parse(string $mrz): array
    {
        $lines = preg_split('/\s+/', trim($mrz)) ?: [];
        if (count($lines) !== 2 || strlen($lines[0]) !== 44 || strlen($lines[1]) !== 44 || ! str_starts_with($lines[0], 'P<')) {
            return [];
        }
        [$first,$second] = $lines;
        if (! Normalizer::mrzChecksum(substr($second, 0, 9), $second[9]) || ! Normalizer::mrzChecksum(substr($second, 13, 6), $second[19]) || ! Normalizer::mrzChecksum(substr($second, 21, 6), $second[27])) {
            return [];
        }
        $birth = substr($second, 13, 6);
        $expiry = substr($second, 21, 6);
        $year = (int) substr($birth, 0, 2);
        $century = $year > (int) date('y') ? '19' : '20';
        $date = fn (string $value, string $prefix) => Normalizer::date($prefix.substr($value, 0, 2).'-'.substr($value, 2, 2).'-'.substr($value, 4, 2));

        return array_filter(['passport_number' => rtrim(substr($second, 0, 9), '<'), 'name' => trim(preg_replace('/\s+/', ' ', str_replace('<', ' ', substr($first, 5))) ?? ''), 'nationality' => substr($second, 10, 3), 'birth_date' => $date($birth, $century), 'expiry_date' => $date($expiry, '20')], fn ($value) => is_string($value) && $value !== '');
    }
}
