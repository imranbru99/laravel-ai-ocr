# Configuration

Publish with `php artisan vendor:publish --tag=ai-ocr-config`. The authoritative
keys and defaults are in [config/ai-ocr.php](../config/ai-ocr.php). AI provider
credentials belong exclusively to AI Hub. Run `php artisan ai-ocr:doctor` after
changing binaries, language packs or providers.

| Group | Purpose and defaults |
|---|---|
| strategy | `auto`: native extraction, local OCR, then quality-driven AI |
| limits | 25 MiB input, 200 pages, 60 million pixels, 120 seconds/process, 512 MiB process address space where prlimit is available; archive size/count/depth bounds |
| binaries | Explicit absolute paths or trusted directory discovery; whitelist `/usr/bin`, `/usr/local/bin`, `/opt/homebrew/bin` |
| native | Poppler then optional Smalot; layout on, embedded image extraction off, maximum 10 images |
| ocr | Enabled; `eng`, 300 DPI, confidence 70, PSM 3/OEM 1; deskew/grayscale/denoise; concurrency 1 |
| quality | Minimum score .75 and 25 characters/page; garbled detection on; optional expected script |
| ai | Enabled/allowed fallback; Hub default provider/model unless overridden; optional provider allowlist, fast/strong models and consensus models |
| ai images | Images allowed unless text-only; JPEG, quality 85, maximum side 1800; `image_driver`: auto, imagick or gd |
| ai spending | Maximum 30 AI pages and $0.25/document; 4096 output tokens; unknown cloud pricing blocks preflight |
| ai thinking | Transcription off; structured auto (no forced thinking); handwriting on; explicit true enables a 4096-token thinking budget |
| ai cache | 86400 seconds, prompt version v1; encrypted and owner scoped; invalidated by schema/options/model settings |
| privacy | No content logs; optional token redaction; `bd_nid` local-only by default |
| store | Disabled; local private disk; originals off; encryption/text on; prune after 90 days when command is scheduled |
| queue | Host default connection, queue `ocr`, Bus batching enabled |
| webhooks | Disabled; public HTTPS URL, secret and five attempts; signed metadata-only payload |
| studio | Local environment by default; path `ai-ocr`, web middleware; explicit authorization required outside local |
| api | Disabled; prefix `api/ai-ocr`; API, Sanctum authentication and throttling middleware |
| filament | Optional plugin enabled, Studio link opens a new tab |

`testing.live` is false by default; `AI_OCR_LIVE=true` explicitly permits spending
in the separate opt-in live test suite.

Environment shortcuts: `AI_OCR_STRATEGY`, `AI_OCR_AI`, `AI_OCR_STORE`,
`AI_OCR_WEBHOOK_SECRET`, `AI_OCR_STUDIO`, `AI_OCR_ROLES`, `AI_OCR_EMAILS`.
All remaining values are normal Laravel configuration. Cache configuration in
production and restart queue workers after changes.

Binary availability controls capability: PDF rasterization needs Poppler or
Imagick; local OCR needs Tesseract and requested traineddata. Image preprocessing
and packed AI images need Imagick/GD. No codec means validated raster bytes may
be sent unchanged. Install optional PhpSpreadsheet for legacy XLS and Filament
for panel integration. API defaults require the host's Sanctum installation.

Use `manualOnly()` or `AI_OCR_AI=false` for offline operation. `send_text_only`
and redaction policies block image transmission. Provider budget checks are
estimates, so use provider-side spending limits for contractual caps.
