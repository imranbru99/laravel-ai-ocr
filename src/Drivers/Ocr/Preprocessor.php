<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Drivers\Ocr;

use ImranDevBd\AiOcr\Exceptions\ExtractionFailedException;
use ImranDevBd\AiOcr\Support\Temp;

final class Preprocessor
{
    /** @param list<string> $steps */
    public function process(string $path, array $steps, Temp $temp): string
    {
        if ($steps === []) {
            return $path;
        }
        $output = $temp->path('processed-'.bin2hex(random_bytes(5)).'.png');
        if (class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick($path);
            } catch (\ImagickException $error) {
                throw new ExtractionFailedException('Image preprocessing could not decode the raster.', 0, $error);
            }
            try {
                foreach ($steps as $step) {
                    match ($step) {
                        'deskew' => $image->deskewImage(0.4 * \Imagick::getQuantum()), 'grayscale' => $image->setImageColorspace(\Imagick::COLORSPACE_GRAY), 'denoise' => $image->despeckleImage(), 'threshold' => $image->adaptiveThresholdImage(25, 25, 5), 'contrast' => $image->normalizeImage(), 'border' => $image->trimImage(0), 'rotate' => $image->autoOrient(), 'upscale' => $image->getImageWidth() < 1000 ? $image->resizeImage(1000, 0, \Imagick::FILTER_LANCZOS, 1) : true, default => true
                    };
                } $image->setImageFormat('png');
                $image->writeImage($output);

                return $output;
            } catch (\ImagickException $error) {
                throw new ExtractionFailedException('Image preprocessing failed; check installed image codecs.', 0, $error);
            } finally {
                $image->clear();
            }
        }
        if (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring((string) file_get_contents($path));
            if ($image !== false) {
                try {
                    foreach ($steps as $step) {
                        if ($step === 'grayscale') {
                            imagefilter($image, IMG_FILTER_GRAYSCALE);
                        } if ($step === 'contrast') {
                            imagefilter($image, IMG_FILTER_CONTRAST, -20);
                        } if ($step === 'denoise') {
                            imagefilter($image, IMG_FILTER_SMOOTH, 2);
                        }
                    } imagepng($image, $output);

                    return $output;
                } finally {
                    unset($image);
                }
            }
        }

        return $path;
    }
}
