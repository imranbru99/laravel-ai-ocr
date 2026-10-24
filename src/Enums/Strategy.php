<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Enums;

enum Strategy: string
{
    case Auto = 'auto';
    case ManualOnly = 'manual_only';
    case AiOnly = 'ai_only';
    case AiVerify = 'ai_verify';
    case Consensus = 'consensus';
}
