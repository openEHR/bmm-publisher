<?php

declare(strict_types=1);

namespace Tests\Integration;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use OpenEHR\BmmPublisher\Writer\Asciidoc;
use OpenEHR\BmmPublisher\Writer\BmmJsonSplit;
use OpenEHR\BmmPublisher\Writer\BmmYaml;
use OpenEHR\BmmPublisher\Writer\PlantUml;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WriterTest extends TestCase
{
    private string $tempOutput;
    private BmmSchemaCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bmm_publisher_writer_' . uniqid('', true);
        self::assertTrue(mkdir($this->tempOutput, 0700, true));
        putenv('BMM_OUTPUT_DIR=' . $this->tempOutput);
        OutputDir::reset();

        $this->collection = new BmmSchemaCollection();
        $this->collection->load('openehr_term_3.0.0');
    }

    protected function tearDown(): void
    {
        putenv('BMM_OUTPUT_DIR');
        OutputDir::reset();
        if (is_dir($this->tempOutput)) {
            $this->deleteTree($this->tempOutput);
        }
        parent::tearDown();
    }

    /** @return list<string> */
    private static function findFiles(string $pattern): array
    {
        $files = glob($pattern);
        return $files !== false ? $files : [];
    }

    private function deleteTree(string $dir): void
    {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) && !is_link($path) ? $this->deleteTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    #[Test]
    public function asciidocWriterCreatesOutputFiles(): void
    {
        (new Asciidoc($this->collection))();

        $adocDir = $this->tempOutput . DIRECTORY_SEPARATOR . 'Adoc';
        self::assertDirectoryExists($adocDir);

        $schemaDir = $adocDir . DIRECTORY_SEPARATOR . 'openehr_term_3.0.0';
        self::assertDirectoryExists($schemaDir);

        // Should have subdirectories for each view
        self::assertDirectoryExists($schemaDir . DIRECTORY_SEPARATOR . 'definitions');
        self::assertDirectoryExists($schemaDir . DIRECTORY_SEPARATOR . 'effective');
        self::assertDirectoryExists($schemaDir . DIRECTORY_SEPARATOR . 'classes');
        self::assertDirectoryExists($schemaDir . DIRECTORY_SEPARATOR . 'BMMs');
        self::assertDirectoryExists($schemaDir . DIRECTORY_SEPARATOR . 'plantUML');

        // Should contain .adoc files
        $adocFiles = self::findFiles($schemaDir . '/definitions/*.adoc');
        self::assertNotEmpty($adocFiles);
    }

    #[Test]
    public function asciidocOutputContainsValidAsciidocStructure(): void
    {
        (new Asciidoc($this->collection))();

        $files = self::findFiles($this->tempOutput . '/Adoc/openehr_term_3.0.0/definitions/*.adoc');
        self::assertNotEmpty($files);

        $content = (string) file_get_contents($files[0]);
        // AsciiDoc tables use |=== delimiters
        self::assertStringContainsString('|===', $content);
    }

    #[Test]
    public function plantUmlWriterCreatesOutputFiles(): void
    {
        (new PlantUml($this->collection))();

        $pumlDir = $this->tempOutput . DIRECTORY_SEPARATOR . 'PlantUML';
        self::assertDirectoryExists($pumlDir);

        $schemaDir = $pumlDir . DIRECTORY_SEPARATOR . 'openehr_term_3.0.0';
        self::assertDirectoryExists($schemaDir);

        // Should contain .puml package files at schema level
        $pumlFiles = self::findFiles($schemaDir . '/*.puml');
        self::assertNotEmpty($pumlFiles, 'Expected .puml files in schema directory');
    }

    #[Test]
    public function plantUmlOutputContainsValidSyntax(): void
    {
        (new PlantUml($this->collection))();

        // Find any .puml file
        $files = self::findFiles($this->tempOutput . '/PlantUML/openehr_term_3.0.0/*.puml');
        self::assertNotEmpty($files);

        $content = (string) file_get_contents($files[0]);
        // PlantUML files should contain class/enum definitions
        self::assertTrue(
            str_contains($content, 'class ') || str_contains($content, 'enum '),
            'Expected PlantUML class or enum definition'
        );
    }

    #[Test]
    public function bmmYamlWriterCreatesYamlFile(): void
    {
        (new BmmYaml($this->collection))();

        $yamlFile = $this->tempOutput . DIRECTORY_SEPARATOR . 'BMM-YAML' . DIRECTORY_SEPARATOR . 'openehr_term_3.0.0.bmm.yaml';
        self::assertFileExists($yamlFile);

        $content = (string) file_get_contents($yamlFile);
        self::assertStringContainsString('rm_publisher', $content);
    }

    #[Test]
    public function bmmYamlOutputUsesTaggedValuesNotTypeKeys(): void
    {
        $this->collection->load('openehr_base_1.0.4');
        (new BmmYaml($this->collection))();

        $yamlFile = $this->tempOutput . DIRECTORY_SEPARATOR . 'BMM-YAML' . DIRECTORY_SEPARATOR . 'openehr_base_1.0.4.bmm.yaml';
        self::assertFileExists($yamlFile);

        $content = (string) file_get_contents($yamlFile);
        // Properties should use YAML tags like !P_BMM_SINGLE_PROPERTY, not _type discriminator keys
        self::assertMatchesRegularExpression('/!P_BMM_\w+/', $content);
        self::assertDoesNotMatchRegularExpression('/^\s+_type:/m', $content);
    }

    #[Test]
    public function bmmJsonSplitWriterCreatesPerTypeFiles(): void
    {
        (new BmmJsonSplit($this->collection))();

        $splitDir = $this->tempOutput . DIRECTORY_SEPARATOR . 'BMM-JSON-development-types';
        self::assertDirectoryExists($splitDir);

        // TERM component should create a TERM subdirectory
        $termDir = $splitDir . DIRECTORY_SEPARATOR . 'TERM';
        self::assertDirectoryExists($termDir);

        // Should contain .bmm.json files named after class types
        $jsonFiles = self::findFiles($termDir . '/*.bmm.json');
        self::assertNotEmpty($jsonFiles);
    }

    #[Test]
    public function bmmJsonSplitOutputContainsSpecUrl(): void
    {
        (new BmmJsonSplit($this->collection))();

        $jsonFiles = self::findFiles($this->tempOutput . '/BMM-JSON-development-types/TERM/*.bmm.json');
        self::assertNotEmpty($jsonFiles);

        $content = (string) file_get_contents($jsonFiles[0]);
        $data = json_decode($content, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('specUrl', $data);
        self::assertStringStartsWith('https://specifications.openehr.org/', $data['specUrl']);
    }

    #[Test]
    public function bmmJsonSplitOutputContainsPackageField(): void
    {
        (new BmmJsonSplit($this->collection))();

        $jsonFiles = self::findFiles($this->tempOutput . '/BMM-JSON-development-types/TERM/*.bmm.json');
        self::assertNotEmpty($jsonFiles);

        $content = (string) file_get_contents($jsonFiles[0]);
        $data = json_decode($content, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('package', $data);
        self::assertStringContainsString('org.openehr', $data['package']);
    }
}
