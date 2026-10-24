# Contributing

Install PHP 8.2+ and Composer, then run `composer install`, `composer test`,
`vendor/bin/pint --test` and `composer analyse`. Poppler and Tesseract are needed
for native PDF and OCR integration tests. See docs/installation-binaries.md.

Add a synthetic regression fixture for every bug. Use `AIHub::fake()` or Laravel's
HTTP fake; never make provider network calls in CI. Do not add private documents
or secrets. Optional drivers must remain feature-detected. Keep monetary arithmetic
in integer minor units. Review SECURITY.md before changing input or AI boundaries.

Developer identity is Imran Ahmed <me@imrandev.bd>, https://imrandev.bd. Before each
new commit, verify `git config user.name`, `git config user.email`,
`git var GIT_AUTHOR_IDENT`, and `git var GIT_COMMITTER_IDENT`. Preserve third-party
notices and existing history. Release only after the supported matrix passes.
