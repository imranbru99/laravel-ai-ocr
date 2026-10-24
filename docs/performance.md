# Performance and cost

Native PDF text is processed one page per Poppler invocation. OCR rasterization
also processes one page at a time and releases image handles. Results retain page
text and trace in memory; they do not retain every page bitmap. Page streaming
uses a Fiber to yield pipeline pages before final schema work and persistence.
Native page extraction currently completes before the first stream yield.

The fixture suite exercises 300 text-PDF pages under a 64 MiB incremental PHP
memory ceiling with max_pages raised to 350. This is a small synthetic corpus,
not evidence of production throughput or decompression-bomb immunity. Default
max_pages is 200. Native image OCR performance depends on DPI, CPU, language packs
and preprocessing. No universal native/OCR/AI pages-per-second number is claimed.

```bash
php artisan ai-ocr:benchmark /private/fixtures
# Explicitly allow provider spending:
php artisan ai-ocr:benchmark /private/fixtures --ai
```

The command reports measured pages, seconds, throughput, cost and confidence.
It cannot calculate accuracy without ground-truth annotations. AI should be
benchmarked only on a license-clean synthetic corpus with a configured cap.

OCR concurrency defaults to one process. Increase `ocr.concurrency` to run bounded
Tesseract process pools. Document-level Bus batches distribute separate files;
`parallel()->dispatch()` distributes pages and merges them in source order before
final schema validation. Use shared private source storage and a batch-capable
queue connection. Parallel page jobs divide the document AI cap among pages.

Cache keys include source SHA-256, schema/options, OCR configuration, Hub model
configuration, owner scope and prompt version. Cache entries are encrypted;
setting noCache disables cache and persistent dedupe. Callable schema casts and
custom type objects disable automatic caching. Stored fingerprints allow dedupe
after cache eviction within the requested TTL. Dedupe is not an atomic reservation
against concurrent submissions of the same file.

Cloud calls require known Hub model prices. Cost preflight estimates text/image
input plus bounded maximum output and blocks insufficient budgets. It is a
heuristic: provider image tokenization is not a guaranteed bound. Track actual
`result->cost()`; semantic failures still contribute usage. A strict contractual
billing cap requires provider-side limits or a transactional reservation service.

Run `composer analyse` for level-8 static checks. CI measures coverage with Xdebug
and enforces 90% line coverage independently for Pipeline, Quality, Schema,
Normalizers and AI drivers. Local validation used PCOV with Imagick and GD.
See [validation](validation.md) for measured results.
