<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Types;

use ImranDevBd\AiOcr\Normalizers\Money;
use ImranDevBd\AiOcr\Normalizers\Normalizer;
use ImranDevBd\AiOcr\Normalizers\Passport;
use ImranDevBd\AiOcr\Schema\Schema;

final class BuiltinType extends DocumentType
{
    public function __construct(private readonly string $type) {}

    public function name(): string
    {
        return $this->type;
    }

    public function schema(): Schema
    {
        $s = Schema::make($this->type);

        return match ($this->type) {
            'invoice', 'receipt', 'purchase_order', 'utility_bill' => $s->string('invoice_number')->required()->date('issue_date')->money('subtotal')->money('tax')->money('discount')->money('total')->required()->string('currency')->enum(['BDT', 'USD', 'AUD', 'EUR', 'GBP', 'INR'])->array('items', fn ($i) => $i->string('description')->number('qty')->money('unit_price')->money('amount'))->string('seller.name')->string('buyer.name'),
            'bd_nid' => $s->string('nid_number')->regex('^(?:[0-9]{10}|[0-9]{13}|[0-9]{17})$')->required()->string('name_bn')->string('name_en')->required()->string('father')->string('mother')->date('birth_date')->required()->string('address'),
            'passport' => $s->string('passport_number')->required()->string('name')->string('nationality')->date('birth_date')->date('expiry_date')->string('mrz')->required(),
            'business_card' => $s->string('name')->required()->string('company')->string('phone')->string('email')->string('url')->string('address'),
            'bank_statement' => $s->string('account_number')->required()->string('bank')->date('period_start')->date('period_end')->money('opening_balance')->money('closing_balance')->array('transactions', fn ($i) => $i->date('date')->string('description')->money('debit')->money('credit')->money('balance')),
            'lab_report' => $s->string('patient')->required()->date('date')->array('tests', fn ($i) => $i->string('name')->string('value')->string('unit')->string('reference_range')),
            'prescription' => $s->string('patient')->string('doctor')->date('date')->array('medications', fn ($i) => $i->string('name')->string('dose')->string('frequency')->string('duration')),
            'resume', 'cv' => $s->string('name')->required()->string('email')->string('phone')->string('summary')->array('experience', fn ($i) => $i->string('employer')->string('role')->string('dates'))->array('education', fn ($i) => $i->string('institution')->string('degree')),
            'contract_basic' => $s->string('title')->required()->array('parties', fn ($i) => $i->string('name'))->date('effective_date')->date('end_date')->string('summary'),
            'id_card', 'driving_license', 'bd_trade_license' => $s->string('number')->required()->string('name')->required()->date('birth_date')->date('issue_date')->date('expiry_date')->string('address')->string('issuer'),
            'cheque' => $s->string('cheque_number')->required()->string('bank')->string('payee')->money('amount')->required()->date('date'),
            'shipping_label', 'bill_of_lading', 'packing_list' => $s->string('reference')->required()->string('sender')->string('recipient')->string('address')->string('tracking_number')->array('items', fn ($i) => $i->string('description')->number('quantity')->number('weight')),
            'table_only' => $s->array('rows', fn ($i) => $i->string('cells'))->required(),
            'generic_key_value' => $s->array('entries', fn ($i) => $i->string('key')->required()->string('value')->required())->required(),
            'handwritten_note' => $s->string('text')->required()->string('language'),
            default => throw new \InvalidArgumentException('Unknown built-in document type: '.$this->type),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function crossChecks(array $data): array
    {
        $failed = [];
        foreach ($data['items'] ?? [] as $index => $item) {
            if (isset($item['qty'],$item['unit_price'],$item['amount']) && is_int($item['unit_price']) && is_int($item['amount'])) {
                if (Money::multiply($item['unit_price'], (string) $item['qty']) !== $item['amount']) {
                    $failed[] = 'items.'.$index.'.amount';
                }
            }
        }

        if (isset($data['subtotal'], $data['total']) && is_int($data['subtotal']) && is_int($data['total'])) {
            if ($data['total'] !== $data['subtotal'] + ($data['tax'] ?? 0) - ($data['discount'] ?? 0)) {
                $failed = [...$failed, 'subtotal', 'tax', 'discount', 'total'];
            }
        }
        if (isset($data['items'], $data['subtotal']) && is_array($data['items']) && $data['items'] !== [] && array_sum(array_column($data['items'], 'amount')) !== $data['subtotal']) {
            $failed[] = 'items';
            $failed[] = 'subtotal';
        }
        if (isset($data['issue_date'], $data['expiry_date']) && $data['issue_date'] > $data['expiry_date']) {
            $failed[] = 'expiry_date';
        }
        if ($this->type === 'passport' && isset($data['mrz'])) {
            $lines = preg_split('/\s+/', trim((string) $data['mrz'])) ?: [];
            $line = (string) end($lines);
            if (strlen($line) !== 44 || ! Normalizer::mrzChecksum(substr($line, 0, 9), $line[9] ?? '') || ! Normalizer::mrzChecksum(substr($line, 13, 6), $line[19] ?? '') || ! Normalizer::mrzChecksum(substr($line, 21, 6), $line[27] ?? '') || ! Normalizer::mrzChecksum(substr($line, 0, 10).substr($line, 13, 7).substr($line, 21, 22), $line[43] ?? '')) {
                $failed[] = 'mrz';
            }
        }

        return array_values(array_unique($failed));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        if ($this->type === 'passport' && isset($data['mrz']) && is_string($data['mrz'])) {
            return array_replace(Passport::parse($data['mrz']), $data);
        }
        if ($this->type === 'bd_nid' && isset($data['nid_number']) && is_string($data['nid_number'])) {
            $data['nid_number'] = preg_replace('/\s/', '', Normalizer::text($data['nid_number']));
        }

        return $data;
    }

    public function promptHints(): string
    {
        return 'Extract '.$this->type.'. Monetary fields are integer minor units (100 per major unit). Do not invent missing values.';
    }
}
