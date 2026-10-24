<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use ImranDevBd\AiOcr\Models\Document;

final class Sources
{
    public function retain(Document $record, string $path): void
    {
        if (! config('ai-ocr.store.retain_original', false) || $record->getAttribute('path') !== null) {
            return;
        }
        $disk = (string) config('ai-ocr.store.disk', 'local');
        $destination = 'ai-ocr/sources/'.$record->getKey().'.enc';
        if (! Storage::disk($disk)->put($destination, Crypt::encryptString((string) file_get_contents($path)), ['visibility' => 'private'])) {
            throw new \RuntimeException('Cannot retain encrypted original document.');
        }
        $record->forceFill(['disk' => $disk, 'path' => $destination])->save();
    }

    public function read(Document $record): string
    {
        $path = $record->getAttribute('path');
        $disk = $record->getAttribute('disk');
        if (! is_string($path) || ! is_string($disk)) {
            throw new \InvalidArgumentException('Original was not retained; upload the document again or enable store.retain_original.');
        }
        $bytes = Storage::disk($disk)->get($path);
        if (! is_string($bytes)) {
            throw new \RuntimeException('Retained original is missing.');
        }

        return Crypt::decryptString($bytes);
    }

    public function forget(Document $record): void
    {
        $disk = $record->getAttribute('disk');
        $path = $record->getAttribute('path');
        if (is_string($disk) && is_string($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
