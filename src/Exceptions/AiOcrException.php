<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Exceptions;

class AiOcrException extends \RuntimeException
{
    /** @param array<string, int|float> $usage */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, public readonly array $usage = [])
    {
        parent::__construct($message, $code, $previous);
    }
}
