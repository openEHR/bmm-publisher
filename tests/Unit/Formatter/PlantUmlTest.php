<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use Cadasto\OpenEHR\BMM\Model\BmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationString;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Formatter\PlantUml;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlantUmlTest extends TestCase
{
    private BmmSchemaCollection $collection;
    private BmmSchema $termSchema;
    private BmmSchema $baseSchema;
    private PlantUml $formatter;

    protected function setUp(): void
    {
        $this->collection = new BmmSchemaCollection();
        $this->collection->load('openehr_term_3.0.0');
        $this->collection->load('openehr_base_1.0.4');

        $schemas = array_values(iterator_to_array($this->collection));
        $this->termSchema = $schemas[0];
        $this->baseSchema = $schemas[1];

        $this->formatter = new PlantUml($this->collection);
    }

    #[Test]
    public function formatClassProducesPlantUmlClassBlock(): void
    {
        $class = $this->termSchema->classDefinitions->get('CODE');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = $this->formatter->format($class, 'org.openehr.term', $this->termSchema);

        self::assertStringContainsString('class CODE', $output);
        self::assertStringContainsString('{', $output);
        self::assertStringContainsString('}', $output);
    }

    #[Test]
    public function formatClassIncludesProperties(): void
    {
        $class = $this->termSchema->classDefinitions->get('TERMINOLOGY');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = $this->formatter->format($class, 'org.openehr.term', $this->termSchema);

        if ($class->properties->count() > 0) {
            // PlantUML properties appear as field declarations
            self::assertStringContainsString(':', $output);
        }
    }

    #[Test]
    public function formatEnumProducesPlantUmlEnumBlock(): void
    {
        $enum = $this->termSchema->classDefinitions->get('TERMINOLOGY_STATUS');
        self::assertInstanceOf(BmmEnumerationString::class, $enum);

        $output = $this->formatter->format($enum, 'org.openehr.term', $this->termSchema);

        self::assertStringContainsString('enum TERMINOLOGY_STATUS', $output);
    }

    #[Test]
    public function formatClassWithFunctionsIncludesMethods(): void
    {
        $class = $this->baseSchema->classDefinitions->get('ARCHETYPE_ID');
        self::assertInstanceOf(BmmClass::class, $class);
        self::assertGreaterThan(0, $class->functions->count());

        $output = $this->formatter->format($class, 'org.openehr.base', $this->baseSchema);

        // Functions appear as method declarations with parentheses
        self::assertStringContainsString('(', $output);
    }

    #[Test]
    public function formatClassWithAncestorsIncludesInheritanceRelation(): void
    {
        $class = $this->baseSchema->classDefinitions->get('ARCHETYPE_ID');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = $this->formatter->format($class, 'org.openehr.base', $this->baseSchema);

        if (!empty($class->ancestors)) {
            // PlantUML inheritance uses --|> or <|--
            self::assertTrue(
                str_contains($output, '--|>') || str_contains($output, '<|--'),
                'Expected inheritance arrow in PlantUML output'
            );
        }
    }

    #[Test]
    public function formatPackageProducesPackageDiagram(): void
    {
        /** @var BmmPackage $package */
        $package = $this->termSchema->packages->getIterator()->current();
        self::assertInstanceOf(BmmPackage::class, $package);

        $output = $this->formatter->format($package, 'org.openehr.term', $this->termSchema);

        // Package diagrams contain class definitions
        self::assertStringContainsString('class ', $output);
    }

    #[Test]
    public function formatPackageProducesGrayedOutExternalAncestor(): void
    {
        /** @var BmmPackage $package */
        $package = $this->baseSchema->packages->get("org.openehr.base.bmm_persistence");
        self::assertInstanceOf(BmmPackage::class, $package);

        $output = $this->formatter->format($package, 'org.openehr.term', $this->termSchema);

        // Package diagrams contain class definitions
        self::assertStringContainsString('class BMM_SCHEMA #whitesmoke;line:gray;line.dotted;text:gray', $output);
    }

    #[Test]
    public function formatPackageProducesAbstractExternalAncestor(): void
    {
        /** @var BmmPackage $package */
        $package = $this->baseSchema->packages->get("org.openehr.base.bmm_persistence");
        self::assertInstanceOf(BmmPackage::class, $package);

        $output = $this->formatter->format($package, 'org.openehr.term', $this->termSchema);

        // Package diagrams contain class definitions
        self::assertStringContainsString('abstract class BMM_SCHEMA', $output);
    }

    #[Test]
    public function formatPackageProducesInheritanceRelationForExternalAncestor(): void
    {
        /** @var BmmPackage $package */
        $package = $this->baseSchema->packages->get("org.openehr.base.bmm_persistence");
        self::assertInstanceOf(BmmPackage::class, $package);

        $output = $this->formatter->format($package, 'org.openehr.term', $this->termSchema);

        // Package diagrams contain class definitions
        self::assertStringContainsString('P_BMM_PACKAGE_CONTAINER <|-- BMM_SCHEMA', $output);
    }

    #[Test]
    public function formatTypeReturnsTypeName(): void
    {
        self::assertSame('String', $this->formatter->formatType('String'));
        self::assertSame('Integer', $this->formatter->formatType('Integer'));
    }

    #[Test]
    public function classDiagramDeclaresASharedAncestorOnce(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_rm_1.2.0');
        $collection->load('openehr_base_1.3.0');
        $rmSchema = iterator_to_array($collection, false)[0];
        // DV_DATE reaches Ordered through DV_ORDERED and through Iso8601_date -> Iso8601_type -> Temporal
        $class = $rmSchema->classDefinitions->get('DV_DATE');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new PlantUml($collection))->format($class, 'org.openehr.rm.data_types', $rmSchema);

        self::assertSame(1, substr_count($output, 'abstract class Ordered '));
        $arrows = array_values(array_filter(explode("\n", $output), static fn (string $line): bool => str_contains($line, ' <|-- ')));
        self::assertSame(array_values(array_unique($arrows)), $arrows);
        self::assertContains('Ordered <|-- DV_ORDERED', $arrows);
        self::assertContains('Ordered <|-- Temporal', $arrows);
    }

    #[Test]
    public function classDiagramListsConstants(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.3.0');
        $schema = iterator_to_array($collection, false)[0];
        $class = $schema->classDefinitions->get('Time_Definitions');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new PlantUml($collection))->format($class, 'org.openehr.base.foundation_types', $schema);

        self::assertStringContainsString("  + {static} Seconds_in_minute : Integer = 60 {readOnly}\n", $output);
    }

    #[Test]
    public function classDiagramListsAConstantWithoutValueWithoutAnEqualsSign(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_lang_1.1.0');
        $collection->load('openehr_base_1.3.0');
        $schema = iterator_to_array($collection, false)[0];
        $class = $schema->classDefinitions->get('BMM_DEFINITIONS'); // Bmm_internal_version has no value
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new PlantUml($collection))->format($class, 'org.openehr.lang.bmm', $schema);

        self::assertStringContainsString("  + {static} Bmm_internal_version : String {readOnly}\n", $output);
    }

    #[Test]
    public function classDiagramShowsTheConstraintOfAGenericParameter(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_rm_1.2.0');
        $collection->load('openehr_base_1.3.0');
        $rmSchema = iterator_to_array($collection, false)[0];
        $class = $rmSchema->classDefinitions->get('DV_INTERVAL'); // T: conforms_to_type DV_ORDERED
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new PlantUml($collection))->format($class, 'org.openehr.rm.data_types', $rmSchema);

        self::assertStringContainsString('class DV_INTERVAL <T: DV_ORDERED> {', $output);
    }
}
