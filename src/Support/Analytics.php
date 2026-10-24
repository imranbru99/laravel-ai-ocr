<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use ImranDevBd\AiOcr\Models\Extraction;

final class Analytics
{
    /** @return array<string,mixed> */
    public function summary(): array
    {
        $user = auth()->guard()->user();
        $query = Extraction::query()->whereHas('document', fn ($q) => $q->where('owner_id', $user?->getAuthIdentifier())->where('owner_type', Owner::type($user)));
        $count = (clone $query)->count();
        $cost = (float) (clone $query)->sum('cost');
        $confidence = (float) (clone $query)->avg('confidence');
        $fallback = 0;
        $types = [];
        foreach ((clone $query)->cursor() as $record) {
            $engines = $record->getAttribute('engine_summary');
            $usedAi = is_array($engines) && ($engines['ai'] ?? 0) > 0;
            if (! $usedAi) {
                $usedAi = $record->extractionResult()->usedAi();
            }
            if ($usedAi) {
                $fallback++;
            }
            $type = (string) ($record->getAttribute('type') ?? 'text');
            $review = $record->getAttribute('review_correct');
            if ($review !== null) {
                $types[$type]['reviewed'] = ($types[$type]['reviewed'] ?? 0) + 1;
                $types[$type]['correct'] = ($types[$type]['correct'] ?? 0) + ($review ? 1 : 0);
            }
            $types[$type]['count'] = ($types[$type]['count'] ?? 0) + 1;
            $types[$type]['confidence_sum'] = ($types[$type]['confidence_sum'] ?? 0) + (float) $record->getAttribute('confidence');
        }
        foreach ($types as &$type) {
            $type['reviewed_accuracy'] = isset($type['reviewed']) ? ($type['correct'] ?? 0) / $type['reviewed'] : null;
            $type['average_confidence'] = $type['confidence_sum'] / $type['count'];
            unset($type['confidence_sum']);
        } unset($type);
        $aiCost = $fallback > 0 ? $cost / $fallback : null;

        return ['documents' => $count, 'cost_usd' => $cost, 'average_cost_usd' => $count > 0 ? $cost / $count : 0, 'average_confidence' => $confidence, 'ai_documents' => $fallback, 'fallback_rate' => $count > 0 ? $fallback / $count : 0, 'per_type' => $types, 'estimated_ai_only_cost' => $aiCost !== null ? $aiCost * $count : null, 'estimated_savings_usd' => $aiCost !== null ? max(0, $aiCost * $count - $cost) : null, 'savings_method' => 'Extrapolation from observed AI document cost; accuracy requires labeled ground truth.'];
    }
}
