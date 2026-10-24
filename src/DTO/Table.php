<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\DTO;

use Illuminate\Support\Collection;

final readonly class Table implements \JsonSerializable
{
    /**
     * @param  list<list<string>>  $rows
     * @param  array<string, float>  $bbox
     */
    public function __construct(public array $rows, public int $page = 1, public bool $hasHeader = false, public bool $mergedCells = false, public array $bbox = [], public float $confidence = 1.0) {}

    /** @return list<list<string>> */
    public function toArray(): array
    {
        return $this->rows;
    }

    /** @return Collection<int, list<string>> */
    public function toCollection(): Collection
    {
        return collect($this->rows);
    }

    public function toCsv(): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Cannot open CSV stream.');
        }
        try {
            foreach ($this->rows as $row) {
                fputcsv($stream, $row, ',', '"', '');
            } rewind($stream);

            return (string) stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    public function toMarkdown(): string
    {
        $rows = array_map(fn ($r) => '| '.implode(' | ', array_map(fn ($v) => str_replace(['|', "\n"], ['\\|', ' '], $v), $r)).' |', $this->rows);
        if ($rows !== []) {
            array_splice($rows, 1, 0, ['| '.implode(' | ', array_fill(0, count($this->rows[0]), '---')).' |']);
        }

        return implode("\n", $rows);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
