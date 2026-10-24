# Installing document tools

The package uses native tools only through Symfony Process argument arrays. The
configured whitelist must contain the resolved binary directory; symlinks are
resolved before acceptance. Run `php artisan ai-ocr:doctor` under the **same user
and PHP CLI** used by queue workers.

## Debian / Ubuntu / FastPanel VPS

```bash
sudo apt-get update
sudo apt-get install poppler-utils tesseract-ocr tesseract-ocr-eng tesseract-ocr-ben
php -m
php artisan ai-ocr:doctor
```

Enable fileinfo and mbstring in PHP. Office input needs zip/dom; URL input needs
curl. Install the PHP-version-specific packages for your OS, such as php8.3-zip
and php8.3-xml. Imagick or GD enables preprocessing. A FastPanel site's FPM PHP
version may differ from `/usr/bin/php`: configure Supervisor/systemd to use the
matching CLI executable and run the worker as the site user. Give that user
access to private uploads and Laravel storage, not the whole home directory.
Restart workers after PHP extension or config changes. Use persistent private
storage, not the HTTP upload temporary directory, for queued jobs.

## macOS

```bash
brew install poppler tesseract tesseract-lang
```

Homebrew Apple Silicon normally uses `/opt/homebrew/bin`, already in the default
whitelist. Check installed languages with `tesseract --list-langs`.

## Windows

Install trusted Poppler and Tesseract builds, and the required `.traineddata`
language files. Set absolute binary paths in `ai-ocr.binaries` and add their
resolved parent directories to `binaries.whitelist`. No shell escaping is needed
for spaces in paths. Verify that the web/worker user can execute the binaries.
The Windows toolchain has not been exercised in this development environment.

## Docker

```bash
docker compose -f docker/compose.yml run --build --rm tests
```

The image installs PHP, Poppler, Tesseract, English/Bangla data and Composer.
Use host-specific UID mapping when necessary to prevent root-owned build files.
The image is for development/testing; a production image should contain only
runtime dependencies and an isolated private upload volume.

## Optional packages

- `smalot/pdfparser`: alternate native PDF text extraction.
- `phpoffice/phpspreadsheet`: legacy XLS support; native XLSX does not require it.
- `ext-imagick`: PDF/image rasterization fallback, TIFF/HEIC, deskew/threshold.
- `ext-gd`: grayscale/contrast/smoothing when Imagick is unavailable.
- `filament/filament`: optional navigation plugin; never needed for base installs.

Missing optional tools produce doctor diagnostics or extraction warnings. Simple
PDF fallback is intentionally low-confidence and does not decode arbitrary fonts.
