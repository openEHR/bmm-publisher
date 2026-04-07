<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use Cadasto\OpenEHR\BMM\Model\BmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationString;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AsciidocDefinitionTest extends TestCase
{
    private BmmSchemaCollection $collection;
    private BmmSchema $termSchema;
    private BmmSchema $baseSchema;
    private AsciidocDefinition $formatter;

    protected function setUp(): void
    {
        $this->collection = new BmmSchemaCollection();
        $this->collection->load('openehr_term_3.0.0');
        $this->collection->load('openehr_base_1.0.4');

        $schemas = array_values(iterator_to_array($this->collection));
        $this->termSchema = $schemas[0];
        $this->baseSchema = $schemas[1];

        $this->formatter = new AsciidocDefinition($this->collection);
    }

    #[Test]
    public function formatClassProducesAsciidocTable(): void
    {
        $class = $this->termSchema->classDefinitions->get('CODE');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = $this->formatter->format($class, 'org.openehr.term', $this->termSchema);

        self::assertStringContainsString('|===', $output);
        self::assertStringContainsString('*Class*', $output);
        self::assertStringContainsString('*CODE*', $output);
    }

    #[Test]
    public function formatClassIncludesDescription(): void
    {
        $class = $this->termSchema->classDefinitions->get('CODE');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = $this->formatter->format($class, 'org.openehr.term', $this->termSchema);

        if (!empty($class->documentation)) {
            self::assertStringContainsString('*Description*', $output);
        }
    }

    #[Test]
    public function formatClassIncludesAncestors(): void
    {
        $class = $this->termSchema->classDefinitions->get('CODE');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = $this->formatter->format($class, 'org.openehr.term', $this->termSchema);

        if (!empty($class->ancestors)) {
            self::assertStringContainsString('*Inherit*', $output);
        }
    }

    #[Test]
    public function formatClassIncludesAttributes(): void
    {
        $class = $this->termSchema->classDefinitions->get('CODE');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = $this->formatter->format($class, 'org.openehr.term', $this->termSchema);

        if ($class->properties->count() > 0) {
            self::assertStringContainsString('*Attributes*', $output);
        }
    }

    #[Test]
    public function formatEnumProducesEnumerationTable(): void
    {
        $enum = $this->termSchema->classDefinitions->get('TERMINOLOGY_STATUS');
        self::assertInstanceOf(BmmEnumerationString::class, $enum);

        $output = $this->formatter->format($enum, 'org.openehr.term', $this->termSchema);

        self::assertStringContainsString('|===', $output);
        self::assertStringContainsString('*Enumeration*', $output);
        self::assertStringContainsString('*TERMINOLOGY_STATUS*', $output);
    }

    #[Test]
    public function formatEnumIncludesConstants(): void
    {
        $enum = $this->termSchema->classDefinitions->get('TERMINOLOGY_STATUS');
        self::assertInstanceOf(BmmEnumerationString::class, $enum);

        $output = $this->formatter->format($enum, 'org.openehr.term', $this->termSchema);

        if ($enum->itemNames) {
            self::assertStringContainsString('*Constants*', $output);
        }
    }

    #[Test]
    public function formatClassWithFunctionsIncludesFunctionsSection(): void
    {
        $class = $this->baseSchema->classDefinitions->get('ARCHETYPE_ID');
        self::assertInstanceOf(BmmClass::class, $class);
        self::assertGreaterThan(0, $class->functions->count());

        $output = $this->formatter->format($class, 'org.openehr.base', $this->baseSchema);

        self::assertStringContainsString('*Functions*', $output);
    }

    #[Test]
    public function formatClassWithPropertiesIncludesSignatures(): void
    {
        $class = $this->termSchema->classDefinitions->get('TERMINOLOGY');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = $this->formatter->format($class, 'org.openehr.term', $this->termSchema);

        if ($class->properties->count() > 0) {
            self::assertStringContainsString('*Attributes*', $output);
            // Signatures contain backtick-wrapped types
            self::assertStringContainsString('`', $output);
        }
    }

    #[Test]
    public function formatTypeResolvesKnownTypeToCrossReference(): void
    {
        $output = $this->formatter->formatType('String', 'org.openehr.base', $this->baseSchema);

        // Known type should produce an xref or link, not just plain text
        self::assertNotSame('String', $output);
    }

    #[Test]
    public function formatTypeFallsBackForSingleLetterTypes(): void
    {
        $output = $this->formatter->formatType('T', 'org.openehr.base', $this->baseSchema);

        self::assertSame('T', $output);
    }

    #[Test]
    public function formatTypeReturnsKeywordsAsIs(): void
    {
        foreach (['void', 'null', 'false', 'true'] as $keyword) {
            self::assertSame($keyword, $this->formatter->formatType($keyword, 'org.openehr.base', $this->baseSchema));
        }
    }

    #[Test]
    public function formatTextEscapesCurlyBraces(): void
    {
        $output = $this->formatter->formatText('Use {item} here');

        self::assertStringContainsString('\{item}', $output);
    }

    #[Test]
    public function crossSchemaResolutionWorks(): void
    {
        // UUID is in base, should be resolvable when formatting term schema
        $output = $this->formatter->formatType('UUID', 'org.openehr.term', $this->termSchema);

        // Should not be plain text — should be resolved via cross-schema lookup
        self::assertNotSame('UUID', $output);
    }
}
