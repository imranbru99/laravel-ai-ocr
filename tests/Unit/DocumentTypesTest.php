<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Unit;

use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Normalizers\Normalizer;
use ImranDevBd\AiOcr\Tests\TestCase;
use ImranDevBd\AiOcr\Types\TypeRegistry;

final class DocumentTypesTest extends TestCase
{
    private function checksum(string $value): string
    {
        for ($i = 0; $i <= 9; $i++) {
            if (Normalizer::mrzChecksum($value, (string) $i)) {
                return (string) $i;
            }
        }
        throw new \LogicException('Cannot calculate MRZ checksum.');
    }

    private function mrz(): string
    {
        $number = 'A12345678';
        $birth = '900101';
        $expiry = '300101';
        $optional = str_repeat('<', 14);
        $line = $number.$this->checksum($number).'BGD'.$birth.$this->checksum($birth).'F'.$expiry.$this->checksum($expiry).$optional.$this->checksum($optional);
        $line .= $this->checksum(substr($line, 0, 10).substr($line, 13, 7).substr($line, 21, 22));

        return str_pad('P<BGDSAMPLE<<TEST', 44, '<')."\n".$line;
    }

    /** @param array<string,mixed> $node @return array<string,mixed> */
    private function data(array $node): array
    {
        $data = [];
        foreach ($node['properties'] ?? [] as $key => $field) {
            $data[$key] = isset($field['enum']) ? $field['enum'][0] : match ($field['type'] ?? 'string') {
                'integer' => 0, 'number' => 0.0, 'boolean' => false,
                'object' => $this->data($field), 'array' => [$this->data($field['items'])],
                default => ($field['format'] ?? '') === 'date' ? '2026-10-24' : 'Synthetic example',
            };
        }

        return $data;
    }

    public function test_every_builtin_schema_has_a_license_clean_synthetic_fixture(): void
    {
        $registry = new TypeRegistry;
        foreach ($registry->names() as $name) {
            $type = $registry->get($name);
            $data = $this->data($type->schema()->toJsonSchema());
            if ($name === 'bd_nid') {
                $data['nid_number'] = '1234567890';
            }
            if ($name === 'passport') {
                $data['mrz'] = $this->mrz();
            }
            self::assertSame([], $type->schema()->errors($data), $name);
            self::assertSame([], $type->crossChecks($data), $name);
            self::assertNotSame('', $type->promptHints());
        }
    }

    public function test_passport_mrz_is_parsed_and_validated_without_ai(): void
    {
        $result = AiOcr::file($this->mrz())->as('passport')->manualOnly()->extract();
        self::assertSame('A12345678', $result->field('passport_number')->value);
        self::assertSame('1990-01-01', $result->field('birth_date')->value);
        self::assertSame('2030-01-01', $result->field('expiry_date')->value);
        self::assertSame('SAMPLE TEST', $result->field('name')->value);
        self::assertSame([], $result->warnings());
    }

    public function test_bangla_nid_labels_and_digits_map_to_validated_fields(): void
    {
        $text = "জাতীয় পরিচয়পত্র নম্বর: ১২৩৪৫৬৭৮৯০\nName: Sample Person\nজন্ম তারিখ: ২৪ অক্টোবর ২০০০\nপিতা: Synthetic Parent";
        $result = AiOcr::file($text)->as('bd_nid')->languages(['ben', 'eng'])->manualOnly()->extract();
        self::assertSame('1234567890', $result->field('nid_number')->value);
        self::assertSame('2000-10-24', $result->field('birth_date')->value);
        self::assertTrue($result->field('nid_number')->validated);
    }

    public function test_invoice_cross_checks_flag_only_offending_fields(): void
    {
        $type = (new TypeRegistry)->get('invoice');
        self::assertContains('total', $type->crossChecks(['subtotal' => 1000, 'tax' => 100, 'total' => 1500]));
        self::assertSame([], $type->crossChecks(['subtotal' => 1000, 'tax' => 100, 'total' => 1100]));
    }
}
