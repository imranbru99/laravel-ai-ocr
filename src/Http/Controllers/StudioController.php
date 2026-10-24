<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Support\Analytics;
use ImranDevBd\AiOcr\Support\Doctor;
use ImranDevBd\AiOcr\Support\Owner;
use ImranDevBd\AiOcr\Types\TypeRegistry;

final class StudioController
{
    public function index(): View
    {
        $history = collect();
        if (config('ai-ocr.store.enabled')) {
            $query = Extraction::query()->whereHas('document', fn ($q) => $q->where('owner_id', auth()->guard()->id())->where('owner_type', Owner::type(auth()->guard()->user())));
            if (is_string(request('search')) && request('search') !== '') {
                $query->where('id', 'like', '%'.mb_substr(request('search'), 0, 100).'%');
            }
            if (is_string(request('type')) && request('type') !== '') {
                $query->where('type', request('type'));
            }
            $history = $query->latest()->limit(25)->get(['id', 'type', 'confidence', 'cost', 'created_at']);
        }

        /** @var view-string $template */
        $template = 'ai-ocr::studio';

        return app(Factory::class)->make($template, ['doctor' => app(Doctor::class)->report(), 'types' => app(TypeRegistry::class)->names(), 'history' => $history, 'analytics' => config('ai-ocr.store.enabled') ? app(Analytics::class)->summary() : []]);
    }
}
