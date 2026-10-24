# Publish the package

Version 1.0.0 is the initial stable GitHub release. These steps describe the release process. Review [validation](validation.md) and
[scope boundaries](implementation-status.md) before creating a stable release.
The local protected `.git` directory must not be replaced to bypass permissions;
use a normal writable checkout of `imranbru99/laravel-ai-ocr`.

1. Copy the reviewed package source into that checkout without replacing existing
   Git history. Keep API credentials and `vendor/` out of Git.
2. Verify `composer validate --strict --no-check-publish`, `composer check`,
   the complete GitHub Actions matrix, optional Filament jobs and coverage gates.
   Historical framework advisories must remain visible. Prefer supported, patched
   framework versions for production deployment.
3. Configure the developer identity if missing. Before **each** commit run:

   ```bash
   git config user.name
   git config user.email
   git var GIT_AUTHOR_IDENT
   git var GIT_COMMITTER_IDENT
   ```

   Both effective identities must be `Imran Ahmed <me@imrandev.bd>`. Correct repo
   overrides and environment overrides if necessary. Missing global defaults:

   ```bash
   git config --global user.name "Imran Ahmed"
   git config --global user.email "me@imrandev.bd"
   ```

4. Update CHANGELOG from Unreleased to `1.0.0` with the actual release date. Commit
   the reviewed implementation with the verified identity. Push the branch and
   let the hosted CI finish successfully. No hosted CI result is implied by the
   locally executed matrix.
5. Create and push the annotated tag:

   ```bash
   git tag -a v1.0.0 -m "Release v1.0.0"
   git push origin v1.0.0
   ```

6. Submit the GitHub repository URL through Packagist's Submit workflow, or update
   its existing package entry. Configure the Packagist GitHub integration/webhook
   for future updates. The Composer package name is `imrandevbd/laravel-ai-ocr`;
   Composer derives its version from tags, so do not add a hardcoded version.
7. Verify installation using `composer require imrandevbd/laravel-ai-ocr:^1.0`
   in a disposable Laravel application, run `ai-ocr:doctor`, and extract the
   synthetic fixture with manual-only mode. Attach release notes and any desired
   archive/checksum to the GitHub release.

## Reproduce local checks

```bash
composer install
composer check
# With Xdebug or PCOV, plus Imagick/GD for optional image paths:
vendor/bin/phpunit --coverage-clover coverage.xml
php tools/check-coverage.php coverage.xml
php tools/build-release.php
```

The default test suite blocks stray HTTP calls. The separate live suite is off
by default; running it with `AI_OCR_LIVE=true` can spend money using configured
AI Hub providers. Supply only synthetic content and configure pricing/caps first:

```bash
AI_OCR_LIVE=true vendor/bin/phpunit -c phpunit-live.xml --group live
```

`docker/run-matrix.sh` is intended for the test image with source mounted at
`/source` and accepts Laravel 10, 11, 12 or 13. Set `DEPENDENCIES=lowest` for the
minimum dependency run, or `FILAMENT_VERSION=3` / `4` for optional integrations.
`docker/smoke-install.sh` checks package discovery in fresh application skeletons.
Container builds and runs may require `--network=host` with rootless Podman.
