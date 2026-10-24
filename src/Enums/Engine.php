<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Enums;

enum Engine: string
{
    case Native = 'native';
    case Tesseract = 'tesseract';
    case Ai = 'ai';
}
