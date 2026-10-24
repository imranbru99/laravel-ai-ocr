<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Drivers\Native;

use ImranDevBd\AiOcr\Exceptions\FileTooLargeException;

final class MimeMessage
{
    /** @return list<string> Decoded MIME leaf contents, in document order. */
    public function parts(string $message, int $depth = 0): array
    {
        if ($depth > config('ai-ocr.limits.max_attachment_depth', 3)) {
            throw new FileTooLargeException('Email attachments exceed recursion limit.');
        }
        [$headers, $body] = array_pad(preg_split('/\r?\n\r?\n/', ltrim($message), 2) ?: [], 2, '');
        $headers = preg_replace('/\r?\n[ \t]+/', ' ', $headers) ?? $headers;
        if (preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/i', $headers, $m)) {
            $boundary = $m[1] !== '' ? $m[1] : $m[2];
            $leaves = [];
            foreach (array_slice(explode('--'.$boundary, $body), 1) as $part) {
                if (str_starts_with($part, '--')) {
                    break;
                }
                $leaves = [...$leaves, ...$this->parts(trim($part), $depth + 1)];
                if (count($leaves) > config('ai-ocr.limits.max_pages', 200)) {
                    throw new FileTooLargeException('Too many email attachments.');
                }
            }

            return $leaves;
        }
        if (stripos($headers, 'Content-Transfer-Encoding: base64') !== false) {
            $body = base64_decode(preg_replace('/\s/', '', $body) ?? '', true) ?: '';
        } elseif (stripos($headers, 'quoted-printable') !== false) {
            $body = quoted_printable_decode($body);
        }
        if (stripos($headers, 'Content-Type: message/rfc822') !== false) {
            return $this->parts($body, $depth + 1);
        }

        return trim($body) === '' ? [] : [$body];
    }
}
