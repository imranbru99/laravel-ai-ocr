<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Quality;

use ImranDevBd\AiOcr\DTO\Page;

final class QualityScorer
{
    public function __construct(private readonly GarbledDetector $garbled) {}

    /** @return array{score: float, reasons: list<string>, garbled: bool} */
    public function score(Page $page, ?string $script = null): array
    {
        $text = trim($page->text);
        $reasons = [];
        $score = 1.0;
        if (mb_strlen($text) < config('ai-ocr.quality.min_chars_per_page', 25)) {
            $score -= 0.6;
            $reasons[] = 'near_empty';
        }
        $bad = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text);
        if ($bad > max(1, strlen($text) * 0.02)) {
            $score -= 0.5;
            $reasons[] = 'control_characters';
        }
        $garbled = config('ai-ocr.quality.detect_garbled', true) ? $this->garbled->reasons($text, $script ?? config('ai-ocr.quality.expected_script')) : [];
        if ($garbled !== []) {
            $score -= 0.8;
            $reasons = array_merge($reasons, $garbled);
        }
        if ($page->engine === 'tesseract') {
            $score = min($score, $page->confidence);
            if ($page->confidence < config('ai-ocr.ocr.min_confidence', 70) / 100) {
                $reasons[] = 'low_ocr_confidence';
                $score = min($score, 0.5);
            }
        }
        if ($page->engine === 'native' && $page->confidence < 0.75) {
            $reasons[] = 'unverified_native_text';
        }
        if ($page->engine === 'ai' && $page->confidence < 0.75) {
            $reasons[] = 'low_ai_confidence';
        }
        if (in_array($page->engine, ['native', 'ai'], true)) {
            $score = min($score, $page->confidence);
        }

        return ['score' => max(0.0, $score), 'reasons' => $reasons, 'garbled' => $garbled !== []];
    }
}
