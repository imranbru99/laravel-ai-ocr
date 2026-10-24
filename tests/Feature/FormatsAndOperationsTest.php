<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Feature;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use ImranDevBd\AiOcr\Facades\AiOcr;
use ImranDevBd\AiOcr\Jobs\ExtractDocumentJob;
use ImranDevBd\AiOcr\Models\Extraction;
use ImranDevBd\AiOcr\Schema\Schema;
use ImranDevBd\AiOcr\Support\Temp;
use ImranDevBd\AiOcr\Support\WebhookVerifier;
use ImranDevBd\AiOcr\Tests\Fixtures\Fixtures;
use ImranDevBd\AiOcr\Tests\TestCase;

final class FormatsAndOperationsTest extends TestCase
{
    /** @param array<string, string> $entries */
    private function office(array $entries): string
    {
        $temp = new Temp;
        $path = $temp->path('office.zip');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $name => $value) {
            $zip->addFromString($name, $value);
        } $zip->close();

        return file_get_contents($path);
    }

    public function test_docx_native_paragraphs(): void
    {
        $docx = $this->office(['word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Readable Word document paragraph with sufficient content.</w:t></w:r></w:p></w:body></w:document>']);
        self::assertStringContainsString('Word document', AiOcr::file($docx)->manualOnly()->text());
    }

    public function test_xlsx_native_shared_strings_and_sparse_cells(): void
    {
        $xlsx = $this->office(['xl/workbook.xml' => '<workbook/>', 'xl/sharedStrings.xml' => '<sst><si><t>Description</t></si><si><t>Test item</t></si></sst>', 'xl/worksheets/sheet1.xml' => '<worksheet><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="inlineStr"><is><t>Amount</t></is></c></row><row r="2"><c r="A2" t="s"><v>1</v></c><c r="C2"><v>25</v></c></row></sheetData></worksheet>']);
        $result = AiOcr::file($xlsx)->manualOnly()->extract();
        self::assertStringContainsString("Description\t\tAmount", $result->text());
        self::assertNotEmpty($result->tables());
    }

    public function test_csv_and_html_produce_native_results(): void
    {
        $csv = AiOcr::file("Description,Quantity,Amount\nPaper,2,300\nPens,4,100")->manualOnly()->extract();
        self::assertCount(1, $csv->tables());
        self::assertStringContainsString('Description', $csv->toCsv());
        $html = AiOcr::file('<html><body><p>Readable HTML document content from the first paragraph.</p><script>alert("bad")</script></body></html>')->manualOnly()->extract();
        self::assertStringNotContainsString('alert', $html->text());
    }

    public function test_email_pdf_attachment_is_extracted_recursively(): void
    {
        $pdf = Fixtures::pdf(['Attached PDF document with sufficient native text.']);
        $email = "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=sample\r\n\r\n--sample\r\nContent-Type: text/plain\r\n\r\nReadable email body with enough native text for quality.\r\n--sample\r\nContent-Type: application/pdf\r\nContent-Transfer-Encoding: base64\r\n\r\n".base64_encode($pdf)."\r\n--sample--\r\n";
        $result = AiOcr::file($email)->manualOnly()->extract();
        self::assertCount(2, $result->pages());
        self::assertStringContainsString('Attached PDF', $result->pages()[1]->text);
    }

    public function test_persistent_dedupe_survives_cache_eviction(): void
    {
        $this->migrate();
        config(['ai-ocr.store.enabled' => true]);
        $schema = Schema::make()->string('name')->required();
        $first = AiOcr::file("name: Example Company\nReadable native document text.")->as($schema)->manualOnly()->cache(60)->extract();
        Cache::flush();
        $second = AiOcr::file("name: Example Company\nReadable native document text.")->as($schema)->manualOnly()->cache(60)->extract();
        self::assertTrue($second->fromCache());
        self::assertSame($first->id, $second->id);
        self::assertSame(1, Extraction::count());
    }

    public function test_disabling_stored_text_removes_text_words_tables_and_quotes(): void
    {
        $this->migrate();
        config(['ai-ocr.store.enabled' => true, 'ai-ocr.store.store_text' => false]);
        $result = AiOcr::file('Readable private text with sufficient content for native extraction.')->manualOnly()->extract();
        $restored = Extraction::findOrFail($result->id)->extractionResult();
        self::assertSame('', $restored->text());
        self::assertSame([], $restored->tables());
    }

    public function test_queued_job_is_encrypted_and_dispatches_to_requested_queue(): void
    {
        Bus::fake();
        $temp = new Temp;
        $path = $temp->path('durable.txt');
        file_put_contents($path, 'Readable queued document text with sufficient native content.');
        $dispatch = AiOcr::file($path)->manualOnly()->onQueue('documents')->dispatch();
        unset($dispatch);
        Bus::assertDispatched(ExtractDocumentJob::class, fn ($job) => $job->queue === 'documents' && $job instanceof ShouldBeEncrypted);
    }

    public function test_webhook_signature_and_replay_protection(): void
    {
        $verifier = app(WebhookVerifier::class);
        $timestamp = (string) time();
        $event = '01SAMPLEEVENT00000000000000';
        $body = '{"event":"extraction.completed"}';
        $secret = str_repeat('s', 32);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        self::assertTrue($verifier->verify($body, $timestamp, $event, $signature, $secret));
        self::assertFalse($verifier->verify($body, $timestamp, $event, $signature, $secret));
        self::assertFalse($verifier->verify($body, (string) (time() - 1000), $event, $signature, $secret));
        self::assertFalse($verifier->verify($body, $timestamp, '01DIFFERENTEVENT00000000000', 'invalid', $secret));
    }

    public function test_type_registry_and_doctor_commands(): void
    {
        $this->artisan('ai-ocr:types')->expectsOutput('bd_nid')->assertExitCode(0);
        $this->artisan('ai-ocr:doctor')->assertExitCode(0);
        self::assertContains('eng', AiOcr::doctor()['languages']);
    }

    public function test_300_page_pdf_stays_within_memory_ceiling(): void
    {
        config(['ai-ocr.limits.max_pages' => 350]);
        $before = memory_get_usage(true);
        $result = AiOcr::file(Fixtures::pdf(array_fill(0, 300, 'Readable native PDF page content for bounded memory testing.')))->manualOnly()->noCache()->extract();
        self::assertCount(300, $result->pages());
        self::assertLessThan(64 * 1024 * 1024, memory_get_peak_usage(true) - $before);
    }
}
