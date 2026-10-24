<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Quality;

use ImranDevBd\AiOcr\Enums\GateStatus;

final class Gate
{
    /**
     * @param  array<int, float>  $scores
     * @param  list<string>  $failedFields
     * @return array{status: string, failed_pages: list<int>, failed_fields: list<string>}
     */
    public function evaluate(array $scores, array $failedFields, float $threshold): array
    {
        $failedPages = array_keys(array_filter($scores, fn ($score) => $score < $threshold));
        $status = $failedPages === [] && $failedFields === [] ? GateStatus::Pass : ($failedPages !== [] && count($failedPages) === count($scores) ? GateStatus::Fail : GateStatus::Partial);

        return ['status' => $status->value, 'failed_pages' => $failedPages, 'failed_fields' => $failedFields];
    }
}
