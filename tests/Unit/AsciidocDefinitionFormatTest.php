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
    }

    #[Test]
    public function formatTextLeavesLoneAsteriskLiteralButEscapesPairs(): void
    {
        $f = $this->makeFormatter();

        // a single asterisk cannot form AsciiDoc bold, so it stays literal
        self::assertSame('0..*', $f->formatText('0..*'));
        self::assertSame('a.*b', $f->formatText('a.*b'));
        // two or more asterisks on a line could pair into bold, so all are escaped
        self::assertSame('a&#42;b&#42;c', $f->formatText('a*b*c'));
        self::assertSame(
            '(0&#124;[1-9][0-9]&#42;)&#42;',
            $f->formatText('(0|[1-9][0-9]*)*'),
        );
    }

    #[Test]
    public function formatTextPreservesListMarkersWhenEscapingAsterisks(): void
    {
        $f = $this->makeFormatter();

        // a leading list marker is block-level and is never escaped; the lone regex
        // asterisk on the same line stays literal (one inline asterisk cannot bold)
        self::assertSame('* a regex `[a-z]*`', $f->formatText('* a regex `[a-z]*`'));
        // multi-line bulleted list with a lone asterisk per line stays fully literal
        self::assertSame(
            "* `\"local\"`\n* a regex `[a-z]*`.",
            $f->formatText("* `\"local\"`\n* a regex `[a-z]*`."),
        );
        // but inline pairs after a marker are still escaped, marker preserved
        self::assertSame('* a &#42;bold&#42; word', $f->formatText('* a *bold* word'));
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
