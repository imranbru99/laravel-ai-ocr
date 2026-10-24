<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Facades;

use Illuminate\Support\Facades\Facade;
use ImranDevBd\AiOcr\AiOcrManager;

/** @mixin AiOcrManager */
final class AiOcr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiOcrManager::class;
    }
}
