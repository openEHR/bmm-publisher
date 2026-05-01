<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer\Formatter;

use RuntimeException;

final readonly class SvgSanitiser
{
    public function sanitise(string $svg, string $sourcePumlBaseName): string
    {
        $this->assertNotErrorSvg($svg, $sourcePumlBaseName);
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
