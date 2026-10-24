<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Input;

use ImranDevBd\AiOcr\Support\Temp;

final class Document
{
    /** @var array<int, array{document: self, page: int}> */
    private array $sources = [];

    /** @var list<int>|null */
    private ?array $selection = null;

    /** @var list<Temp> */
    private array $temporaryOwners = [];

    public function __construct(public readonly string $path, public readonly string $mime, public readonly string $hash, public readonly int $size, public readonly string $name) {}

    /** @param list<int> $numbers */
    public function select(array $numbers): void
    {
        $this->selection = $numbers;
    }

    public function selected(int $number): bool
    {
        return $this->selection === null || in_array($number, $this->selection, true);
    }

    public function attach(int $number, self $source, int $page): void
    {
        $this->sources[$number] = ['document' => $source, 'page' => $page];
    }

    /** @return array{document: self, page: int} */
    public function source(int $number): array
    {
        return $this->sources[$number] ?? ['document' => $this, 'page' => $number];
    }

    public function retain(Temp $temp): void
    {
        $this->temporaryOwners[] = $temp;
    }

    public function __destruct()
    {
        foreach ($this->temporaryOwners as $owner) {
            $owner->cleanup();
        }
    }
}
