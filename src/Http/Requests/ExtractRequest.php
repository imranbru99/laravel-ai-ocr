<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ExtractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['queued' => ['sometimes', 'boolean'], 'parallel' => ['sometimes', 'boolean'], 'schema' => ['nullable', 'string', 'json', 'max:65536'], 'provider' => ['nullable', 'string', 'max:100'], 'model' => ['nullable', 'string', 'max:100'], 'file' => ['required', 'file', 'max:'.((int) config('ai-ocr.limits.max_size_mb', 25) * 1024)], 'strategy' => ['sometimes', 'in:auto,manual_only,ai_only,ai_verify,consensus'], 'type' => ['nullable', 'string', 'max:100'], 'languages' => ['sometimes', 'array', 'max:10'], 'languages.*' => ['string', 'regex:/^[a-zA-Z0-9_]+$/'], 'dpi' => ['sometimes', 'integer', 'between:72,600']];
    }
}
