<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Enums;

enum GateStatus: string
{
    case Pass = 'pass';
    case Partial = 'partial';
    case Fail = 'fail';
}
