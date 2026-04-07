<?php

declare(strict_types=1);

namespace Tests\Unit;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AsciidocDefinitionFormatTest extends TestCase
{
    private function makeFormatter(bool $legacyFormat = false): AsciidocDefinition
    {
        return new AsciidocDefinition(new BmmSchemaCollection(), $legacyFormat);
    }

    #[Test]
    public function formatTextReturnsEmptyStringForNull(): void
    {
        $f = $this->makeFormatter();

        self::assertSame('', $f->formatText(null));
    }

    #[Test]
    public function formatTextTrimsWhitespace(): void
    {
        $f = $this->makeFormatter();

        self::assertSame('x', $f->formatText("  x \n"));
    }

    #[Test]
    public function formatTextAppliesTextReplacementMap(): void
    {
        $f = $this->makeFormatter();

        self::assertSame('a&#124;b', $f->formatText('a|b'));
        self::assertSame('a\<=b', $f->formatText('a<=b'));
        self::assertSame('a.&#42;b', $f->formatText('a.*b'));
    }

    #[Test]
    public function formatXrefReturnsEmptyWhenNoOpenehrMarker(): void
    {
        $f = $this->makeFormatter();
        $results = [];

        self::assertSame('', $f->formatXref('', $results));
        self::assertSame([], $results);

        self::assertSame('', $f->formatXref('com.example.only', $results));
    }

    #[Test]
    public function formatXrefBuildsComponentModulePageAndResults(): void
    {
        $f = $this->makeFormatter();
        $results = [];

        $xref = $f->formatXref('openehr_base_1.0.4.org.openehr.base.foundation_types.identification', $results);

        self::assertSame('BASE:foundation_types:identification', $xref);
        self::assertSame(['BASE', 'foundation_types', 'identification'], $results);
    }

    #[Test]
    public function formatXrefOmitsEmptySegmentsInOutput(): void
    {
        $f = $this->makeFormatter();
        $results = [];

        $xref = $f->formatXref('x.org.openehr.base.foundation_types', $results);

        self::assertSame('BASE:foundation_types', $xref);
        self::assertSame(['BASE', 'foundation_types', ''], $results);
    }
}
