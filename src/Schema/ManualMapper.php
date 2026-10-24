<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Schema;

use ImranDevBd\AiOcr\Normalizers\Normalizer;

final class ManualMapper
{
    /** @var array<string, string> */
    private array $quotes = [];

    /** @return array<string, string> */
    public function evidence(): array
    {
        return $this->quotes;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function extract(string $text, array $schema, string $prefix = ''): array
    {
        $data = [];
        if (isset($schema['properties']['mrz']) && preg_match('/P<[A-Z<]{42}\s+[A-Z0-9<]{44}/', $text, $mrz)) {
            $data['mrz'] = $mrz[0];
            $this->quotes['mrz'] = $mrz[0];
        }
        foreach ($schema['properties'] ?? [] as $key => $field) {
            if (($field['type'] ?? '') === 'object') {
                $child = $this->extract($text, $field, $prefix.$key.'.');
                if ($child) {
                    $data[$key] = $child;
                }

                continue;
            }
            if (($field['type'] ?? '') === 'array') {
                continue;
            }
            $label = str_replace('_', '[ _-]*', preg_quote($key, '/'));
            $aliases = ['invoice_number' => '(?:invoice[ _-]*(?:number|no\.?|#)|receipt[ _-]*(?:number|no\.?|#))', 'nid_number' => '(?:nid[ _-]*(?:number|no\.?)?|id[ _-]*(?:number|no\.?)?|জাতীয় পরিচয়পত্র নম্বর)', 'name_en' => '(?:name[ _-]*(?:en|english)?|নাম \(ইংরেজি\))', 'name_bn' => '(?:নাম|name[ _-]*bn)', 'birth_date' => '(?:date of birth|dob|জন্ম তারিখ)', 'father' => '(?:father|পিতা)', 'mother' => '(?:mother|মাতা)', 'address' => '(?:address|ঠিকানা)'];
            if (isset($aliases[$key])) {
                $label = '(?:'.$label.'|'.$aliases[$key].')';
            }

            if (! preg_match('/(?:^|\n)\s*'.$label.'\s*[:=#]\s*([^\n]+)/iu', $text, $match)) {
                continue;
            }
            $value = trim($match[1]);
            $type = $field['type'] ?? 'string';
            if (($field['x-cast'] ?? '') === 'money') {
                $value = Normalizer::money($value);
            } elseif (($field['format'] ?? '') === 'date') {
                $value = Normalizer::date($value);
            } elseif ($type === 'integer' && preg_match('/^-?\d+$/', $value)) {
                $value = (int) $value;
            } elseif ($type === 'number' && is_numeric($value)) {
                $value = (float) $value;
            } elseif ($type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
            if ($value !== null) {
                $data[$key] = $value;
                $this->quotes[$prefix.$key] = trim($match[0]);
            }
        }

        return $data;
    }
}
