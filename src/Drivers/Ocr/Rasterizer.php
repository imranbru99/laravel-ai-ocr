<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Drivers\Ocr;

use ImranDevBd\AiOcr\Exceptions\ExtractionFailedException;
use ImranDevBd\AiOcr\Input\Document;
use ImranDevBd\AiOcr\Support\Binaries;
use ImranDevBd\AiOcr\Support\Temp;

final class Rasterizer implements \ImranDevBd\AiOcr\Contracts\Rasterizer
{
    public function __construct(private readonly Binaries $binaries) {}

    public function page(Document $document, int $page, int $dpi, Temp $temp): string
    {
        $source = $document->source($page);
        if ($source['document'] !== $document) {
            return $this->page($source['document'], $source['page'], $dpi, $temp);
        }
        if ($document->mime === 'application/pdf') {
            $prefix = $temp->path('page-'.$page);
            $side = (int) floor(sqrt((int) config('ai-ocr.limits.max_pixels', 60000000)));
            if ($this->binaries->find('pdfinfo')) {
                $info = $this->binaries->run('pdfinfo', ['-f', (string) $page, '-l', (string) $page, $document->path]);
                if (preg_match('/size:\s*([0-9.]+)\s+x\s+([0-9.]+)\s+pts/i', $info, $dimensions)) {
                    $side = min($side, (int) ceil(max((float) $dimensions[1], (float) $dimensions[2]) * $dpi / 72));
                }
            }

            if ($this->binaries->find('pdftoppm')) {
                $this->binaries->run('pdftoppm', ['-f', (string) $page, '-l', (string) $page, '-r', (string) $dpi, '-scale-to', (string) $side, '-singlefile', '-png', $document->path, $prefix]);

                return $prefix.'.png';
            }
        } elseif (! in_array($document->mime, ['image/tiff', 'image/heic', 'image/heif'], true)) {
            return $document->path;
        }
        if (class_exists(\Imagick::class)) {
            $image = new \Imagick;
            $image->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 128 * 1024 * 1024);
            $image->setResourceLimit(\Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
            try {
                $image->setResolution($dpi, $dpi);
                $image->pingImage($document->path.'['.($page - 1).']');
                if ($image->getImageWidth() * $image->getImageHeight() > config('ai-ocr.limits.max_pixels', 60000000)) {
                    throw new ExtractionFailedException('Raster dimensions exceed pixel limit.');
                } $image->readImage($document->path.'['.($page - 1).']');
                $image->setImageFormat('png');
                $path = $temp->path('raster-'.$page.'.png');
                $image->writeImage($path);

                return $path;
            } catch (\ImagickException $error) {
                throw new ExtractionFailedException('Image rasterization failed; check installed codecs.', 0, $error);
            } finally {
                $image->clear();
            }
        }
        throw new ExtractionFailedException('Rasterization requires poppler pdftoppm or ext-imagick.');
    }
}
