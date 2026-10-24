<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Unit;

use ImranDevBd\AiOcr\DTO\FieldValue;
use ImranDevBd\AiOcr\DTO\Page;
use ImranDevBd\AiOcr\Normalizers\Money;
use ImranDevBd\AiOcr\Normalizers\Normalizer;
use ImranDevBd\AiOcr\Normalizers\Passport;
use ImranDevBd\AiOcr\Quality\FieldConsensus;
use ImranDevBd\AiOcr\Quality\GarbledDetector;
use ImranDevBd\AiOcr\Quality\QualityScorer;
use ImranDevBd\AiOcr\Quality\TextStatistics;
use ImranDevBd\AiOcr\Schema\ManualMapper;
use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Tests\TestCase;
use ImranDevBd\AiOcr\Types\TypeRegistry;

final class ValidationBranchesTest extends TestCase
{
    public function test_schema_rejects_out_of_range_wrong_enum_and_invalid_nested_values(): void
    {
        $schema = Schema::fromJson(['type' => 'object', 'properties' => [
            'amount' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
            'code' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 4, 'pattern' => '^[A-Z]+$'],
            'email' => ['type' => 'string', 'format' => 'email'], 'url' => ['type' => 'string', 'format' => 'uri'],
            'rows' => ['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 1, 'maxItems' => 2],
            'currency' => ['type' => 'string', 'enum' => ['BDT', 'USD']],
        ]]);
        $bad = ['amount' => -1, 'code' => 'a', 'email' => 'invalid', 'url' => 'invalid', 'rows' => [], 'currency' => 'EUR'];
        self::assertSame(['amount', 'code', 'email', 'url', 'rows', 'currency'], $schema->errors($bad));
        self::assertSame(['amount', 'code', 'rows'], $schema->errors(['amount' => 101, 'code' => 'TOOLONG', 'rows' => [1, 2, 3]]));
        self::assertContains('rows.0', $schema->errors(['rows' => ['bad']]));
    }

