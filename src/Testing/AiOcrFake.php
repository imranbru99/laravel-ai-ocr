<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Testing;

use ImranDevBd\AiOcr\AiOcrManager;
use ImranDevBd\AiOcr\DTO\ExtractionResult;
use PHPUnit\Framework\Assert;

final class AiOcrFake extends AiOcrManager
{
    /** @var list<array{input: mixed, options: array<string, mixed>, result: ExtractionResult}> */
    private array $recorded = [];

    /** @var list<ExtractionResult> */
    private array $responses;

    /** @param ExtractionResult|list<ExtractionResult> $responses */
    public function __construct(ExtractionResult|array $responses = [])
    {
        $this->responses = $responses instanceof ExtractionResult ? [$responses] : $responses;
    }

    /** @param array<string, mixed> $options */
    public function extract(mixed $input, array $options = []): ExtractionResult
    {
        $result = array_shift($this->responses) ?? new ExtractionResult;
        $this->recorded[] = ['input' => $input, 'options' => $options, 'result' => $result];

        return $result;
    }

    public function assertExtracted(?callable $callback = null): void
    {
        Assert::assertNotEmpty(array_filter($this->recorded, $callback ?? fn ($r) => true), 'No matching OCR extraction was recorded.');
    }

    public function assertExtractedCount(int $count): void
    {
        Assert::assertCount($count, $this->recorded);
    }

    public function assertUsedAi(): void
    {
        Assert::assertNotEmpty(array_filter($this->recorded, fn ($r) => $r['result']->usedAi()));
    }

    public function assertNotUsedAi(): void
    {
        Assert::assertEmpty(array_filter($this->recorded, fn ($r) => $r['result']->usedAi()));
    }

    public function assertSentToAi(callable $callback): void
    {
        Assert::assertNotEmpty(array_filter($this->recorded, fn ($r) => $r['result']->usedAi() && $callback($r)));
    }
}
