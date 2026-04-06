<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\ResourcesDir;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BmmSchemaCollectionTest extends TestCase
{

    #[Test]
    public function inputDirPointsToResourcesDirectory(): void
    {
        $expected = ResourcesDir::path() . DIRECTORY_SEPARATOR;
        self::assertSame($expected, BmmSchemaCollection::inputDir());
        self::assertStringEndsWith('resources' . DIRECTORY_SEPARATOR, BmmSchemaCollection::inputDir());
    }

    #[Test]
    public function loadWithoutExtensionLoadsSchema(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.0.4');

        self::assertSame(1, $collection->count());
        $schemas = iterator_to_array($collection, false);
        self::assertCount(1, $schemas);
        self::assertInstanceOf(BmmSchema::class, $schemas[0]);
        self::assertSame('openehr_base_1.0.4', $schemas[0]->getSchemaId());
    }

    #[Test]
    public function loadWithBmmJsonExtensionLoadsSameSchema(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.0.4.bmm.json');

        self::assertSame(1, $collection->count());
        $schemas = iterator_to_array($collection, false);
        self::assertSame('openehr_base_1.0.4', $schemas[0]->getSchemaId());
    }

    #[Test]
    public function loadMissingFileThrowsRuntimeException(): void
    {
        $collection = new BmmSchemaCollection();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File missing or not readable');

        $collection->load('nonexistent_schema_zzzz');
    }

    #[Test]
    public function getClassReturnsModelForKnownType(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.0.4');

        $class = $collection->getClass('UUID');
        self::assertNotNull($class);
        self::assertSame('UUID', $class->getName());
    }

    #[Test]
    public function getClassReturnsNullForUnknownType(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.0.4');

        self::assertNull($collection->getClass('NotARealBmmClassName'));
    }

    #[Test]
    public function getClassPackageQNameReturnsNonEmptyForKnownType(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.0.4');

        $qname = $collection->getClassPackageQName('UUID');
        self::assertNotNull($qname);
        self::assertStringContainsString('org.openehr', $qname);
    }

    #[Test]
    public function availableSchemasListsBasenamesIncludingKnownFile(): void
    {
        $names = BmmSchemaCollection::availableSchemas();

        self::assertContains('openehr_base_1.0.4.bmm.json', $names);
        foreach ($names as $name) {
            self::assertStringEndsWith('.bmm.json', $name);
        }
    }

    #[Test]
    public function loadMultipleSchemasIncreasesCountAndResolvesClassAcrossFirst(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.0.4');
        $collection->load('openehr_term_3.0.0');

        self::assertSame(2, $collection->count());

        $class = $collection->getClass('UUID');
        self::assertNotNull($class);
        self::assertSame('UUID', $class->getName());
    }
}
