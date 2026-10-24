<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Types\DocumentType;
use Laravel\SerializableClosure\SerializableClosure;

final class JobOptions
{
    /**
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    public static function prepare(array $options): array
    {
        $user = auth()->guard()->user();
        $options['owner_id'] = $options['owner_id'] ?? $user?->getAuthIdentifier();
        $options['owner_type'] = $options['owner_type'] ?? (Owner::type($user));
        $schema = $options['schema'] ?? null;
        if ($schema instanceof Schema) {
            if ($schema->cacheDefinition() === null) {
                throw new \InvalidArgumentException('Queued schema casts must be defined in a class-based DocumentType.');
            } $options['privacy_type'] = $schema->name;
            $options['schema'] = $schema->toJsonSchema();
        } elseif ($schema instanceof DocumentType) {
            $options['privacy_type'] = $schema->name();
        } elseif (is_string($schema)) {
            $options['privacy_type'] = is_subclass_of($schema, DocumentType::class) ? (new $schema)->name() : $schema;
        }
        if (($options['tap'] ?? null) instanceof \Closure) {
            $options['tap'] = new SerializableClosure($options['tap']);
        }

        return $options;
    }
}
