# Schemas and field evidence

```php
use ImranDevBd\AiOcr\Schema\Schema;

$schema = Schema::make('invoice')
    ->string('invoice_number')->required()->description('Printed invoice ID')
    ->date('issue_date')->nullable()
    ->money('total')->required()
    ->string('currency')->enum(['BDT','USD'])
    ->object('seller', fn ($s) => $s->string('name')->required())
    ->array('items', fn ($i) => $i->string('description')->integer('quantity'));
```

Modifiers apply to the most recently added field. Dotted fields create nested
objects. `money()` stores integer minor units; `number()` may use floating-point
numbers for non-monetary measurements. `nullable()` accepts null when a property
is present; `required()` determines property presence. Custom `cast(callable)`
transforms manual values and disables caching for that schema because callable
semantics cannot be fingerprinted safely.

`toJsonSchema()`, `rules()`, `errors($data)` and `normalize($data)` expose the
compiler/validation boundaries. `->as($jsonSchemaArray)` accepts a deliberately
bounded JSON Schema subset: types, objects/properties, required, arrays/items,
enums, nullable types, pattern, description, numeric minimum/maximum, string
length, array length and date/email/URI formats. Unsupported keywords throw
rather than silently pretending to enforce them. Object schemas reject extra
keys. References, combinators and external schemas are not supported yet.

Manual mapping recognizes labeled fields and common invoice/NID labels. It does
not invent table fields or infer arbitrary business facts. Missing or invalid
fields trigger a text-only AI request restricted to their root properties.
Returned scalar fields need evidence with an exact quote on the cited page;
unverified fields are rejected. Schema and Laravel rules are re-run after repair.
A source quote proves textual grounding, not the semantic truth of a document.

Use `->validate(['total'=>'integer|min:0'])` for application rules. Complex arrays
and cross-field arithmetic belong in a custom document type. Inspect
`$result->field('total')->validated`, confidence and warnings before persisting
business decisions. `field()` returns a FieldValue with value, page, engine,
confidence and source_quote.
