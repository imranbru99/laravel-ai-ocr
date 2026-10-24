<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Unit;

use ImranDevBd\AiOcr\Drivers\Ocr\Tesseract;
use ImranDevBd\AiOcr\DTO\Page;
use ImranDevBd\AiOcr\Normalizers\Normalizer;
use ImranDevBd\AiOcr\Quality\GarbledDetector;
use ImranDevBd\AiOcr\Quality\QualityScorer;
use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Support\Redactor;
use ImranDevBd\AiOcr\Tests\TestCase;

final class QualityAndSchemaTest extends TestCase
{
    public function test_garbled_encoding_is_detected(): void
    {
        $quality = new QualityScorer(new GarbledDetector);
        self::assertLessThan(0.75, $quality->score(new Page(1, 'evsjv cÖKvk Kvh© weÁ wkÿ †‡ªÖ repeated text'))['score']);
        self::assertNotEmpty((new GarbledDetector)->reasons('This is Latin text on a Bengali page', 'bengali'));
        self::assertSame([], (new GarbledDetector)->reasons('A normal readable English text document.'));
    }

    public function test_schema_compiles_nested_fields_and_rejects_extra_keys(): void
    {
        $schema = Schema::make()->string('seller.name')->required()->array('items', fn ($i) => $i->integer('quantity')->required());
        self::assertSame([], $schema->errors(['seller' => ['name' => 'Shop'], 'items' => [['quantity' => 2]]]));
        self::assertContains('seller.name', $schema->errors(['seller' => []]));
        self::assertContains('seller.execute', $schema->errors(['seller' => ['name' => 'Shop', 'execute' => 'bad']]));
        self::assertArrayHasKey('items.*.quantity', $schema->rules());
    }

    public function test_numbers_money_dates_and_checksums(): void
    {
        self::assertSame('0123456789', Normalizer::text('০১২৩৪৫৬৭৮৯'));
        self::assertSame(12345650, Normalizer::money('৳ 1,23,456.50'));
        self::assertSame(123450, Normalizer::money('€ 1.234,50'));
        self::assertSame('2026-10-24', Normalizer::date('২৪ অক্টোবর ২০২৬'));
        self::assertNull(Normalizer::date('31/02/2026'));
        self::assertSame('+8801712345678', Normalizer::phone('01712345678'));
        self::assertTrue(Normalizer::luhn('4111 1111 1111 1111'));
        self::assertTrue(Normalizer::iban('GB82 WEST 1234 5698 7654 32'));
        self::assertTrue(Normalizer::mrzChecksum('L898902C3', '6'));
    }

    public function test_redaction_is_reversible_and_checksum_aware(): void
    {
        $redactor = new Redactor;
        $text = 'Email me@example.com card 4111111111111111 phone 01712345678';
        $masked = $redactor->redact($text, ['email', 'card', 'phone']);
        self::assertStringNotContainsString('me@example.com', $masked);
        self::assertStringNotContainsString('4111111111111111', $masked);
        self::assertSame($text, $redactor->restore($masked));
    }

    public function test_tesseract_tsv_parsing(): void
    {
        $tsv = "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext\n5\t1\t1\t1\t1\t1\t10\t20\t30\t40\t90\tHello\n";
        $page = app(Tesseract::class)->parse($tsv, 3);
        self::assertSame('Hello', $page->text);
        self::assertSame(0.9, $page->confidence);
        self::assertSame(10, $page->words[0]['bbox']['x']);
    }
}
