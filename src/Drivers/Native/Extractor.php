<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Drivers\Native;

use ImranDevBd\AiOcr\Contracts\NativeExtractor;
use ImranDevBd\AiOcr\DTO\Page;
use ImranDevBd\AiOcr\DTO\Table;
use ImranDevBd\AiOcr\Exceptions\ExtractionFailedException;
use ImranDevBd\AiOcr\Exceptions\FileTooLargeException;
use ImranDevBd\AiOcr\Input\Document;
use ImranDevBd\AiOcr\Input\Resolver;
use ImranDevBd\AiOcr\Support\Binaries;
use ImranDevBd\AiOcr\Support\Temp;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser;

final class Extractor implements NativeExtractor
{
    public function __construct(private readonly Binaries $binaries) {}

    /** @return list<Page> */
    public function extract(Document $document): array
    {
        if (str_starts_with($document->mime, 'image/')) {
            return array_map(fn ($n) => new Page($n, '', confidence: 0), range(1, $this->pageCount($document)));
        }
        if ($document->mime === 'application/pdf') {
            $count = $this->pageCount($document);
            if (in_array('pdftotext', config('ai-ocr.native.drivers', ['pdftotext', 'smalot']), true) && $this->binaries->find('pdftotext')) {
                $pages = [];
                for ($number = 1; $number <= $count; $number++) {
                    if (! $document->selected($number)) {
                        continue;
                    }
                    $args = ['-f', (string) $number, '-l', (string) $number, '-enc', 'UTF-8'];
                    if (config('ai-ocr.native.layout', true)) {
                        $args[] = '-layout';
                    }
                    $pages[] = new Page($number, trim($this->binaries->run('pdftotext', [...$args, $document->path, '-'])));
                }

                return $pages;
            }
            if (class_exists(Parser::class)) {
                $pdf = (new Parser)->parseFile($document->path);

                return array_map(fn ($p, $i) => new Page((int) $i + 1, $p->getText()), $pdf->getPages(), array_keys($pdf->getPages()));
            }

            return (new MinimalPdf)->extract((string) file_get_contents($document->path), $count);
        }
        $bytes = (string) file_get_contents($document->path);
        if (in_array($document->mime, ['message/rfc822', 'text/x-mail'], true) || preg_match('/^(?:MIME-Version|Content-Type):/mi', $bytes)) {
            $temp = new Temp;
            $document->retain($temp);
            $pages = [];
            foreach ((new MimeMessage)->parts($bytes) as $part) {
                $source = app(Resolver::class)->resolve($part, $temp);
                // Each attachment needs its own input file rather than reusing the resolver's input filename.
                $copy = $temp->path('attachment-'.count($pages).'-'.bin2hex(random_bytes(4)));
                copy($source->path, $copy);
                $source = new Document($copy, $source->mime, $source->hash, $source->size, $source->name);
                foreach ($this->extract($source) as $page) {
                    $number = count($pages) + 1;
                    $document->attach($number, $source, $page->number);
                    $pages[] = new Page($number, $page->text, $page->engine, $page->confidence, $page->words, $page->blocks);
                    $this->checkPages(count($pages));
                }
            }

            return $pages ?: [new Page(1, '', confidence: 0)];
        }
        if (str_contains($document->mime, 'wordprocessingml')) {
            return [new Page(1, $this->docx($document->path))];
        }
        if (str_contains($document->mime, 'spreadsheetml')) {
            return $this->xlsx($document->path);
        }
        if (in_array($document->mime, ['application/vnd.ms-excel', 'application/x-ole-storage'], true)) {
            if (! class_exists(IOFactory::class)) {
                throw new ExtractionFailedException('XLS requires phpoffice/phpspreadsheet; run ai-ocr:doctor.');
            }
            $book = IOFactory::load($document->path);
            $pages = [];
            foreach ($book->getAllSheets() as $i => $sheet) {
                $pages[] = new Page($i + 1, implode("\n", array_map(fn ($r) => implode("\t", $r), $sheet->toArray())));
            } $book->disconnectWorksheets();

            return $pages;
        }
        if ($document->mime === 'text/html') {
            $bytes = $this->html($bytes);
        }
        if (in_array($document->mime, ['message/rfc822', 'text/x-mail'], true) || preg_match('/^(From|MIME-Version):/mi', $bytes)) {
            $bytes = $this->email($bytes);
        }

        return [new Page(1, trim($bytes))];
    }

    public function pageCount(Document $document): int
    {
        if ($document->mime === 'image/tiff' && class_exists(\Imagick::class)) {
            $image = new \Imagick;
            try {
                $image->pingImage($document->path);

                return $this->checkPages($image->getNumberImages());
            } finally {
                $image->clear();
            }
        }
        if ($document->mime !== 'application/pdf') {
            return 1;
        }
        if ($this->binaries->find('pdfinfo')) {
            $info = $this->binaries->run('pdfinfo', [$document->path]);
            if (preg_match('/^Pages:\s+(\d+)/m', $info, $m)) {
                return $this->checkPages((int) $m[1]);
            }
        }
        $bytes = (string) file_get_contents($document->path);
        preg_match_all('#/Type\s*/Page\b#', $bytes, $m);
        $count = count($m[0]);
        if ($count === 0) {
            throw new ExtractionFailedException('Cannot safely determine PDF page count; install poppler pdfinfo.');
        }

        return $this->checkPages($count);
    }

