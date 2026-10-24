<?php

declare(strict_types=1);
use ImranDevBd\AiOcr\AiOcrManager;
use ImranDevBd\AiOcr\ExtractionRequest;

if (! function_exists('ai_ocr')) {
    function ai_ocr(mixed $input): ExtractionRequest
    {
        return app(AiOcrManager::class)->file($input);
    }
}
