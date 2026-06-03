<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use OpenEHR\BmmPublisher\Writer\Formatter\Odin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OdinTest extends TestCase
{
    /** @param array<string, mixed> $schema */
    private function format(array $schema): string
    {
        return (new Odin())->format($schema);
    }

    /** Every `<` opened must be matched by a `>`, ignoring string content, comments and `|...|` intervals. */
    private function isBalanced(string $odin): bool
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
            if ($c === '"') {
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

    #[Test]
    public function rendersHeaderScalarsAndBooleans(): void
    {
        $out = $this->format(['bmm_version' => '2.4', 'schema_name' => 'x', 'is_abstract' => true]);
        self::assertStringContainsString('bmm_version = <"2.4">', $out);
        self::assertStringContainsString('schema_name = <"x">', $out);
        self::assertStringContainsString('is_abstract = <True>', $out);
        self::assertTrue($this->isBalanced($out));
    }

    #[Test]
    public function rendersKeyedHashesWithTypeMarkers(): void
    {
        $out = $this->format([
            'class_definitions' => [
                'FOO' => [
                    'name' => 'FOO',
                    'properties' => [
                        'value' => ['_type' => 'P_BMM_SINGLE_PROPERTY', 'name' => 'value', 'type' => 'String'],
                    ],
                ],
            ],
        ]);
        self::assertStringContainsString('["FOO"] = <', $out);
        self::assertStringContainsString('["value"] = (P_BMM_SINGLE_PROPERTY) <', $out);
        self::assertStringContainsString('type = <"String">', $out);
        self::assertTrue($this->isBalanced($out));
    }

    #[Test]
    public function rendersStringLists(): void
    {
        $single = $this->format(['ancestors' => ['Any']]);
        self::assertStringContainsString('ancestors = <"Any", ...>', $single, 'single-item list gets a continuation marker');

        $multi = $this->format(['ancestors' => ['ITEM', 'DATA_VALUE']]);
        self::assertStringContainsString('ancestors = <"ITEM", "DATA_VALUE">', $multi);
    }

    #[Test]
    public function rendersIntegerEnumerationValuesUnquoted(): void
    {
        $out = $this->format(['item_values' => [0, 1, 2]]);
        self::assertStringContainsString('item_values = <0, 1, 2>', $out);
    }

    #[Test]
    public function rendersCardinalityAsOdinInterval(): void
    {
        self::assertStringContainsString(
            'cardinality = <|>=0|>',
            $this->format(['cardinality' => ['lower' => 0, 'upper_unbounded' => true]]),
        );
        self::assertStringContainsString(
            'cardinality = <|1..4|>',
            $this->format(['cardinality' => ['lower' => 1, 'upper' => 4]]),
        );
    }

    #[Test]
    public function rendersNestedTypeDefBlocks(): void
    {
        $out = $this->format([
            'type_def' => ['container_type' => 'List', 'type' => 'ITEM'],
        ]);
        self::assertStringContainsString('type_def = <', $out);
        self::assertStringContainsString('container_type = <"List">', $out);
        self::assertStringContainsString('type = <"ITEM">', $out);
    }

    #[Test]
    public function rendersInvariantsAsKeyedStrings(): void
    {
        $out = $this->format(['invariants' => ['Validity' => 'x > 0']]);
        self::assertStringContainsString('["Validity"] = <"x > 0">', $out);
        self::assertTrue($this->isBalanced($out));
    }

    #[Test]
    public function escapesQuotesAndBackslashesInStrings(): void
    {
        $out = $this->format(['schema_description' => 'a "b" \\ c']);
        self::assertStringContainsString('schema_description = <"a \\"b\\" \\\\ c">', $out);
        self::assertTrue($this->isBalanced($out));
    }

    #[Test]
    public function rendersMixedGenericParametersAsIndexedHash(): void
    {
        // A generic_parameters list that contains an object (not just strings) becomes an indexed hash.
        $out = $this->format([
            'generic_parameters' => ['String', ['_type' => 'P_BMM_GENERIC_TYPE', 'root_type' => 'TUPLE2']],
        ]);
        self::assertStringContainsString('[1] = <"String">', $out);
        self::assertStringContainsString('[2] = (P_BMM_GENERIC_TYPE) <', $out);
        self::assertTrue($this->isBalanced($out));
    }
}