    private function checkPages(int $pages): int
    {
        if ($pages > config('ai-ocr.limits.max_pages', 200)) {
            throw new FileTooLargeException('Document exceeds maximum page count.');
        }

        return $pages;
    }

    /**
     * @param  list<Page>  $pages
     * @return list<Table>
     */
    public function tables(array $pages): array
    {
        $tables = [];
        foreach ($pages as $page) {
            $rows = [];
            $firstLine = explode("\n", $page->text)[0];
            $delimiter = str_contains($firstLine, "\t") ? "\t" : (str_contains($firstLine, ',') ? ',' : (str_contains($firstLine, ';') ? ';' : null));
            if ($delimiter !== null) {
                $stream = fopen('php://temp', 'r+');
                if ($stream === false) {
                    throw new ExtractionFailedException('Cannot read tabular text.');
                }
                try {
                    fwrite($stream, $page->text);
                    rewind($stream);
                    while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                        $rows[] = array_map(fn ($v) => (string) $v, $row);
                    }
                } finally {
                    fclose($stream);
                }
                if (count($rows) > 1 && count($rows[0]) > 1) {
                    $tables[] = new Table($rows, $page->number, true, confidence: 0.9);

                    continue;
                }
                $rows = [];
            }
            foreach (explode("\n", $page->text) as $line) {
                if (str_contains($line, "\t")) {
                    $cells = explode("\t", trim($line));
                } elseif (preg_match('/\S\s{2,}\S/', $line)) {
                    $cells = preg_split('/\s{2,}/', trim($line)) ?: [];
                } elseif (str_contains($line, ',') || str_contains($line, ';')) {
                    $cells = str_getcsv($line, str_contains($line, ';') ? ';' : ',', '"', '');
                } else {
                    $cells = [];
                }
                if (count($cells) > 1) {
                    $rows[] = array_map(fn ($v) => (string) $v, $cells);
                }
            }
            if (count($rows) > 1) {
                $tables[] = new Table($rows, $page->number, true, confidence: 0.7);
            }
        }

        return $tables;
    }

    private function xml(string $xml): \DOMDocument
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new ExtractionFailedException('XML entities are not permitted.');
        }
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $dom->loadXML($xml, LIBXML_NONET)) {
                throw new ExtractionFailedException('Malformed Office XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $dom;
    }

    private function docx(string $path): string
    {
        $zip = new \ZipArchive;
        $zip->open($path);
        try {
            $dom = $this->xml((string) $zip->getFromName('word/document.xml'));
            $lines = [];
            foreach ($dom->getElementsByTagNameNS('*', 'p') as $paragraph) {
                $line = '';
                foreach ($paragraph->getElementsByTagNameNS('*', 't') as $text) {
                    $line .= $text->textContent;
                } $lines[] = $line;
            }

            return implode("\n", $lines);
        } finally {
            $zip->close();
        }
    }

    /** @return list<Page> */
    private function xlsx(string $path): array
    {
        $zip = new \ZipArchive;
        $zip->open($path);
        try {
            $shared = [];
            $xml = $zip->getFromName('xl/sharedStrings.xml');
            if ($xml !== false) {
                foreach ($this->xml($xml)->getElementsByTagName('si') as $item) {
                    $shared[] = $item->textContent;
                }
            }
            $pages = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name) || ! preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    continue;
                } $lines = [];
                foreach ($this->xml((string) $zip->getFromName($name))->getElementsByTagName('row') as $row) {
                    $cells = [];
                    foreach ($row->getElementsByTagName('c') as $cell) {
                        $ref = $cell->getAttribute('r');
                        preg_match('/^([A-Z]+)/', $ref, $letters);
                        $index = 0;
                        foreach (str_split($letters[1] ?? 'A') as $char) {
                            $index = $index * 26 + ord($char) - 64;
                        } while (count($cells) < $index - 1) {
                            $cells[] = '';
                        } $value = $cell->getElementsByTagName('v')->item(0)->textContent ?? $cell->getElementsByTagName('is')->item(0)->textContent ?? '';
                        $cells[] = $cell->getAttribute('t') === 's' ? ($shared[(int) $value] ?? '') : $value;
                    } $lines[] = implode("\t", $cells);
                } $pages[] = new Page(count($pages) + 1, implode("\n", $lines));
            }

            return $pages;
        } finally {
            $zip->close();
        }
    }

    private function html(string $text): string
    {
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $text) ?? $text;

        return html_entity_decode(strip_tags(preg_replace('#</?(p|div|tr|br|li)\b[^>]*>#i', "\n", $text) ?? $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function email(string $bytes): string
    {
        [$headers, $body] = array_pad(preg_split('/\r?\n\r?\n/', $bytes, 2) ?: [], 2, '');
        if (preg_match('/boundary="?([^"\s;]+)/i', $headers, $m)) {
            $parts = explode('--'.$m[1], $body);
            $texts = [];
            foreach ($parts as $part) {
                if (preg_match('/Content-Type:\s*text\/(plain|html)/i', $part)) {
                    $texts[] = $this->email(ltrim($part));
                }
            }

            return implode("\n", $texts);
        }
        if (stripos($headers, 'Content-Transfer-Encoding: base64') !== false) {
            $body = base64_decode(preg_replace('/\s/', '', $body) ?? '', true) ?: '';
        } elseif (stripos($headers, 'quoted-printable') !== false) {
            $body = quoted_printable_decode($body);
        }

        return stripos($headers, 'text/html') !== false ? $this->html($body) : trim($body);
    }
}
