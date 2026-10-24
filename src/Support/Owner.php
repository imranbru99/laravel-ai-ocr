<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use Illuminate\Database\Eloquent\Model;

final class Owner
{
    public static function type(?object $owner): ?string
    {
        if ($owner === null) {
            return null;
        }

        return $owner instanceof Model ? $owner->getMorphClass() : $owner::class;
    }
}
