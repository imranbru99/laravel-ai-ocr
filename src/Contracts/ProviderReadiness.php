<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Contracts;

interface ProviderReadiness
{
    /** @return list<string> */
    public function ready(): array;
}
