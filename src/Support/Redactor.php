<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use ImranDevBd\AiOcr\Normalizers\Normalizer;

final class Redactor
{
    /** @var array<string, string> */
    private array $map = [];

    /** @param list<string> $kinds */
    public function redact(string $text, array $kinds): string
    {
        $patterns = ['email' => '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', 'phone' => '/(?<!\d)(?:\+?880|0)1[3-9]\d{8}(?!\d)/', 'nid' => '/(?<!\d)(?:\d{17}|\d{13}|\d{10})(?!\d)/', 'passport' => '/\b[A-Z]{1,2}\d{7,8}\b/', 'card' => '/(?<!\d)(?:\d[ -]?){12,19}(?!\d)/'];
        foreach ($kinds as $kind) {
            if (! isset($patterns[$kind])) {
                continue;
            } $text = preg_replace_callback($patterns[$kind], function ($m) use ($kind) {
                if ($kind === 'card' && ! Normalizer::luhn($m[0])) {
                    return $m[0];
                } $token = '__PII_'.bin2hex(random_bytes(8)).'__';
                $this->map[$token] = $m[0];

                return $token;
            }, $text) ?? $text;
        }

        return $text;
    }

    public function restore(mixed $value): mixed
    {
        if (is_string($value)) {
            return strtr($value, $this->map);
        } if (is_array($value)) {
            return array_map($this->restore(...), $value);
        }

        return $value;
    }
}
