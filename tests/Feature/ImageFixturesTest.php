<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use ImranDevBd\AiHub\Facades\AIHub;
use ImranDevBd\AiOcr\Contracts\Rasterizer;
use ImranDevBd\AiOcr\Drivers\Ocr\Preprocessor;
use ImranDevBd\AiOcr\Exceptions\FileTooLargeException;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Input\Resolver;
use ImranDevBd\AiOcr\Support\Temp;
use ImranDevBd\AiOcr\Tests\Fixtures\Fixtures;
use ImranDevBd\AiOcr\Tests\TestCase;

final class ImageFixturesTest extends TestCase
{
    public function test_huge_pixel_dimensions_are_rejected_without_decoding(): void
    {
        $chunk = pack('NNCCCCC', 100000, 100000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n".pack('N', strlen($chunk)).'IHDR'.$chunk.pack('N', crc32('IHDR'.$chunk));
        $this->expectException(FileTooLargeException::class);
        AiOcr::file($png)->manualOnly()->extract();
    }

    public function test_synthetic_legacy_bangla_pdf_triggers_page_recovery(): void
    {
        $this->hubFake('{"text":"এই বাংলা নথির পাঠ্য পুনরুদ্ধার করা হয়েছে এবং যাচাইয়ের জন্য প্রস্তুত।","confidence":0.95}');
        $legacy = mb_convert_encoding('evsjv cÖKvk Kvh© weÁ wkÿ †‡ªÖ repeated text', 'Windows-1252', 'UTF-8');
        $result = AiOcr::file(Fixtures::pdf([$legacy]))->extract();
        self::assertTrue($result->usedAi());
        self::assertSame('ai', $result->pages()[0]->engine);
    }

    public function test_optional_imagick_preprocessing_and_multipage_tiff(): void
    {
        if (! class_exists(\Imagick::class)) {
            self::markTestSkipped('Optional ext-imagick is not installed.');
        }
        $temp = new Temp;
        $sequence = new \Imagick;
        foreach (['white', 'red'] as $color) {
            $frame = new \Imagick;
            $frame->newImage(80, 40, $color, 'tiff');
            $sequence->addImage($frame);
            $frame->clear();
        }
        $source = $temp->path('multipage.tiff');
        $sequence->setImageFormat('tiff');
        $sequence->writeImages($source, true);
        $sequence->clear();
        $result = AiOcr::file($source)->manualOnly()->extract();
        self::assertCount(2, $result->pages());
        $document = app(Resolver::class)->resolve($source, $temp);
        $second = app(Rasterizer::class)->page($document, 2, 150, $temp);
        $image = new \Imagick($second);
        $color = $image->getImagePixelColor(0, 0)->getColor();
        $image->clear();
        self::assertGreaterThan($color['g'], $color['r']);
        $processed = app(Preprocessor::class)->process($second, ['rotate', 'deskew', 'grayscale', 'denoise', 'threshold', 'contrast', 'border', 'upscale'], $temp);
        self::assertFileExists($processed);
    }

    public function test_gd_ai_packing_honors_requested_png_format(): void
    {
        if (! extension_loaded('gd')) {
            self::markTestSkipped('Optional GD is not installed.');
        }
        $this->hubFake('{"text":"Readable recovered content from a PNG packed by the GD image driver.","confidence":0.95}');
        config(['ai-ocr.ai.image_driver' => 'gd', 'ai-ocr.ai.image_format' => 'png']);
        $result = AiOcr::file($this->image())->extract();
        self::assertTrue($result->usedAi());
        AIHub::assertSent(fn ($request) => str_starts_with($request->getImages()[0], 'data:image/png;base64,'));
    }

    public function test_local_ocr_pool_processes_pages_and_preserves_order(): void
    {
        config(['ai-ocr.ocr.enabled' => true, 'ai-ocr.ocr.concurrency' => 2, 'ai-ocr.ocr.dpi' => 120]);
        $result = AiOcr::file(Fixtures::pdf(['', '']))->manualOnly()->preprocess([])->extract();
        self::assertSame([1, 2], array_column(array_map(fn ($p) => $p->jsonSerialize(), $result->pages()), 'number'));
        self::assertNotEmpty(array_filter($result->trace(), fn ($step) => $step['step'] === 'local_ocr'));
    }
}
