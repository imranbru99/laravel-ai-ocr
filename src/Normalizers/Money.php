<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Normalizers;

final class Money
{
    /** @return array{minor: int|null, currency: string|null} */
    public static function parse(string $value, ?string $currency = null, int $scale = 2): array
    {
        if ($scale < 0 || $scale > 4) {
            throw new \InvalidArgumentException('Currency precision must be between zero and four.');
        }
        if ($currency === null) {
            foreach (['৳' => 'BDT', '$' => 'USD', '€' => 'EUR', '£' => 'GBP', '₹' => 'INR'] as $symbol => $code) {
                if (str_contains($value, $symbol)) {
                    $currency = $code;
                    break;
                }
            }
        }
        if ($currency === null && preg_match('/\b(BDT|USD|EUR|AUD|GBP|INR|JPY)\b/', $value, $match)) {
            $currency = $match[1];
        }

        return ['minor' => Normalizer::money($value, $scale), 'currency' => $currency];
    }

    public static function multiply(int $minor, string $quantity): ?int
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,6}))?$/D', $quantity, $parts)) {
            return null;
        }
        $fraction = $parts[3] ?? '';
        if (strlen($parts[2].$fraction) > 15) {
            return null;
        }
        $factor = 10 ** strlen($fraction);
        $unscaled = (int) ($parts[2].$fraction);
        if ($unscaled !== 0 && abs($minor) > intdiv(PHP_INT_MAX, $unscaled)) {
            return null;
        }
        $product = $minor * $unscaled;
        if (abs($product) > PHP_INT_MAX - intdiv($factor, 2)) {
            return null;
        }
        $sign = ($parts[1] === '-' ? -1 : 1) * ($product < 0 ? -1 : 1);

        return $sign * intdiv(abs($product) + intdiv($factor, 2), $factor);
    }
}
