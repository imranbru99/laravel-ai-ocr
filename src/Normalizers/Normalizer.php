<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Normalizers;

final class Normalizer
{
    public static function text(string $text): string
    {
        $text = strtr($text, array_combine(preg_split('//u', '০১২৩৪৫৬৭৮৯٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY) ?: [], str_split('012345678901234567890123456789')));
        $text = strtr($text, ['ﬁ' => 'fi', 'ﬂ' => 'fl', "\r\n" => "\n", "\r" => "\n", "\u{00a0}" => ' ']);
        $text = preg_replace('/(\pL)-\n(\pL)/u', '$1$2', $text) ?? $text;

        return trim(class_exists(\Normalizer::class) ? (\Normalizer::normalize($text) ?: $text) : $text);
    }

    public static function money(string $value, int $precision = 2): ?int
    {
        if ($precision < 0 || $precision > 4) {
            throw new \InvalidArgumentException('Money precision must be between zero and four.');
        }
        $original = self::text($value);
        preg_match_all('/[0-9]+(?:[,.][0-9]+| [0-9]{3})*/', $original, $numbers);
        if (count($numbers[0]) !== 1) {
            return null;
        }
        $negative = str_contains($original, '-') || str_contains($original, '(');
        $value = str_replace(' ', '', $numbers[0][0]);
        $dot = strrpos($value, '.');
        $comma = strrpos($value, ',');
        $separator = $dot !== false && $comma !== false ? ($dot > $comma ? '.' : ',') : ($dot !== false ? '.' : ($comma !== false && strlen($value) - $comma - 1 <= $precision ? ',' : null));
        if ($separator !== null) {
            $position = (int) strrpos($value, $separator);
            $whole = preg_replace('/\D/', '', substr($value, 0, $position));
            $fraction = preg_replace('/\D/', '', substr($value, $position + 1));
        } else {
            $whole = preg_replace('/\D/', '', $value);
            $fraction = '';
        }
        if (strlen((string) $whole) > 14) {
            return null;
        }
        $minor = ((int) $whole) * (10 ** $precision) + (int) substr(str_pad((string) $fraction, $precision, '0'), 0, $precision);

        if (strlen((string) $fraction) > $precision && (int) ($fraction[$precision] ?? 0) >= 5) {
            $minor++;
        }

        return $negative ? -$minor : $minor;
    }

    public static function date(string $value): ?string
    {
        $months = ['জানুয়ারি' => 'January', 'ফেব্রুয়ারি' => 'February', 'মার্চ' => 'March', 'এপ্রিল' => 'April', 'মে' => 'May', 'জুন' => 'June', 'জুলাই' => 'July', 'আগস্ট' => 'August', 'সেপ্টেম্বর' => 'September', 'অক্টোবর' => 'October', 'নভেম্বর' => 'November', 'ডিসেম্বর' => 'December'];
        $value = strtr(self::text($value), $months);
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d F Y', '!F d, Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    public static function phone(string $value): string
    {
        $digits = preg_replace('/\D/', '', self::text($value)) ?? '';
        if (str_starts_with($digits, '00880')) {
            $digits = substr($digits, 2);
        } if (strlen($digits) === 11 && str_starts_with($digits, '01')) {
            $digits = '88'.$digits;
        }

        return str_starts_with($digits, '880') ? '+'.$digits : $digits;
    }

    public static function luhn(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($digits) < 12 || strlen($digits) > 19) {
            return false;
        } $sum = 0;
        foreach (str_split(strrev($digits)) as $i => $digit) {
            $n = (int) $digit * ($i % 2 ? 2 : 1);
            $sum += $n > 9 ? $n - 9 : $n;
        }

        return $sum % 10 === 0;
    }

    public static function mrzChecksum(string $value, string $digit): bool
    {
        $sum = 0;
        foreach (str_split(strtoupper($value)) as $i => $c) {
            $v = $c === '<' ? 0 : (ctype_digit($c) ? (int) $c : ord($c) - 55);
            if ($v < 0 || $v > 35) {
                return false;
            } $sum += $v * [7, 3, 1][$i % 3];
        }

        return ctype_digit($digit) && $sum % 10 === (int) $digit;
    }

    public static function iban(string $value): bool
    {
        $value = strtoupper(preg_replace('/\s/', '', $value) ?? '');
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/D', $value)) {
            return false;
        } $value = substr($value, 4).substr($value, 0, 4);
        $remainder = 0;
        foreach (str_split($value) as $char) {
            foreach (str_split(ctype_alpha($char) ? (string) (ord($char) - 55) : $char) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}
