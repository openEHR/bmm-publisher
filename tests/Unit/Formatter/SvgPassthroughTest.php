<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use OpenEHR\BmmPublisher\Writer\Formatter\SvgPassthrough;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SvgPassthroughTest extends TestCase
{
    #[Test]
    public function wrapsSvgInPassthroughBlockWithProvenanceComment(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>';

        $output = (new SvgPassthrough())->format($svg, 'foo.puml');

        self::assertStringStartsWith("////\nAuto-generated from foo.puml", $output);
        self::assertStringContainsString("\n++++\n", $output);
        self::assertStringEndsWith("++++", $output);
        self::assertStringContainsString('<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>', $output);
    }

    #[Test]
    public function stripsLeadingXmlProcessingInstruction(): void
    {
        $svg = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\n<svg/>";

        $output = (new SvgPassthrough())->format($svg, 'bar.puml');

        self::assertStringNotContainsString('<?xml', $output);
        self::assertStringContainsString('<svg/>', $output);
    }

    #[Test]
    public function stripsDoctypeDeclaration(): void
    {
        $svg = "<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"...dtd\">\n<svg/>";

        $output = (new SvgPassthrough())->format($svg, 'baz.puml');

        self::assertStringNotContainsString('DOCTYPE', $output);
    }

    #[Test]
    public function stripsPlantUmlMd5StampComment(): void
    {
        $svg = "<svg><!--MD5=[abc123]\nPlantUML version 1.2025.0--></svg>";

        $output = (new SvgPassthrough())->format($svg, 'qux.puml');

        self::assertStringNotContainsString('MD5=', $output);
        self::assertStringNotContainsString('PlantUML version', $output);
    }

    #[Test]
    public function throwsWhenSvgContainsPlantUmlSyntaxErrorMarker(): void
    {
        $svg = '<svg><text>Syntax Error?</text></svg>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('error.puml');

        (new SvgPassthrough())->format($svg, 'error.puml');
    }
}
