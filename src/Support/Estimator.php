<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use ImranDevBd\AiHub\Support\CostCalculator;
use ImranDevBd\AiOcr\Drivers\Native\Extractor;
use ImranDevBd\AiOcr\Input\Resolver;

final class Estimator
{
    public function __construct(private readonly Resolver $resolver, private readonly Extractor $native) {}

    /** @return array<string, mixed> */
    public function estimate(mixed $input): array
    {
        $temp = new Temp;
        try {
            $document = $this->resolver->resolve($input, $temp);
            $pages = $this->native->pageCount($document);
            $provider = config('ai-ocr.ai.provider') ?: config('ai-hub.default', 'openai');
            $model = config('ai-ocr.ai.models.fast') ?: config('ai-hub.defaults.'.$provider);
            $calculator = CostCalculator::fromConfig();
            $rates = $calculator->ratesFor($provider, (string) $model);

            return ['pages' => $pages, 'provider' => $provider, 'model' => $model, 'estimated_usd' => $rates === null ? null : $calculator->calculate($provider, (string) $model, $pages * (int) config('ai-ocr.ai.estimated_image_tokens', 4000), $pages * (int) config('ai-ocr.ai.max_output_tokens', 4096)), 'warning' => 'Conservative token heuristic, not a price guarantee. Native extraction may reduce AI cost to zero.'];
        } finally {
            $temp->cleanup();
        }
    }
}
