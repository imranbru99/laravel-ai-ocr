<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ImranDevBd\AiOcr\AiOcrManager;
use ImranDevBd\AiOcr\Exceptions\AiOcrException;
use ImranDevBd\AiOcr\Http\Requests\ExtractRequest;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Support\JobTracker;
use ImranDevBd\AiOcr\Support\Owner;
use ImranDevBd\AiOcr\Support\Sources;

final class ExtractionController
{
    public function store(ExtractRequest $request, AiOcrManager $ocr): JsonResponse
    {
        try {
            $input = $request->file('file');
            if ($request->boolean('queued')) {
                if (! $input instanceof UploadedFile) {
                    throw new \InvalidArgumentException('A document upload is required.');
                }
                $directory = storage_path('app/private/ai-ocr-queue');
                if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                    throw new \RuntimeException('Cannot create private queue input directory.');
                }
                $input = $input->move($directory, (string) Str::ulid())->getPathname();
            }
            if (is_string($input) && $request->boolean('queued')) {
                chmod($input, 0600);
            }
            $job = $ocr->file($input)->using($request->input('strategy', 'auto'))->noCache();
            if ($request->filled('schema')) {
                $definition = json_decode($request->input('schema'), true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($definition)) {
                    throw new \InvalidArgumentException('Schema must be a JSON object.');
                }
                $job->as($definition);
            }
            if ($request->filled('provider')) {
                $job->provider($request->input('provider'));
            }
            if ($request->filled('model')) {
                $job->model($request->input('model'));
            }
            if ($request->filled('type') && ! $request->filled('schema')) {
                $job->as($request->input('type'));
            } if ($request->has('languages')) {
                $job->languages($request->input('languages'));
            } if ($request->has('dpi')) {
                $job->dpi((int) $request->input('dpi'));
            }

            if ($request->boolean('queued')) {
                $id = (string) Str::ulid();
                $dispatch = $job->trackingId($id)->deleteSourceAfterCompletion()->parallel($request->boolean('parallel'))->dispatch();
                unset($dispatch);

                return response()->json(['id' => $id, 'status' => 'queued', 'poll_url' => $request->url().'/'.$id], 202);
            }

            return response()->json($job->extract()->toArray());
        } catch (AiOcrException|\InvalidArgumentException|\JsonException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function owned(Request $request, string $id): Extraction
    {
        return Extraction::query()->whereHas('document', fn ($q) => $q->where('owner_id', $request->user()?->getAuthIdentifier())->where('owner_type', Owner::type($request->user())))->findOrFail($id);
    }

    public function rerun(Request $request, string $id, AiOcrManager $ocr): JsonResponse
    {
        abort_unless(config('ai-ocr.store.enabled'), 404);
        $record = $this->owned($request, $id);
        $document = $record->document;
        abort_if($document === null, 404);
        $validated = $request->validate(['strategy' => ['sometimes', 'in:auto,manual_only,ai_only,ai_verify,consensus']]);
        try {
            $bytes = app(Sources::class)->read($document);

            return response()->json($ocr->extract($bytes, [...($record->getAttribute('options') ?? []), ...$validated, 'cache' => 0])->toArray());
        } catch (\InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }
    }

    public function review(Request $request, string $id): JsonResponse
    {
        abort_unless(config('ai-ocr.store.enabled'), 404);
        $record = $this->owned($request, $id);
        $data = $request->validate(['correct' => ['required', 'boolean']]);
        $record->setAttribute('review_correct', (bool) $data['correct']);
        $record->save();

        return response()->json(['reviewed' => true]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $progress = app(JobTracker::class)->get($id);
        if ($progress !== null) {
            abort_unless((string) ($progress['owner_id'] ?? '') === (string) $request->user()?->getAuthIdentifier() && ($progress['owner_type'] ?? null) === (Owner::type($request->user())), 404);

            return response()->json($progress);
        }
        abort_unless(config('ai-ocr.store.enabled'), 404);
        $extraction = $this->owned($request, $id);

        return response()->json($extraction->extractionResult()->toArray());
    }
}
