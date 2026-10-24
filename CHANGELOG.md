# Changelog

All notable changes follow Keep a Changelog and semantic versioning.

## [Unreleased]

## [1.1.0] - 2026-10-25

### Added
- Visual AI Providers tab inside OCR Studio with password key fields, model selection, enabled/default controls and provider configuration status.
- Direct use of AI Hub's protected settings endpoint; existing authorization, storage and custom route prefixes remain in effect.
- Refresh status and Use in Playground actions without discarding the current document.
- Visual setup guide, real provider-panel screenshot and regression tests for secret non-disclosure, blank-key preservation and independent Hub permissions.

### Validation
- 103 tests / 383 assertions with optional image codecs; one optional Filament skip. Core coverage gates remain above 90%.
- All 11 supported PHP/Laravel runtime combinations pass the expanded 103-test suite and formatting.
- Saving provider settings sends no inference request; browser verification used synthetic credentials only.

## [1.0.0] - 2026-10-25

### Added
- Manual-first document pipeline with native parsers, local Tesseract and page/field AI fallback.
- Strict schema validation, document types, integer money, digit/date normalization and checksums.
- Provider readiness, policy-constrained failover, reversible redaction and grounded field evidence.
- Private temporary storage, DNS-pinned URL downloads, archive limits and shell-free subprocesses.
- Encrypted cache/persistence, encrypted queued jobs, document batches, events and signed webhooks.
- Blade Studio, schema builder, template storage, protected HTTP API and eight Artisan commands.
- Testbench tests, Pint, PHPStan/Larastan configuration, CI matrix and development Docker image.

### Documentation
- Comprehensive feature guide, business workflows and clearly labeled time-saving examples.
- Actual Studio screenshots, contact information and a production-readiness checklist.
- Stable release badge and Composer installation instructions for the 1.x series.

### Validation
- Local runtime matrix, fresh Laravel installs, optional Filament integrations, static analysis and coverage gates passed as documented in docs/validation.md.
- Hosted GitHub Actions did not start because of the account billing lock. No hosted CI success is claimed.

See docs/implementation-status.md for deployment-dependent boundaries.

[Unreleased]: https://github.com/imranbru99/laravel-ai-ocr/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/imranbru99/laravel-ai-ocr/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/imranbru99/laravel-ai-ocr/releases/tag/v1.0.0
