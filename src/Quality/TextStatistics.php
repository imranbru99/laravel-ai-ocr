<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Quality;

final class TextStatistics
{
    public function entropy(string $text): float
    {
        $characters = preg_split('//u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($characters === []) {
            return 0.0;
        } $counts = array_count_values($characters);
        $entropy = 0.0;
        foreach ($counts as $count) {
            $p = $count / count($characters);
            $entropy -= $p * log($p, 2);
        }

        return $entropy;
    }

    /** @return list<string> */
    public function reasons(string $text): array
    {
        preg_match_all('/[A-Za-z]{3,}/', $text, $matches);
        $words = array_map('strtolower', $matches[0]);
        $letters = preg_replace('/[^A-Za-z]/', '', $text) ?? '';
        if (strlen($letters) > 100 && strlen($letters) > strlen($text) * 0.8 && $this->entropy($letters) < 1.0) {
            return ['low_text_entropy'];
        }
        if (count($words) < 20) {
            return [];
        }
        $dictionary = array_fill_keys(explode(' ', 'the and for with from this that have your you are was were has had document invoice total date name number amount payment tax price quantity description issue receipt company address customer seller buyer bank account statement balance credit debit reference item items page information report note contract of in on to at is a an be as or by not only due paid sum'), true);
        $known = count(array_filter($words, fn ($word) => isset($dictionary[$word]))) / count($words);
        $clusters = count(array_filter($words, fn ($word) => preg_match('/[bcdfghjklmnpqrstvwxyz]{5,}/', $word) === 1)) / count($words);

        return $known < 0.05 && $clusters > 0.25 && $this->entropy($letters) > 3.8 ? ['unnatural_latin_language_patterns'] : [];
    }
}
