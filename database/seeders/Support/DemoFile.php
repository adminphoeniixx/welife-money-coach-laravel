<?php

namespace Database\Seeders\Support;

/**
 * Builds tiny, genuinely valid placeholder files for the demo seeders, so the
 * vault / debt-attachment endpoints have real bytes to encrypt, stream and
 * download without shipping binary fixtures in the repo.
 */
final class DemoFile
{
    /** A 1x1 transparent PNG — enough for "is this an image?" code paths. */
    public static function png(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );

        return $bytes === false ? '' : $bytes;
    }

    /**
     * A single-page PDF showing $text, with a correct xref table so real PDF
     * viewers open it rather than reporting a damaged file.
     */
    public static function pdf(string $text): string
    {
        $stream = 'BT /F1 16 Tf 40 96 Td ('.self::escape($text).") Tj ET\n"
            .'BT /F1 10 Tf 40 68 Td (MoneyCoach demo document - sample data only.) Tj ET';

        $objects = [
            '<</Type/Catalog/Pages 2 0 R>>',
            '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            '<</Type/Page/Parent 2 0 R/MediaBox[0 0 420 160]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>',
            '<</Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",
            '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $size = count($objects) + 1;
        $startxref = strlen($pdf);

        $pdf .= "xref\n0 ".$size."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<</Size ".$size."/Root 1 0 R>>\nstartxref\n".$startxref."\n%%EOF";

        return $pdf;
    }

    /** Escape the characters that would otherwise close a PDF string literal. */
    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
