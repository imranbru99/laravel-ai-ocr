<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use ImranDevBd\AiHub\Facades\AIHub;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Quality\Validity;
use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Support\Temp;
use ImranDevBd\AiOcr\Tests\Fixtures\Fixtures;
use ImranDevBd\AiOcr\Tests\TestCase;

final class ParallelAndConsensusTest extends TestCase
{
    private function batchTable(): void
    {
        DatabaseSchema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
        config(['queue.default' => 'sync', 'queue.batching.database' => 'testing']);
    }

    public function test_parallel_pages_merge_before_document_schema_validation(): void
    {
        $this->batchTable();
        $this->migrate();
        config(['ai-ocr.store.enabled' => true]);
        $temp = new Temp;
        $path = $temp->path('invoice.pdf');
        file_put_contents($path, Fixtures::pdf(['invoice_number: TEST123 readable invoice identifier', 'total: 125.00 final invoice payment amount']));
        $schema = Schema::make('invoice')->string('invoice_number')->required()->money('total')->required();
        $id = '01PARALLELINVOICE0000000001';
        $batch = AiOcr::file($path)->as($schema)->manualOnly()->trackingId($id)->parallel()->dispatch();
        self::assertSame(2, $batch->totalJobs);
        $progress = AiOcr::progress($id);
        self::assertSame('completed', $progress['status']);
        self::assertSame(12500, $progress['result']['data']['total']);
        self::assertSame([1, 2], array_column($progress['result']['pages'], 'number'));
        self::assertSame(1, Extraction::count());
    }

    public function test_consensus_votes_on_fields_instead_of_only_page_text(): void
    {
        $this->hubFake([
            '{"text":"total: 10.00. The actual printed amount for this example is 20.00.","confidence":0.8}',
            '{"data":{"total":2000},"evidence":[{"field":"total","confidence":0.96,"page":1,"source_quote":"20.00"}]}',
        ]);
        $result = AiOcr::file("total: 10.00\nThe actual printed amount for this example is 20.00.")->as(Schema::make()->money('total')->required())->using('consensus')->extract();
        self::assertSame(2000, $result->field('total')->value);
        self::assertSame('ai', $result->field('total')->engine);
        self::assertNotEmpty(array_filter($result->trace(), fn ($step) => $step['step'] === 'field_difference'));
    }

    public function test_local_only_policy_is_preserved_in_page_jobs(): void
    {
        $this->batchTable();
        $this->hubFake('{}');
        config(['ai-hub.providers.ollama.enabled' => false]);
        $temp = new Temp;
        $path = $temp->path('identity.pdf');
        file_put_contents($path, Fixtures::pdf(['']));
        AiOcr::file($path)->as('bd_nid')->parallel()->dispatch();
        AIHub::assertSentCount(0);
    }

    public function test_invalid_ancestor_field_invalidates_all_children(): void
    {
        self::assertTrue(Validity::failed('items.0.amount', ['items']));
        self::assertTrue(Validity::failed('items', ['items.0.amount']));
        self::assertFalse(Validity::failed('total', ['items']));
    }
}
