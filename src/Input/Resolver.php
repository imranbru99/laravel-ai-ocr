<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Input;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ImranDevBd\AiOcr\Contracts\MediaSource;
use ImranDevBd\AiOcr\Exceptions\FileTooLargeException;
use ImranDevBd\AiOcr\Exceptions\UnsupportedFileException;
use ImranDevBd\AiOcr\Support\Temp;
use Psr\Http\Message\StreamInterface;

final class Resolver
{
    public function __construct(private readonly UrlFetcher $urls) {}

    public function resolve(mixed $input, Temp $temp, ?string $disk = null): Document
    {
        $name = 'document';
        $ownedStream = false;
        $max = (int) (config('ai-ocr.limits.max_size_mb', 25) * 1048576);
        if ($input instanceof MediaSource) {
            $disk = $input->ocrDisk();
            $input = $input->ocrPath();
        }
        if ($input instanceof \SplFileInfo) {
            $name = $input instanceof UploadedFile ? basename(str_replace('\\', '/', $input->getClientOriginalName())) : $input->getFilename();
            $input = $input->getRealPath();
        }
        if (is_string($input) && $disk !== null) {
            $name = basename($input);
            $input = Storage::disk($disk)->readStream($input);
            $ownedStream = true;
        }
        if ($input instanceof StreamInterface) {
            $input = $input->detach();
            $ownedStream = true;
        }
        if (is_resource($input)) {
            try {
                $bytes = stream_get_contents($input, $max + 1);
            } finally {
                if ($ownedStream) {
                    fclose($input);
                }
            }
            if ($bytes === false) {
                throw new UnsupportedFileException('Cannot read document stream.');
            }
        } elseif (is_string($input)) {
            if (preg_match('#^https?://#i', $input)) {
                $bytes = $this->urls->fetch($input);
            } elseif (! str_contains($input, "\0") && strlen($input) < 4096 && is_file($input)) {
                $name = $name === 'document' ? basename($input) : $name;
                $handle = fopen($input, 'rb');
                if ($handle === false) {
                    throw new UnsupportedFileException('Cannot read document file.');
                } try {
                    $bytes = (string) stream_get_contents($handle, $max + 1);
                } finally {
                    fclose($handle);
                }
            } elseif (preg_match('#^data:[^;,]+;base64,(.*)$#s', $input, $match)) {
                $bytes = base64_decode($match[1], true);
                if ($bytes === false) {
                    throw new UnsupportedFileException('Invalid base64 document.');
                }
            } elseif (strlen($input) >= 16 && strlen($input) % 4 === 0 && preg_match('#^[A-Za-z0-9+/]+={0,2}$#D', $input) && ($decoded = base64_decode($input, true)) !== false && $this->isBinaryDocument($decoded)) {
                $bytes = $decoded;
            } else {
                $bytes = $input;
            }
        } else {
            throw new UnsupportedFileException('Unsupported input; provide a file, bytes, URL or readable stream.');
        }
        if (strlen($bytes) > $max) {
            throw new FileTooLargeException('Document exceeds configured maximum size.');
        }
        if ($bytes === '') {
            throw new UnsupportedFileException('Document is empty.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (str_starts_with($bytes, '%PDF-')) {
            $mime = 'application/pdf';
        }
        if (str_starts_with($bytes, 'PK')) {
            $mime = 'application/zip';
        }
        if ($mime === 'image/svg+xml' || preg_match('/<svg\b/i', substr($bytes, 0, 1024))) {
            throw new UnsupportedFileException('SVG is not an accepted raster document.');
        }
        $path = $temp->path('input');
        file_put_contents($path, $bytes);
        chmod($path, 0600);
        if ($mime === 'application/zip') {
            $mime = $this->officeMime($path);
        }
        if (str_starts_with((string) $mime, 'image/')) {
            $dimensions = @getimagesize($path);
            if ($dimensions !== false && config('ai-ocr.limits.max_pixels', 60000000) < $dimensions[0] * $dimensions[1]) {
                throw new FileTooLargeException('Image pixel count exceeds configured maximum.');
            }
        }
        if ($mime === 'application/octet-stream' && mb_check_encoding($bytes, 'UTF-8') && ! str_contains($bytes, "\0")) {
            $mime = 'text/plain';
        }
        if (! in_array($mime, ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'application/x-ole-storage', 'text/plain', 'text/html', 'text/csv', 'text/tab-separated-values', 'text/x-mail', 'message/rfc822', 'image/png', 'image/jpeg', 'image/webp', 'image/tiff', 'image/bmp', 'image/x-ms-bmp', 'image/gif', 'image/heic', 'image/heif'], true)) {
            throw new UnsupportedFileException('Unsupported document MIME: '.$mime);
        }

        return new Document($path, (string) $mime, hash('sha256', $bytes), strlen($bytes), $name);
    }

    private function isBinaryDocument(string $bytes): bool
    {
        return str_starts_with($bytes, '%PDF-') || str_starts_with($bytes, 'PK') || str_starts_with($bytes, "\x89PNG") || str_starts_with($bytes, "\xff\xd8") || str_starts_with($bytes, 'GIF8');
    }

    private function officeMime(string $path): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new UnsupportedFileException('Office documents require ext-zip.');
        }
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new UnsupportedFileException('Invalid Office archive.');
        }
        try {
            if ($zip->numFiles > config('ai-ocr.limits.zip_max_entries', 10000)) {
                throw new FileTooLargeException('Office archive contains too many entries.');
            }
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if ($entry === false) {
                    continue;
                } $size += $entry['size'];
                if ($size > config('ai-ocr.limits.zip_max_uncompressed_mb', 100) * 1048576) {
                    throw new FileTooLargeException('Office archive exceeds expanded size limit.');
                } if (str_contains($entry['name'], '..') || str_starts_with($entry['name'], '/') || str_contains($entry['name'], '\\')) {
                    throw new UnsupportedFileException('Unsafe archive entry path.');
                }
            }
            if ($zip->locateName('word/document.xml') !== false) {
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }
            if ($zip->locateName('xl/workbook.xml') !== false) {
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }
            throw new UnsupportedFileException('Only DOCX and XLSX ZIP containers are accepted.');
        } finally {
            $zip->close();
        }
    }
}
