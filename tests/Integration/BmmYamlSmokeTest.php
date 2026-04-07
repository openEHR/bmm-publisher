<?php

declare(strict_types=1);

namespace Tests\Integration;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use OpenEHR\BmmPublisher\Writer\BmmYaml;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BmmYamlSmokeTest extends TestCase
{
    private string $tempOutput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bmm_publisher_yaml_smoke_' . uniqid('', true);
        self::assertTrue(mkdir($this->tempOutput, 0700, true));
        putenv('BMM_OUTPUT_DIR=' . $this->tempOutput);
        OutputDir::reset();
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
    public function writesYamlFileForLoadedSchema(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_term_3.0.0');

        (new BmmYaml($collection))();

        $expectedFile = $this->tempOutput . DIRECTORY_SEPARATOR . 'BMM-YAML' . DIRECTORY_SEPARATOR . 'openehr_term_3.0.0.bmm.yaml';
        self::assertFileExists($expectedFile);
        $content = (string) file_get_contents($expectedFile);
        self::assertNotSame('', $content);
        self::assertStringContainsString('rm_publisher', $content);
    }
}
