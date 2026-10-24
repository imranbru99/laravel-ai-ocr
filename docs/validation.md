# Version 1.1.0 validation

Validated locally on 2026-10-25 by running the package, not by assuming the hosted
CI result. These local checks support the v1.1.0 release. All AI paths in the default
suite use fakes and block stray HTTP calls.

## Main checks

| Check | Result |
|---|---|
| Main suite, PHP 8.5.4 / Laravel 13.38.0 with Imagick/GD | 103 tests, 383 assertions, one optional Filament test skipped |
| PHPStan / Larastan, level 8 on current dependencies | Zero errors |
| Laravel Pint | Passed with both current and PHP-8.2-compatible formatter versions |
| Composer strict validation | Passed |
| Separate live suite without opt-in | Skipped as intended; no provider spending |
| Source ZIP | Built and integrity checked; SHA-256 in dist/SHA256SUMS |
| Studio browser check | Authorization enforced; schema builder adds a required field and applies it to Playground |
| Fresh application installation | Laravel 10, 11, 12 and 13: dependency resolution, package discovery, doctor and types commands passed |

The default host suite without optional image codecs passes 103 tests / 378
assertions with three skips. Real Imagick/GD paths were separately exercised in
the coverage run. Optional Filament runs exercise the remaining integration.

## Runtime matrix

Each cell below ran the complete 103-test package suite (378 assertions, three
optional-dependency skips) and formatting inside disposable PHP CLI containers.

| PHP | Laravel 10 | Laravel 11 | Laravel 12 | Laravel 13 |
|---|---|---|---|---|
| 8.2.35 | Pass | Pass | Pass | Unsupported by framework |
| 8.3.36 | Pass | Pass | Pass | Pass |
| 8.4 | Pass | Pass | Pass | Pass |

The prefer-lowest / prefer-stable combination on PHP 8.2 + Laravel 10 also passes
103 tests / 378 assertions. Minimum-dependency testing checks runtime compatibility;
formatting uses current compatible Pint, whose rules differ from early releases.
Filament 3 and 4, each installed alongside Laravel 12 / PHP 8.3, pass 103 tests /
382 assertions with two image-codec skips, plus formatting.

Laravel 10/11 dependency resolution requires `--no-security-blocking` in these
isolated historical tests because Composer reports upstream advisories. Consumer
security defaults remain enabled. These cells prove runtime compatibility and do
not certify the historical dependencies as safe for deployment. Current root
lockfile refresh reported no security vulnerability advisories.

## Enforced line coverage

PCOV 1.0.12 measured executable statements with real optional image codecs loaded.
`tools/check-coverage.php` enforces at least 90% independently in every group:

| Area | Covered / executable | Coverage |
|---|---|---|
| Pipeline | 299 / 331 | 90.33% |
| Quality | 86 / 88 | 97.73% |
| Schema | 202 / 208 | 97.12% |
| Normalizers | 98 / 101 | 97.03% |
| AI drivers | 97 / 99 | 97.98% |

Coverage is not an OCR accuracy measurement. Tests cover native and scanned PDFs,
failed-page fallback, legacy-text corruption, nested schema callbacks, structured
repair and grounding, money/MRZ/NID normalization, consensus, privacy, budgets,
malicious archives/URLs/process arguments, encrypted cache/storage, ownership,
queues/page merging, review/rerun, webhooks and the 300-page memory regression.

## Evidence and reproduction

Sanitized local logs and Clover report are copied into `dist/validation/`. Re-run
checks using [release instructions](release.md). GitHub Actions contains the
runtime matrix, coverage gate and optional Filament jobs; the first hosted run was blocked before runner execution by an account billing
lock. No hosted CI success is claimed. The source archive intentionally
omits tests/docs according to the requested distribution policy; the full local
workspace includes both. [Scope boundaries](implementation-status.md) list the
remaining deployment-dependent limits explicitly.

## Visual provider setup regression checks

The v1.1.0 suite adds seven tests for visual forms, custom Hub route prefixes,
secret non-disclosure, configuration-only readiness, disabled UI/allowlists,
Hub-backed saving, preserved blank keys, and independent Hub authorization.
Browser checks used synthetic credentials to save providers, change the default,
refresh status and select the provider/model in Playground. Password fields
cleared after saving; no billable inference request was sent.

The full 11-cell runtime matrix was repeated for this feature and passed with
103 tests / 378 assertions and three optional-dependency skips per cell.
