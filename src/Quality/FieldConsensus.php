<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Quality;

use ImranDevBd\AiOcr\DTO\FieldValue;

final class FieldConsensus
{
    /** @param list<FieldValue> $candidates */
    public function choose(array $candidates): FieldValue
    {
        if ($candidates === []) {
            throw new \InvalidArgumentException('Consensus requires at least one candidate.');
        }
        /** @var array<string, array{score:float,best:FieldValue}> $groups */
        $groups = [];
        foreach ($candidates as $field) {
            if (! $field->validated) {
                continue;
            }
            $key = json_encode($field->value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            if (! isset($groups[$key])) {
                $groups[$key] = ['score' => 0.0, 'best' => $field];
            }
            $groups[$key]['score'] = ($groups[$key]['score'] ?? 0.0) + $field->confidence;
            if ($field->confidence > $groups[$key]['best']->confidence) {
                $groups[$key]['best'] = $field;
            }
        }
        if ($groups === []) {
            return $candidates[0];
        }
        uasort($groups, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_values($groups)[0]['best'];
    }
}
