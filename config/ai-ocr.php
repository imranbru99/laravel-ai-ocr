<?php

declare(strict_types=1);

return [
    'testing' => ['live' => env('AI_OCR_LIVE', false)],
    'strategy' => env('AI_OCR_STRATEGY', 'auto'),
    'limits' => ['max_size_mb' => 25, 'max_pages' => 200, 'max_pixels' => 60000000, 'process_timeout' => 120, 'process_memory_mb' => 512, 'zip_max_uncompressed_mb' => 100, 'zip_max_entries' => 10000, 'max_attachment_depth' => 3],
    'binaries' => ['pdftotext' => null, 'pdftoppm' => null, 'pdfinfo' => null, 'pdfimages' => null, 'prlimit' => null, 'tesseract' => null, 'whitelist' => ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin']],
    'native' => ['drivers' => ['pdftotext', 'smalot'], 'layout' => true, 'extract_images' => false, 'max_embedded_images' => 10],
    'ocr' => ['enabled' => true, 'languages' => ['eng'], 'dpi' => 300, 'min_confidence' => 70, 'psm' => 3, 'oem' => 1, 'preprocess' => ['deskew', 'grayscale', 'denoise'], 'concurrency' => 1],
    'quality' => ['min_score' => 0.75, 'min_chars_per_page' => 25, 'detect_garbled' => true, 'expected_script' => null],
    'ai' => ['enabled' => env('AI_OCR_AI', true), 'allow' => true, 'mode' => 'fallback', 'provider' => null, 'models' => ['fast' => null, 'strong' => null], 'allowed_providers' => null, 'send_images' => true, 'send_text_only' => false, 'image_max_side' => 1800, 'image_format' => 'jpeg', 'image_driver' => 'auto', 'image_quality' => 85, 'max_pages_per_document' => 30, 'max_cost_per_document' => 0.25, 'thinking' => ['transcribe' => false, 'structured' => 'auto', 'handwriting' => true], 'consensus_models' => [], 'job_tag' => 'ai-ocr', 'cache_ttl' => 86400, 'prompt_version' => 'v1', 'max_output_tokens' => 4096, 'estimated_image_tokens' => 4000],
    'privacy' => ['redact' => [], 'policies' => ['bd_nid' => 'local_only'], 'log_content' => false],
    'store' => ['enabled' => env('AI_OCR_STORE', false), 'disk' => 'local', 'retain_original' => false, 'encrypt' => true, 'store_text' => true, 'prune_days' => 90],
    'queue' => ['connection' => null, 'queue' => 'ocr', 'batch' => true],
    'webhooks' => ['enabled' => false, 'url' => null, 'secret' => env('AI_OCR_WEBHOOK_SECRET'), 'tries' => 5],
    'studio' => ['enabled' => env('AI_OCR_STUDIO'), 'path' => 'ai-ocr', 'middleware' => ['web'], 'roles' => env('AI_OCR_ROLES'), 'emails' => env('AI_OCR_EMAILS')],
    'api' => ['enabled' => false, 'prefix' => 'api/ai-ocr', 'middleware' => ['api', 'auth:sanctum', 'throttle:30,1']],
    'filament' => ['enabled' => true, 'new_tab' => true],
];
