<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Types;

final class TypeRegistry
{
    /** @var array<string, DocumentType> */
    private array $types = [];

    public function __construct()
    {
        foreach (['invoice', 'receipt', 'purchase_order', 'bank_statement', 'utility_bill', 'bill_of_lading', 'packing_list', 'business_card', 'resume', 'cv', 'contract_basic', 'lab_report', 'prescription', 'id_card', 'bd_nid', 'passport', 'driving_license', 'bd_trade_license', 'cheque', 'shipping_label', 'table_only', 'generic_key_value', 'handwritten_note'] as $name) {
            $this->register(new BuiltinType($name));
        }
    }

    public function register(DocumentType|string $type): void
    {
        if (is_string($type)) {
            $type = new $type;
        } if (! $type instanceof DocumentType) {
            throw new \InvalidArgumentException('Document types must extend DocumentType.');
        } $this->types[$type->name()] = $type;
    }

    public function get(string $name): DocumentType
    {
        return $this->types[$name] ?? throw new \InvalidArgumentException('Unregistered document type: '.$name);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->types);
    }

    public function detect(string $text): ?string
    {
        foreach (['bd_nid' => '/জাতীয় পরিচয়|national identity|NID\s*(?:no|number)/iu', 'passport' => '/P<[A-Z]{3}/', 'invoice' => '/\binvoice\b/i', 'receipt' => '/\breceipt\b/i', 'bank_statement' => '/opening balance|bank statement/i', 'prescription' => '/prescription|dosage/i'] as $type => $pattern) {
            if (preg_match($pattern, $text)) {
                return $type;
            }
        }

        return null;
    }
}
