# Replacing drivers

The pipeline resolves the following contracts through Laravel's container:

- `Contracts\NativeExtractor::extract(Document): array` returns Page objects.
- `Contracts\OcrEngine::recognize(path, number, languages): Page` returns words/confidence.
- `Contracts\Rasterizer::page(Document, page, dpi, Temp): string` returns a private image path.
- `Contracts\AiExtractor::generate(text, schema, options, image): array` returns data, usage, provider/model.
- `Contracts\ProviderReadiness::ready(): array` returns ready configured provider names.

Bind a replacement before the manager is resolved:

```php
$this->app->bind(
    \ImranDevBd\AiOcr\Contracts\OcrEngine::class,
    MyTesseractEngine::class,
);
```

Native drivers must enforce page counts and return original page numbers. OCR
engines must use normalized 0–1 confidence, not Tesseract's 0–100 values. Never
execute filenames through a shell. Replacements own their security guarantees;
use the shared Resolver, Binaries and Temp helpers where appropriate.

`AiOcr::extend('my-driver', fn ($app) => $app->make(MyDriver::class))` registers a
named application extension retrievable with `AiOcr::driver('my-driver')`.
To replace the pipeline's driver, bind its contract; a named extension alone
does not replace an already constructed manager dependency.

AI driver options contain job, type, hints, provider/model, redactions, cache TTL
and remaining cost. Return actual cost/token/latency usage even after semantic
retries. Throw an AiOcrException carrying accumulated usage when a paid attempt
fails validation. Keep provider transport retries in the Hub.
