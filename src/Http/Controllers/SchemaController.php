<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ImranDevBd\AiOcr\Schema\Schema;

final class SchemaController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(config('ai-ocr.store.enabled') && $request->user(), 403);
        $schemas = DB::table('ai_ocr_schemas')->where('owner_id', (string) $request->user()->getAuthIdentifier())->get();

        return response()->json($schemas->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'schema' => json_decode($row->schema, true, 512, JSON_THROW_ON_ERROR)]));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(config('ai-ocr.store.enabled') && $request->user(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'schema' => ['required', 'array']]);
        $schema = Schema::fromJson($data['schema']);
        $id = (string) Str::ulid();
        DB::table('ai_ocr_schemas')->insert(['id' => $id, 'name' => $data['name'], 'owner_id' => (string) $request->user()->getAuthIdentifier(), 'schema' => json_encode($schema->toJsonSchema(), JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        abort_unless(config('ai-ocr.store.enabled') && $request->user(), 403);
        DB::table('ai_ocr_schemas')->where('owner_id', (string) $request->user()->getAuthIdentifier())->where('id', $id)->delete();

        return response()->json(['deleted' => true]);
    }
}
