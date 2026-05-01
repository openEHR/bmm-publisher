<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use OpenEHR\BmmPublisher\Writer\Formatter\SvgSanitiser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SvgSanitiserTest extends TestCase
{
    #[Test]
    public function preservesSvgPayloadWhenAlreadyClean(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>';

        $output = (new SvgSanitiser())->sanitise($svg, 'foo.puml');

        self::assertStringContainsString('<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>', $output);
    }

    #[Test]
    public function stripsPlantUmlMd5StampComment(): void
    {
        $svg = "<svg><!--MD5=[abc123]\nPlantUML version 1.2025.0--></svg>";

        $output = (new SvgSanitiser())->sanitise($svg, 'qux.puml');

        self::assertStringNotContainsString('MD5=', $output);
        self::assertStringNotContainsString('PlantUML version', $output);
    }

    #[Test]
    public function throwsWhenSvgContainsPlantUmlSyntaxErrorMarker(): void
    {
        $svg = '<svg><text>Syntax Error?</text></svg>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('error.puml');

        (new SvgSanitiser())->sanitise($svg, 'error.puml');
    }
}
