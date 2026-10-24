<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Drivers\Native;

use ImranDevBd\AiOcr\Input\Document;
use ImranDevBd\AiOcr\Support\Binaries;
use ImranDevBd\AiOcr\Support\Temp;

final class PdfMetadata
{
    public function __construct(private readonly Binaries $binaries) {}

    /** @return array<string,mixed> */
    public function extract(Document $document, Temp $temp): array
    {
        if ($document->mime !== 'application/pdf') {
            return [];
        }
        /** @var array<string,mixed> $metadata */
        $metadata = ['forms' => [], 'embedded_images' => []];
        if ($this->binaries->find('pdfinfo')) {
            foreach (explode("\n", $this->binaries->run('pdfinfo', [$document->path])) as $line) {
                if (str_contains($line, ':')) {
                    [$key,$value] = explode(':', $line, 2);
                    $key = str_replace(' ', '_', strtolower(trim($key)));
                    if (! in_array($key, ['forms', 'embedded_images'], true)) {
                        $metadata[$key] = trim($value);
                    }
                }
            }
        }
        $bytes = (string) file_get_contents($document->path);
        preg_match_all('/<<(.*?)>>/s', $bytes, $objects);
        foreach ($objects[1] as $object) {
            if (preg_match('/\/T\s*\(((?:\\\\.|[^()])*)\)/s', $object, $name) && preg_match('/\/V\s*\(((?:\\\\.|[^()])*)\)/s', $object, $value)) {
                $metadata['forms'][stripcslashes($name[1])] = stripcslashes($value[1]);
            }
        }
        if (! config('ai-ocr.native.extract_images', false) || ! $this->binaries->find('pdfimages')) {
            return $metadata;
        }
        $list = $this->binaries->run('pdfimages', ['-list', $document->path]);
        $pages = [];
        foreach (explode("\n", $list) as $row) {
            $columns = preg_split('/\s+/', trim($row)) ?: [];
            if (count($columns) > 5 && ctype_digit($columns[0]) && ctype_digit($columns[3]) && ctype_digit($columns[4])) {
                if ((int) $columns[3] * (int) $columns[4] > (int) config('ai-ocr.limits.max_pixels', 60000000)) {
                    $metadata['image_warning'] = 'An embedded image exceeds the pixel limit.';

                    return $metadata;
                }
                $pages[(int) $columns[0]] = ($pages[(int) $columns[0]] ?? 0) + 1;
            }
        }
        $limit = (int) config('ai-ocr.native.max_embedded_images', 10);
        $total = 0;
        foreach ($pages as $page => $count) {
            if (count($metadata['embedded_images']) + $count > $limit) {
                $metadata['image_warning'] = 'Embedded image limit reached.';
                break;
            }
            $prefix = $temp->path('embedded-'.$page);
            $this->binaries->run('pdfimages', ['-f', (string) $page, '-l', (string) $page, '-png', $document->path, $prefix]);
            foreach (glob($prefix.'-*') ?: [] as $path) {
                $size = filesize($path);
                $total += $size === false ? 0 : $size;
                if ($total > config('ai-ocr.limits.max_size_mb', 25) * 1048576) {
                    $metadata['image_warning'] = 'Embedded image byte limit reached.';

                    return $metadata;
                }
                $metadata['embedded_images'][] = ['page' => $page, 'mime' => (new \finfo(FILEINFO_MIME_TYPE))->file($path), 'base64' => base64_encode((string) file_get_contents($path))];
                unlink($path);
            }
        }

        return $metadata;
    }
}
