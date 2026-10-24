# Application recipes

## Import an invoice into Eloquent

```php
$result = AiOcr::file($request->file('invoice'))->as('invoice')->extract();
if (!$result->field('total')->validated) {
    return back()->withErrors(['invoice' => implode(' ', $result->warnings())]);
}
Invoice::create([
    'number' => $result->field('invoice_number')->value,
    'total_minor' => $result->field('total')->value,
]);
```

Map only application-owned fillable attributes; never mass-assign arbitrary AI
output. Use database transactions around your own related records.

## Bangla NID review

```php
$result = AiOcr::file($privatePath)->as('bd_nid')
    ->languages(['ben','eng'])->manualOnly()->extract();
```

Keep the default local-only NID policy. Local OCR needs ben.traineddata. Number
shape validation is not identity verification; route uncertain fields to your
application's review workflow. Do not publish sample NIDs in issue reports.

## Receipts to accounting

```php
$result = AiOcr::file($receipt)->as('receipt')
    ->validate(['total'=>'integer|min:0'])->forJob('receipt-import')->extract();
$amountMinor = $result->field('total')->value;
```

Amounts are minor units. Choose an explicit currency before posting ledger entries.
Warnings and cross-check failures should block automatic accounting decisions.

## Batch folder imports

```php
$files = glob(storage_path('app/private/imports/*.pdf'));
$batch = AiOcr::files($files)->using('auto')
    ->then(function ($batch) { /* application notification */ })
    ->catch(function ($batch, $error) { report($error); })
    ->dispatch();
```

Queued batch callbacks follow Laravel Bus batch signatures. Sync batch callbacks
receive result arrays, exception and finally results respectively. Install
Laravel's job_batches table, configure a queue connection and start workers.
Do not delete source files until all retries finish. Jobs are encrypted and
unique outside Bus batches; Laravel batch behavior governs duplicate jobs inside
a batch. OCR does not send an email or notification on its own.

## Filament upload action

Store the upload privately, then call the same facade from a Filament action.
Register `AiOcrPlugin::make()` on your panel for navigation to the protected Studio.
The plugin registers an owner-scoped extraction resource and statistics widget.
Use application resources to display only records the current user owns.

## Persist results and prune

Publish migrations, run `php artisan migrate`, then set AI_OCR_STORE=true. Results
and page text are encrypted by default. Schedule `ai-ocr:prune --days=90` in your
host app; no scheduler entries are registered automatically. The HasOcrDocuments
trait exposes owner document/extraction relations and an `ocr()` request. `save($model)` writes `ocr_data` on that model, so supply
a matching cast/column in your application.

## Completion webhooks

Set webhooks.enabled, a public HTTPS URL and a random 32+ byte secret. Start queue
workers. Headers contain X-Ai-Ocr-Timestamp, X-Ai-Ocr-Event and X-Ai-Ocr-Signature.
Verify the raw body with `Support\WebhookVerifier`, then retrieve the stored
result through your authenticated application endpoint. Handle duplicate event
IDs idempotently. No document content is included in the webhook body.
