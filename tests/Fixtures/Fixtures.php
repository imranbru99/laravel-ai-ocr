<?php

declare(strict_types=1);

namespace ImranDevBd\AiOcr\Tests\Fixtures;

final class Fixtures
{
    /** @param list<string> $texts */
    public static function pdf(array $texts): string
    {
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', ''];
        $kids = [];
        foreach ($texts as $text) {
            $pageId = count($objects) + 1;
            $streamId = $pageId + 1;
            $kids[] = $pageId.' 0 R';
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /Contents '.$streamId.' 0 R >>';
            $stream = 'BT /F1 12 Tf 50 720 Td ('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text).') Tj ET';
            $objects[] = '<< /Length '.strlen($stream).' >>'."\nstream\n".$stream."\nendstream";
        }
        $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($texts).' >>';
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".count($offsets)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf.'trailer << /Size '.count($offsets).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF\n";
    }
}
