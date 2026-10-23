<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use Cadasto\OpenEHR\BMM\Model\BmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocDefinition;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocEffective;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TerminologyLinkTest extends TestCase
{
    private BmmSchemaCollection $collection;
    private BmmSchema $rmSchema;

    protected function setUp(): void
    {
        $this->collection = new BmmSchemaCollection();
        $this->collection->load('openehr_rm_1.2.0');
        $this->collection->load('openehr_base_1.3.0');

        $schemas = array_values(iterator_to_array($this->collection));
        $this->rmSchema = $schemas[0];
    }

    private function rmClass(string $name): BmmClass
    {
        $class = $this->rmSchema->classDefinitions->get($name);
        self::assertInstanceOf(BmmClass::class, $class);
        return $class;
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function codedAttributes(): array
    {
        return [
            'vocabulary from own invariant' => ['EVENT_CONTEXT', 'setting', '#_setting[openEHR Terminology, setting group]'],
            'code set from own invariant' => ['COMPOSITION', 'territory', '#_countries[openEHR code set countries]'],
            'second code set on same class' => ['COMPOSITION', 'language', '#_languages[openEHR code set languages]'],
            'invariant on ancestor class' => ['ORIGINAL_VERSION', 'lifecycle_state', '#_version_lifecycle_state['],
            'aliased group id' => ['ELEMENT', 'null_flavour', '#_null_flavours[openEHR Terminology, null flavours group]'],
            'explicit map, extract type' => [
                'EXTRACT_SPEC',
                'extract_type',
                '#_extract_content_type[openEHR Terminology, extract content type group]',
            ],
            'explicit map, extract action' => [
                'EXTRACT_ACTION_REQUEST',
                'action',
                '#_extract_action_type[openEHR Terminology, extract action type group]',
            ],
            'explicit map, update trigger events' => [
                'EXTRACT_UPDATE_SPEC',
                'trigger_events',
                '#_extract_update_trigger_event_type[openEHR Terminology, extract update trigger event type group]',
            ],
        ];
    }

    #[Test]
    #[DataProvider('codedAttributes')]
    public function linksCodedAttributeToTermSection(string $className, string $property, string $expected): void
    {
        $formatter = new AsciidocDefinition($this->collection);

        $link = $formatter->formatTerminologyLink($this->rmClass($className), $property, $this->rmSchema);

        self::assertStringStartsWith("\n\nTerminology: xref:TERM:SupportTerminology:terminology.adoc", $link);
        self::assertStringContainsString($expected, $link);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function amCodedAttributes(): array
    {
        return [
            'code set via has_concept_id' => ['ARCHETYPE_TERMINOLOGY', 'original_language', '#_languages[openEHR code set languages]'],
            'invariant on ancestor in another schema' => ['AUTHORED_ARCHETYPE', 'original_language', '#_languages[openEHR code set languages]'],
        ];
    }

    #[Test]
    #[DataProvider('amCodedAttributes')]
    public function linksAmCodedAttributeToTermSection(string $className, string $property, string $expected): void
    {
        // the dependencies `make publish-all` passes, so AUTHORED_RESOURCE resolves from BASE, not from RM
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_am_2.4.0');
        $collection->load('openehr_lang_1.1.0');
        $collection->load('openehr_base_1.3.0');
        $amSchema = iterator_to_array($collection)['openehr_am_2.4.0'];
        $class = $amSchema->classDefinitions->get($className);
        self::assertInstanceOf(BmmClass::class, $class);

        $link = (new AsciidocDefinition($collection))->formatTerminologyLink($class, $property, $amSchema);

        self::assertStringStartsWith("\n\nTerminology: xref:TERM:SupportTerminology:terminology.adoc", $link);
        self::assertStringContainsString($expected, $link);
    }

    #[Test]
    public function noLinkForAttributeWithoutGroup(): void
    {
        $formatter = new AsciidocDefinition($this->collection);

        self::assertSame('', $formatter->formatTerminologyLink($this->rmClass('EVENT_CONTEXT'), 'location', $this->rmSchema));
        // archetype-defined coding, not openEHR Terminology
        self::assertSame('', $formatter->formatTerminologyLink($this->rmClass('ISM_TRANSITION'), 'careflow_step', $this->rmSchema));
    }

    #[Test]
    public function noLinkInLegacyFormat(): void
    {
        $formatter = new AsciidocDefinition($this->collection, true);

        self::assertSame('', $formatter->formatTerminologyLink($this->rmClass('EVENT_CONTEXT'), 'setting', $this->rmSchema));
    }

    #[Test]
    public function definitionAndEffectiveTablesContainTheLink(): void
    {
        $class = $this->rmClass('EVENT_CONTEXT');
        $expected = 'Terminology: xref:TERM:SupportTerminology:terminology.adoc#_setting[openEHR Terminology, setting group]';

        $definition = (new AsciidocDefinition($this->collection))->format($class, 'org.openehr.rm.composition', $this->rmSchema);
        $effective = (new AsciidocEffective($this->collection))->format($class, 'org.openehr.rm.composition', $this->rmSchema);

        self::assertStringContainsString($expected, $definition);
        self::assertStringContainsString($expected, $effective);
    }
}
