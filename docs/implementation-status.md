# Implementation and release status

The package implementation is complete as a local release candidate. It includes
native extraction, local OCR, quality gates, scoped AI fallback, schemas and 23
built-in types, grounded fields, verification/consensus, private storage, cache,
queues and page batches, API, Studio, Filament, commands, fakes and documentation.
See [validation](validation.md) for measured checks and [file tree](file-tree.md)
for the delivered source inventory.

## Deliberate differences and boundaries

- The installed AI Hub v1.6 source takes precedence over the supplied dependency
  description. Hub settings are not encrypted upstream; its generic exceptions,
  image transport and failover require the documented adapters. OCR does not
  claim to fix upstream credential storage. See [integration](ai-hub-integration.md).
- DTO instance properties are readonly; the result class supports Laravel's
  Macroable trait, whose static property prevents a PHP readonly class declaration.
- Native format parsers share a composable extractor rather than one class per
  extension. Built-in types share tested schema/normalization logic.
- Raw JSON Schema is a validated bounded subset. Unsupported references,
  combinators and external schemas fail explicitly. PDF AcroForm extraction is
  best-effort for literal field values; arbitrary PDF form encodings and encrypted
  PDF passwords are not advertised as supported.
- Studio uses bundled plain JavaScript/CSS instead of CDN Alpine/Tailwind. It needs
  no Node build, external asset request or CDN availability. Compare displays
  separate candidates; confidence words are highlighted rather than geometrically
  overlaid on the PDF. Reruns require opt-in encrypted original retention.
- Cost preflight is an estimate. Strict billing guarantees require provider-side
  limits or transactional reservations. Accuracy requires annotated ground truth;
  analytics accuracy uses human reviews, and benchmarks report measured throughput.
- The synthetic memory regression covers 300 pages, with limits explicitly raised.
  Results retain text/trace; the pipeline releases page bitmaps but does not promise
  constant memory for arbitrarily large output. No universal OCR accuracy or speed
  is claimed, and no billable live-provider calls were made.
- Optional codecs/libraries and system binaries determine format capability.
  Missing capabilities produce diagnostics or partial results. Filament is optional.
- Laravel 10/11 historical compatibility tests use Composer's isolated
  `--no-security-blocking` option because dependency advisories prevent normal
  historical resolution. The package does not disable security checks for consumers.
  Runtime compatibility is not a recommendation to deploy vulnerable dependencies.
- Level-8 analysis uses the current Laravel/Larastan toolchain. Historical Larastan
  has incompatible relation generic arity. Runtime tests and formatting cover the
  supported matrix; minimum-dependency testing excludes obsolete formatter rules.

## Git and publication

The original workspace contains a protected, read-only `.git` directory without
usable Git metadata. Publishing uses a separate writable clone of
`https://github.com/imranbru99/laravel-ai-ocr`, preserving its existing remote
history and copyright notice. The original protected directory remains untouched.
Both author and committer are verified as Imran Ahmed <me@imrandev.bd> before
committing. A source push does not create a stable release tag or publish to
Packagist; those are separate release steps.

The local archive is generated with `php tools/build-release.php`; its SHA-256 is
written to `dist/SHA256SUMS`. It contains production sources and first-party
metadata, with vendor dependencies, credentials, tests and Git metadata excluded.
Follow [release steps](release.md) in a writable Git checkout to publish.
