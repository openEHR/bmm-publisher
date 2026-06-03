<?php

declare(strict_types=1);

namespace Tests\Integration;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use OpenEHR\BmmPublisher\Writer\BmmOdin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BmmOdinSmokeTest extends TestCase
{
    private string $tempOutput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bmm_publisher_odin_smoke_' . uniqid('', true);
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
    public function writesConformantOdinForLoadedSchema(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_term_3.0.0');

        (new BmmOdin($collection))();

        $expectedFile = $this->tempOutput . DIRECTORY_SEPARATOR . 'BMM-ODIN' . DIRECTORY_SEPARATOR . 'openehr_term_3.0.0.bmm';
        self::assertFileExists($expectedFile);
        $odin = (string) file_get_contents($expectedFile);

        self::assertStringContainsString('bmm_version = <', $odin);
        self::assertStringContainsString('schema_name = <"term">', $odin);
        self::assertStringContainsString('class_definitions = <', $odin);
        self::assertMatchesRegularExpression('/\["[A-Z_]+"\] = </', $odin, 'classes are keyed by name');
        self::assertTrue($this->bracketsBalanced($odin), 'every < is matched by a >');
    }

    /**
     * `BmmOdin` feeds `jsonSerialize()` straight to the formatter (no JSON round-trip), which only
     * holds while the model serialises to a pure array tree. This pins that contract against the
     * AM schema — the one with nested generic types — so a future upstream regression that left a
     * live model object embedded would fail here rather than in published `.bmm` output.
     */
    #[Test]
    public function jsonSerializeReturnsAnObjectFreeTree(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_am_2.4.0');

        foreach ($collection as $schema) {
            self::assertSame([], $this->objectPaths($schema->jsonSerialize()), 'no model objects may remain in the serialised tree');
        }
    }

    /**
     * @param array<array-key, mixed> $tree
     * @return list<string> paths at which a non-array, non-scalar value was found
     */
    private function objectPaths(array $tree, string $path = ''): array
    {
        $found = [];
        foreach ($tree as $key => $value) {
            $here = $path . '/' . $key;
            if (\is_array($value)) {
                $found = array_merge($found, $this->objectPaths($value, $here));
            } elseif (!\is_scalar($value) && $value !== null) {
                $found[] = $here . ' => ' . get_debug_type($value);
            }
        }

        return $found;
    }

    /** String-, comment- and interval-aware delimiter balance check. */
    private function bracketsBalanced(string $odin): bool
    {
        $depth = 0;
        $len = \strlen($odin);
        $inString = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $odin[$i];
            if ($inString) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($c === '-' && ($odin[$i + 1] ?? '') === '-') {
                $nl = strpos($odin, "\n", $i);
                $i = $nl === false ? $len : $nl;
            } elseif ($c === '"') {
                $inString = true;
            } elseif ($c === '<' && ($odin[$i + 1] ?? '') === '|') {
                $end = strpos($odin, '|>', $i + 2);
                if ($end !== false) {
                    $i = $end + 1;
                }
            } elseif ($c === '<') {
                $depth++;
            } elseif ($c === '>') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }
}
