<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use Cadasto\OpenEHR\BMM\Model\BmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationString;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Formatter\AntoraClassMap;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Tests\Unit\Support\RecordingLogger;

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

    #[Test]
    public function formatTypeWarnsOnceForATypeNoLoadedSchemaDefines(): void
    {
        $logger = new RecordingLogger();
        $collection = new BmmSchemaCollection($logger);
        $collection->load('openehr_term_3.0.0'); // BASE, which defines String, is deliberately not loaded
        $termSchema = iterator_to_array($collection, false)[0];
        $formatter = new AsciidocDefinition($collection, true);

        $first = $formatter->formatType('String', 'org.openehr.term', $termSchema);
        $formatter->formatType('String', 'org.openehr.term', $termSchema);

        self::assertSame('link:/classes/String[String]', $first);
        $warnings = $logger->recordsAt(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertSame('String', $warnings[0]['context']['type']);
        self::assertSame('openehr_term_3.0.0', $warnings[0]['context']['schema']);
    }

    #[Test]
    public function formatTypeDoesNotWarnForTypesTheLoadedSchemasDefine(): void
    {
        $logger = new RecordingLogger();
        $collection = new BmmSchemaCollection($logger);
        $collection->load('openehr_term_3.0.0');
        $collection->load('openehr_base_1.0.4');
        $termSchema = iterator_to_array($collection, false)[0];
        $formatter = new AsciidocDefinition($collection, true);

        $formatter->formatType('String', 'org.openehr.term', $termSchema); // defined in BASE
        $formatter->formatType('CODE', 'org.openehr.term', $termSchema); // defined in TERM

        self::assertSame([], $logger->recordsAt(LogLevel::WARNING));
    }

    #[Test]
    public function formatTypeNamesAnEmptyTypeReferenceInItsWarning(): void
    {
        $logger = new RecordingLogger();
        $collection = new BmmSchemaCollection($logger);
        $collection->load('openehr_term_3.0.0');
        $termSchema = iterator_to_array($collection, false)[0];

        (new AsciidocDefinition($collection, true))->formatType('', 'org.openehr.term', $termSchema);

        $warnings = $logger->recordsAt(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertStringContainsString('empty type name', $warnings[0]['message']);
    }

    #[Test]
    public function formatTypeRendersNothingForAnEmptyTypeReference(): void
    {
        self::assertSame('', $this->formatter->formatType('', 'org.openehr.term', $this->termSchema));
    }

    #[Test]
    public function formatClassShowsAFunctionWithoutResultTypeWithoutOne(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_lang_1.0.0'); // BMM_ROUTINE.signature has result type ""
        $collection->load('openehr_base_1.1.0');
        $langSchema = iterator_to_array($collection, false)[0];
        $class = $langSchema->classDefinitions->get('BMM_ROUTINE');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new AsciidocDefinition($collection))->format($class, 'org.openehr.lang', $langSchema);

        self::assertStringContainsString("|*signature* ()\n", $output);
        self::assertStringNotContainsString('link:/classes/[]', $output);
        self::assertStringNotContainsString('(): ``', $output);
    }

    #[Test]
    public function formatTypeResolvesATypeNameThatDiffersOnlyInCase(): void
    {
        $logger = new RecordingLogger();
        $collection = new BmmSchemaCollection($logger);
        $collection->load('openehr_term_3.0.0');
        $collection->load('openehr_base_1.0.4'); // defines Iso8601_date
        $termSchema = iterator_to_array($collection, false)[0];

        $output = (new AsciidocDefinition($collection))->formatType('ISO8601_DATE', 'org.openehr.term', $termSchema);

        self::assertSame('xref:{class_iso8601_date}[ISO8601_DATE]', $output);
        self::assertSame([], $logger->recordsAt(LogLevel::WARNING));
    }

    #[Test]
    public function formatTypeResolvesByteToTheBaseOctetPrimitive(): void
    {
        $output = $this->formatter->formatType('Byte', 'org.openehr.term', $this->termSchema);

        self::assertSame('xref:{class_octet}[Byte]', $output);
    }

    #[Test]
    public function formatTypeLinksByteToTheOctetAnchorInLegacyFormat(): void
    {
        $output = (new AsciidocDefinition($this->collection, true))->formatType('Byte', 'org.openehr.term', $this->termSchema);

        self::assertStringStartsWith('link:/releases/BASE/', $output);
        self::assertStringEndsWith('.html#_octet_class[Byte^]', $output);
    }

    #[Test]
    public function formatTypePrefersAnExactMatchInAnotherSchemaOverACaseVariant(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_am_1.4.0'); // defines CARDINALITY, loaded first as in `adoc all`
        $collection->load('openehr_am_2.4.0'); // C_ATTRIBUTE.cardinality is a Cardinality
        $collection->load('openehr_base_1.3.0'); // defines Cardinality
        $am2Schema = iterator_to_array($collection, false)[1];

        $output = (new AsciidocDefinition($collection))->formatType('Cardinality', 'org.openehr.am.aom2', $am2Schema);

        self::assertSame('xref:{class_cardinality}[Cardinality]', $output);
    }

    #[Test]
    public function formatClassKeepsThePlusSignsOfDvOrdinal(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_rm_1.2.0');
        $collection->load('openehr_base_1.3.0');
        $schema = iterator_to_array($collection, false)[0];
        $class = $schema->classDefinitions->get('DV_ORDINAL');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new AsciidocDefinition($collection))->format($class, 'org.openehr.rm.data_types', $schema);

        self::assertStringContainsString('`{neg, trace, {plus}, {plus}{plus}, {plus}{plus}{plus}}`', $output);
    }

    #[Test]
    public function formatClassEscapesFunctionAliases(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.3.0');
        $schema = iterator_to_array($collection, false)[0];
        $class = $schema->classDefinitions->get('Ordered') ?? $schema->primitiveTypes->get('Ordered');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new AsciidocDefinition($collection))->format($class, 'org.openehr.base.foundation_types', $schema);

        // Unescaped, Asciidoctor renders "<=" as a double arrow
        self::assertStringContainsString('__alias__ "\<=", "≤"', $output);
    }

    #[Test]
    public function formatClassShowsAConstantWithoutValueWithoutAnEqualsSign(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_lang_1.1.0');
        $collection->load('openehr_base_1.3.0');
        $schema = iterator_to_array($collection, false)[0];
        $class = $schema->classDefinitions->get('BMM_DEFINITIONS'); // Bmm_internal_version has no value
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new AsciidocDefinition($collection))->format($class, 'org.openehr.lang.bmm', $schema);

        self::assertMatchesRegularExpression('/^\|\*Bmm_internal_version\*: `[^`]*\[String\]`$/m', $output);
    }

    #[Test]
    public function formatClassShowsNoDefaultForAnEmptyDefault(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.3.0');
        $schema = iterator_to_array($collection, false)[0];
        $class = $schema->classDefinitions->get('RESOURCE_DESCRIPTION'); // parent_resource has default ""
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new AsciidocDefinition($collection))->format($class, 'org.openehr.base.resource', $schema);

        self::assertStringContainsString('*parent_resource*', $output);
        self::assertStringNotContainsString('{default{nbsp}={nbsp}}', $output);
    }

    #[Test]
    public function legacyFormatLinksATypeFromAPackageWhoseNameOnlyStartsLikeThisOne(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_lang_1.1.0');
        $collection->load('openehr_base_1.3.0');
        $langSchema = iterator_to_array($collection, false)[0];
        $formatter = new AsciidocDefinition($collection, true);

        // P_BMM_SCHEMA is in org.openehr.lang.bmm_persistence, a different page from org.openehr.lang.bmm
        $other = $formatter->formatType('P_BMM_SCHEMA', 'org.openehr.lang.bmm', $langSchema);
        $same = $formatter->formatType('BMM_CLASS', 'org.openehr.lang.bmm', $langSchema);

        self::assertStringStartsWith('link:/releases/LANG/', $other);
        self::assertStringEndsWith('/bmm_persistence.html#_p_bmm_schema_class[P_BMM_SCHEMA^]', $other);
        self::assertSame('<<_bmm_class_class,BMM_CLASS>>', $same);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function parallelModelClasses(): iterable
    {
        yield 'AOM 1.4' => ['openehr_am_1.4.0', 'C_OBJECT', 'xref:{class_aom14_c_object}[C_OBJECT]'];
        yield 'AOM 2' => ['openehr_am_2.4.0', 'C_OBJECT', 'xref:{class_aom2_c_object}[C_OBJECT]'];
        yield 'BMM 3' => ['openehr_lang_1.1.0-bmm3', 'BMM_CLASS', 'xref:{class_bmm3_bmm_class}[BMM_CLASS]'];
        yield 'legacy BMM' => ['openehr_lang_1.1.0', 'BMM_CLASS', 'xref:{class_bmm_class}[BMM_CLASS]'];
    }

    /**
     * The attribute is named after the class partial, so a model that shares class names with another gets its own.
     */
    #[Test]
    #[DataProvider('parallelModelClasses')]
    public function formatTypeNamesTheAttributeAfterTheClassPartial(string $schemaId, string $type, string $expected): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load($schemaId);
        $collection->load('openehr_base_1.3.0');
        $schema = iterator_to_array($collection, false)[0];

        self::assertSame($expected, (new AsciidocDefinition($collection))->formatType($type, 'org.openehr.base.foundation_types', $schema));
    }

    #[Test]
    public function formatTypeChecksTheAttributeAgainstTheSiteClassMap(): void
    {
        $logger = new RecordingLogger();
        $classMap = new AntoraClassMap(['class_string' => '{page_base_primitive_types}#_string_class'], 'global-vars.yml', $logger);
        $formatter = new AsciidocDefinition($this->collection, false, $classMap);

        $formatter->formatType('String', 'org.openehr.term', $this->termSchema);
        $formatter->formatType('Boolean', 'org.openehr.term', $this->termSchema);

        $warnings = $logger->recordsAt(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertSame('class_boolean', $warnings[0]['context']['attribute']);
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: string, 2: string, 3: string}>
     */
    public static function legacyDocumentLinks(): iterable
    {
        yield 'composition is in the EHR IM' => [
            ['openehr_rm_1.2.0', 'openehr_base_1.3.0'], 'CONTENT_ITEM', 'org.openehr.rm.common',
            'link:/releases/RM/{rm_release}/ehr.html#_content_item_class[CONTENT_ITEM^]',
        ];
        yield 'composition from the ehr package is on the same page' => [
            ['openehr_rm_1.2.0', 'openehr_base_1.3.0'], 'CONTENT_ITEM', 'org.openehr.rm.ehr',
            '<<_content_item_class,CONTENT_ITEM>>',
        ];
        yield 'security is in the EHR IM' => [
            ['openehr_rm_1.1.0', 'openehr_base_1.2.0'], 'ACCESS_CONTROL_SETTINGS', 'org.openehr.rm.common',
            'link:/releases/RM/{rm_release}/ehr.html#_access_control_settings_class[ACCESS_CONTROL_SETTINGS^]',
        ];
        yield 'AOM2 document name is upper case' => [
            ['openehr_am_2.4.0', 'openehr_lang_1.1.0', 'openehr_base_1.3.0'], 'C_OBJECT', 'org.openehr.lang.beom',
            'link:/releases/AM/{am_release}/AOM2.html#_c_object_class[C_OBJECT^]',
        ];
        yield 'BEOM is a chapter of BEL' => [
            ['openehr_am_2.4.0', 'openehr_lang_1.1.0', 'openehr_base_1.3.0'], 'ASSERTION', 'org.openehr.am.aom2',
            'link:/releases/LANG/{lang_release}/BEL.html#_assertion_class[ASSERTION^]',
        ];
        yield 'AOM1.4 document name keeps its case and dot' => [
            ['openehr_am_1.4.0', 'openehr_base_1.3.0'], 'C_ATTRIBUTE', 'org.openehr.rm.common',
            'link:/releases/AM/{am_release}/AOM1.4.html#_c_attribute_class[C_ATTRIBUTE^]',
        ];
        // The table's own package is renamed too, so its classes stay on the same page.
        yield 'from inside composition, the EHR IM is the same page' => [
            ['openehr_rm_1.2.0', 'openehr_base_1.3.0'], 'EVENT_CONTEXT', 'org.openehr.rm.composition',
            '<<_event_context_class,EVENT_CONTEXT>>',
        ];
        yield 'from inside aom2, AOM2 is the same page' => [
            ['openehr_am_2.4.0', 'openehr_lang_1.1.0', 'openehr_base_1.3.0'], 'C_ATTRIBUTE', 'org.openehr.am.aom2',
            '<<_c_attribute_class,C_ATTRIBUTE>>',
        ];
        yield 'from inside aom14, AOM1.4 is the same page' => [
            ['openehr_am_1.4.0', 'openehr_base_1.3.0'], 'C_ATTRIBUTE', 'org.openehr.am.aom14',
            '<<_c_attribute_class,C_ATTRIBUTE>>',
        ];
        yield 'from inside beom, BEL is the same page' => [
            ['openehr_lang_1.1.0', 'openehr_base_1.3.0'], 'EXPR_LEAF', 'org.openehr.lang.beom',
            '<<_expr_leaf_class,EXPR_LEAF>>',
        ];
    }

    /**
     * @param list<string> $schemaIds
     */
    #[Test]
    #[DataProvider('legacyDocumentLinks')]
    public function legacyFormatLinksToTheDocumentThatHoldsThePackage(array $schemaIds, string $type, string $prefix, string $expected): void
    {
        $collection = new BmmSchemaCollection();
        foreach ($schemaIds as $schemaId) {
            $collection->load($schemaId);
        }
        $schema = iterator_to_array($collection, false)[0];

        self::assertSame($expected, (new AsciidocDefinition($collection, true))->formatType($type, $prefix, $schema));
    }
}
