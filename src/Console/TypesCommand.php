<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Console;

use Illuminate\Console\Command;
use ImranDevBd\AiOcr\Types\TypeRegistry;

final class TypesCommand extends Command
{
    protected $signature = 'ai-ocr:types';

    protected $description = 'List registered document types';

    public function handle(TypeRegistry $types): int
    {
        foreach ($types->names() as $name) {
            $this->line($name);
        }

        return 0;
    }
}
