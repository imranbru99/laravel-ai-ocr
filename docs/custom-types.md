# Custom document types

```php
use ImranDevBd\AiOcr\Types\DocumentType;
use ImranDevBd\AiOcr\Schema\Schema;

final class PurchaseTicket extends DocumentType
{
    public function name(): string { return 'purchase_ticket'; }
    public function schema(): Schema {
        return Schema::make($this->name())->string('reference')->required()
            ->money('subtotal')->money('tax')->money('total')->required();
    }
    public function rules(): array { return ['total'=>'integer|min:0']; }
    public function crossChecks(array $data): array {
        if (!isset($data['subtotal'],$data['tax'],$data['total'])) { return []; }
        return $data['subtotal'] + $data['tax'] === $data['total'] ? [] : ['total'];
    }
    public function normalize(array $data): array { return $data; }
    public function promptHints(): string { return 'The reference is printed above the item list.'; }
}

AiOcr::registerType(PurchaseTicket::class);
$result = AiOcr::file($file)->as('purchase_ticket')->extract();
```

Registration lives in the singleton registry, not static package state. Pass an
instance to configure a type at runtime, or `->as(PurchaseTicket::class)` to use
it directly. `crossChecks()` returns failing field paths; they lower confidence
and become repair targets. Native normalizers run before schema checks.

Built-ins are defined in `Types/BuiltinType.php`. Invoice-style types reconcile
subtotal/tax/discount/total and item amounts when those values exist. Passport
TD3 parsing verifies field checksums before deriving names, dates and numbers;
the type cross-check additionally checks the composite digit. Birth-century
inference is heuristic for two-digit MRZ years. Bangladesh NID uses digit-count
validation for 10, 13 or 17 ASCII digits; it does not query a government registry.

Declare privacy policies by registered name:

```php
config(['ai-ocr.privacy.policies.purchase_ticket' => 'local_only']);
```

`autoDetectType()` currently uses text heuristics for common document families.
It does not send unknown documents to a classification model automatically.
