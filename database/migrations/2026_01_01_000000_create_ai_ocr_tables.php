<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_ocr_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('hash', 64)->index();
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('disk')->nullable();
            $table->text('path')->nullable();
            $table->string('original_name');
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();
            $table->index(['owner_type', 'owner_id']);
            $table->timestamps();
        });
        Schema::create('ai_ocr_extractions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained('ai_ocr_documents')->cascadeOnDelete();
            $table->string('fingerprint', 80)->nullable()->index();
            $table->string('strategy');
            $table->string('type')->nullable();
            $table->string('status')->index();
            $table->decimal('confidence', 5, 4);
            $table->json('engine_summary');
            $table->decimal('cost', 14, 8)->default(0);
            $table->unsignedBigInteger('tokens')->default(0);
            $table->unsignedBigInteger('duration')->default(0);
            $table->longText('result');
            $table->json('options')->nullable();
            $table->boolean('review_correct')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
        Schema::create('ai_ocr_pages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('extraction_id')->constrained('ai_ocr_extractions')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->longText('text')->nullable();
            $table->string('engine');
            $table->decimal('confidence', 5, 4);
            $table->boolean('garbled')->default(false);
            $table->json('bbox')->nullable();
            $table->timestamps();
        });
        Schema::create('ai_ocr_schemas', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('owner_id');
            $table->json('schema');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ai_ocr_schemas', 'ai_ocr_pages', 'ai_ocr_extractions', 'ai_ocr_documents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
