<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer\Formatter;

use RuntimeException;

final readonly class SvgPassthrough
{
    public function format(string $svg, string $sourcePumlBaseName): string
    {
        $svg = $this->sanitise($svg);
        $this->assertNotErrorSvg($svg, $sourcePumlBaseName);

        return <<<ADOC
            ////
            Auto-generated from {$sourcePumlBaseName} — do not edit.
            ////
            ++++
            {$svg}
            ++++
            ADOC;
    }

    private function sanitise(string $svg): string
    {
        // Strip leading XML processing instruction; passthrough is verbatim and an XML PI mid-HTML is invalid.
        $svg = (string) preg_replace('/^\s*<\\?xml[^?]*\\?>\s*/u', '', $svg);
        // Strip DOCTYPE declarations.
        $svg = (string) preg_replace('/<!DOCTYPE[^>]*>\s*/u', '', $svg);
        // Strip PlantUML MD5/version stamp comments so SVG version churn does not pollute git diffs.
        $svg = (string) preg_replace('/<!--\s*MD5=.*?-->/us', '', $svg);

        return rtrim($svg) . "\n";
    }

    private function assertNotErrorSvg(string $svg, string $sourcePumlBaseName): void
    {
        // PlantUML emits a literal "Syntax Error?" string inside <text> when parsing fails.
        if (preg_match('/Syntax Error\?/u', $svg) === 1) {
            throw new RuntimeException(sprintf(
                'PlantUML rendered an error diagram for "%s" — fix the .puml source.',
                $sourcePumlBaseName,
            ));
        }
    }
}