    public function test_schema_rejects_unsupported_or_malformed_definitions(): void
    {
        foreach ([['oneOf' => []], ['type' => 'unknown'], ['type' => 'object', 'properties' => ['a' => 'bad']], ['type' => 'array', 'items' => 'bad']] as $definition) {
            try {
                Schema::fromJson($definition);
                self::fail('Invalid schema accepted.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_nullable_required_enum_and_cast_have_explicit_semantics(): void
    {
        $schema = Schema::make('profile')->string('name')->required()->nullable()->description('Optional printed name')->integer('count')->required()->cast(fn ($v) => (int) $v)->boolean('active')->string('code')->regex('^[A-Z]+$');
        self::assertNull($schema->cacheDefinition());
        self::assertSame(['name' => null, 'count' => 2], $schema->normalize(['name' => null, 'count' => '2']));
        self::assertSame([], $schema->errors(['name' => null, 'count' => 2, 'active' => true, 'code' => 'ABC']));
        self::assertContains('code', $schema->errors(['name' => null, 'count' => 2, 'code' => '123']));
        self::assertContains('name', $schema->errors(['count' => 2]));
        self::assertContains('present', $schema->rules()['name']);
    }

    public function test_manual_mapper_handles_nested_numeric_boolean_and_date_fields(): void
    {
        $schema = Schema::make()->object('seller', fn ($s) => $s->string('name'))->integer('count')->number('weight')->boolean('active')->date('date');
        $mapper = new ManualMapper;
        $data = $mapper->extract("name: Shop\ncount: 3\nweight: 2.5\nactive: true\ndate: invalid", $schema->toJsonSchema());
        self::assertSame(['seller' => ['name' => 'Shop'], 'count' => 3, 'weight' => 2.5, 'active' => true], $data);
        self::assertSame('count: 3', $mapper->evidence()['count']);
    }

    public function test_scorer_detects_low_confidence_controls_and_multiple_encoding_failures(): void
    {
        $scorer = app(QualityScorer::class);
        self::assertContains('low_ocr_confidence', $scorer->score(new Page(1, str_repeat('readable ', 8), 'tesseract', 0.4))['reasons']);
        self::assertContains('control_characters', $scorer->score(new Page(1, str_repeat("\x01", 10).'long readable document text'))['reasons']);
        $detector = app(GarbledDetector::class);
        self::assertContains('mojibake', $detector->reasons('Ã©Ã©Ã©Ã©'));
        self::assertContains('replacement_characters', $detector->reasons('bad � text'));
        self::assertContains('expected_arabic_script_missing', $detector->reasons('ordinary Latin text', 'arabic'));
        self::assertSame([], $detector->reasons(''));
    }

    public function test_normalizers_reject_invalid_checksums_and_preserve_integer_arithmetic(): void
    {
        self::assertNull(Normalizer::money('no amount'));
        self::assertNull(Normalizer::money('12345678901234567890'));
        self::assertSame(-10000, Normalizer::money('(100)'));
        self::assertSame(10000, Normalizer::money('100'));
        self::assertSame('+8801712345678', Normalizer::phone('008801712345678'));
        self::assertFalse(Normalizer::luhn('123'));
        self::assertFalse(Normalizer::iban('invalid'));
        self::assertFalse(Normalizer::mrzChecksum('!', '0'));
        self::assertSame([], Passport::parse('invalid'));
    }

    public function test_consensus_weights_agreement_and_ignores_unvalidated_candidates(): void
    {
        $consensus = new FieldConsensus;
        $a = new FieldValue(100, 0.8, engine: 'native', validated: true);
        $b = new FieldValue(200, 0.95, engine: 'ai', validated: true);
        $c = new FieldValue(100, 0.7, engine: 'ai', validated: true);
        self::assertSame(100, $consensus->choose([$a, $b, $c])->value);
        self::assertSame($a, $consensus->choose([$a, new FieldValue(200, 1, validated: false)]));
        $unvalidated = new FieldValue(null);
        self::assertSame($unvalidated, $consensus->choose([$unvalidated]));
        $this->expectException(\InvalidArgumentException::class);
        $consensus->choose([]);
    }

    public function test_currency_parsing_rounding_and_quantity_cross_checks(): void
    {
        $money = Money::class;
        self::assertSame(['minor' => 123450, 'currency' => 'EUR'], $money::parse('€ 1.234,50'));
        self::assertSame(['minor' => 10000, 'currency' => 'AUD'], $money::parse('AUD 100'));
        self::assertSame(['minor' => 10000, 'currency' => null], $money::parse('100'));
        self::assertSame(124, Normalizer::money('1.235'));
        self::assertNull(Normalizer::money('10.00 plus 20.00'));
        self::assertSame(250, $money::multiply(100, '2.5'));
        self::assertSame(-250, $money::multiply(100, '-2.5'));
        self::assertNull($money::multiply(100, 'not numeric'));
        self::assertNull($money::multiply(PHP_INT_MAX, '2'));
        self::assertNull($money::multiply(1, '12345678901234567890'));
        try {
            $money::parse('1', null, 9);
            self::fail('Invalid money precision accepted');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $type = (new TypeRegistry)->get('invoice');
        self::assertContains('items.0.amount', $type->crossChecks(['items' => [['qty' => 2, 'unit_price' => 100, 'amount' => 300]]]));
    }

    public function test_language_statistics_distinguish_repetitive_and_random_encoding_from_prose(): void
    {
        $statistics = new TextStatistics;
        self::assertSame(0.0, $statistics->entropy(''));
        self::assertSame(['low_text_entropy'], $statistics->reasons(str_repeat('a', 120)));
        $garbled = implode(' ', array_fill(0, 25, 'qzxvbnmlkjhgfdsawertyuiop'));
        self::assertSame(['unnatural_latin_language_patterns'], $statistics->reasons($garbled));
        self::assertSame([], $statistics->reasons(str_repeat('the invoice has a total amount and a date for the customer ', 3)));
    }

    public function test_custom_schema_validator_rejects_invalid_business_identifier(): void
    {
        $schema = Schema::make()->string('reference')->validator(fn ($value) => is_string($value) && str_starts_with($value, 'INV-'));
        self::assertSame(['reference'], $schema->errors(['reference' => 'OTHER']));
        self::assertSame([], $schema->errors(['reference' => 'INV-123']));
        self::assertNull($schema->cacheDefinition());
        self::assertSame([], Schema::fromJson(['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'nullable' => true]]])->errors(['name' => null]));
    }

    public function test_nested_schema_callbacks_are_not_lost_when_compiled(): void
    {
        $schema = Schema::make()->array('items', fn ($item) => $item->integer('quantity')->cast(fn ($value) => (int) $value)->validator(fn ($value) => $value > 0));
        self::assertSame(['items' => [['quantity' => 3]]], $schema->normalize(['items' => [['quantity' => '3']]]));
        self::assertSame(['items.0.quantity'], $schema->errors(['items' => [['quantity' => 0]]]));
        self::assertNull($schema->cacheDefinition());
    }
}
