<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer\Formatter;

/**
 * Serialises a P_BMM schema array (as produced by {@see \Cadasto\OpenEHR\BMM\Model\BmmSchema::jsonSerialize()})
 * into ODIN — the syntax used by hand-authored `.bmm` schema files.
 *
 * ODIN and the BMM JSON are two serialisations of the same P_BMM instance model, so this
 * walks the same tree the YAML/JSON writers consume and emits the equivalent ODIN constructs:
 *
 * - attribute objects → `attr = <...>` blocks (the schema root is emitted without outer brackets);
 * - the `_type` discriminator → an ODIN type marker `(P_BMM_TYPE)` on the enclosing `<...>`;
 * - keyed containers (packages, classes, properties, …) → `["key"] = <...>` hash members;
 * - string lists (ancestors, classes, item_names, …) → `<"a", "b">` (single item gets a `, ...` tail);
 * - `cardinality` intervals → ODIN range literals such as `|>=0|`.
 *
 * @see https://specifications.openehr.org/releases/LANG/development/odin.html
 */
final class Odin
{
    private const INDENT = "\t";

    /**
     * Attributes whose value is a hash keyed by element name/id, rendered as `["key"] = <...>`.
     *
     * @var list<string>
     */
    private const KEYED_HASH = [
        'includes', 'packages', 'primitive_types', 'class_definitions',
        'properties', 'generic_parameter_defs', 'constants', 'functions', 'invariants',
        'parameters', 'pre_conditions', 'post_conditions',
    ];

    /**
     * Preferred emission order; keys not listed follow, in their original order. `name` and
     * `documentation` lead every node that has them (the schema root has neither, so its header
     * attributes come first there).
     */
    private const KEY_ORDER = [
        'name', 'documentation',
        'bmm_version', 'rm_publisher', 'schema_name', 'rm_release',
        'schema_revision', 'schema_lifecycle_state', 'schema_description', 'schema_author',
        'includes', 'archetype_namespace',
        'ancestors', 'is_abstract', 'is_mandatory', 'conforms_to_type',
        'generic_parameter_defs', 'generic_parameters',
        'item_names', 'item_values', 'item_documentations',
        'container_type', 'root_type', 'type', 'type_def',
        'cardinality', 'default', 'result', 'value',
        'classes', 'packages', 'primitive_types', 'properties', 'constants', 'functions', 'invariants',
    ];

    /**
     * Render a whole schema. The root attributes are emitted at column 0 without enclosing brackets.
     *
     * @param array<string, mixed> $schema
     */
    public function format(array $schema): string
    {
        $lines = [];
        foreach ($this->ordered($schema) as $key => $value) {
            if ($key === '_type') {
                continue;
            }
            $lines[] = $this->attribute($key, $value, 0);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Render a single `name = (Type?) <value>` attribute at the given depth.
     */
    private function attribute(string $name, mixed $value, int $depth): string
    {
        $pad = str_repeat(self::INDENT, $depth);

        if (!\is_array($value)) {
            return $pad . $name . ' = <' . $this->scalar($value) . '>';
        }
        if ($name === 'cardinality') {
            return $pad . $name . ' = <|' . $this->interval($value) . '|>';
        }
        // A list of primitives renders as a comma-separated ODIN list; a list (or hash) that
        // contains objects falls through to block(), which emits keyed members instead.
        if (array_is_list($value) && $this->allScalar($value)) {
            return $pad . $name . ' = <' . $this->primitiveList($value) . '>';
        }

        return $pad . $name . ' = ' . $this->block($name, $value, $depth);
    }

    /**
     * Render the `(Type?) <...>` block for an array value: either a keyed hash or an attribute object.
     *
     * @param array<array-key, mixed> $value
     */
    private function block(string $name, array $value, int $depth): string
    {
        $pad = str_repeat(self::INDENT, $depth);

        if (\in_array($name, self::KEYED_HASH, true) || array_is_list($value)) {
            $lines = [];
            foreach ($value as $key => $member) {
                $odinKey = '[' . (\is_int($key) ? (string) ($key + 1) : '"' . $key . '"') . ']';
                $lines[] = str_repeat(self::INDENT, $depth + 1) . $odinKey . ' = ' . $this->memberValue($member, $depth + 1);
            }

            return "<\n" . implode("\n", $lines) . "\n" . $pad . '>';
        }

        // Attribute object: a (possibly type-marked) block of nested attribute = <...> lines.
        return $this->objectBlock($value, $depth);
    }

    /**
     * Render the value side of a keyed-hash member, which may be a scalar (e.g. an invariant
     * assertion string) or a nested object.
     */
    private function memberValue(mixed $member, int $depth): string
    {
        if (\is_array($member)) {
            return $this->objectBlock($member, $depth);
        }

        return '<' . $this->scalar($member) . '>';
    }

    /**
     * Render `(Type?) <\n attr = <...> \n>` for an attribute object, lifting any `_type` to a marker.
     *
     * @param array<array-key, mixed> $value
     */
    private function objectBlock(array $value, int $depth): string
    {
        $marker = '';
        if (isset($value['_type']) && \is_string($value['_type'])) {
            $marker = '(' . $value['_type'] . ') ';
        }
        $pad = str_repeat(self::INDENT, $depth);
        $lines = [];
        foreach ($this->ordered($value) as $key => $inner) {
            if ($key === '_type') {
                continue;
            }
            $lines[] = $this->attribute((string) $key, $inner, $depth + 1);
        }
        if ($lines === []) {
            return $marker . '<>';
        }

        return $marker . "<\n" . implode("\n", $lines) . "\n" . $pad . '>';
    }

    /**
     * Whether every element of a list is a primitive (no nested arrays/objects).
     *
     * @param array<array-key, mixed> $list
     */
    private function allScalar(array $list): bool
    {
        foreach ($list as $v) {
            if (\is_array($v)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Render a flat list of primitives as a comma-separated ODIN list. A single-element list
     * gets a `, ...` continuation marker (ODIN's way of forcing list — not scalar — semantics).
     *
     * @param array<array-key, mixed> $list
     */
    private function primitiveList(array $list): string
    {
        $items = array_map(fn(mixed $v): string => $this->scalar($v), array_values($list));
        if (\count($items) === 1) {
            return $items[0] . ', ...';
        }

        return implode(', ', $items);
    }

    /**
     * Render a P_BMM cardinality/Interval map (e.g. `{lower: 0, upper_unbounded: true}`)
     * as an ODIN range literal such as `|>=0|`, `|1..4|`, or `|<=10|`.
     *
     * @param array<string, mixed> $iv
     */
    private function interval(array $iv): string
    {
        $lower = $iv['lower'] ?? null;
        $upper = $iv['upper'] ?? null;
        $lowerUnbounded = (bool) ($iv['lower_unbounded'] ?? false);
        $upperUnbounded = (bool) ($iv['upper_unbounded'] ?? false);
        $lowerIncluded = (bool) ($iv['lower_included'] ?? true);
        $upperIncluded = (bool) ($iv['upper_included'] ?? true);

        $hasLower = !$lowerUnbounded && $lower !== null;
        $hasUpper = !$upperUnbounded && $upper !== null;

        if ($hasLower && $hasUpper) {
            return $lower === $upper ? (string) $lower : $lower . '..' . $upper;
        }
        if ($hasLower) {
            return ($lowerIncluded ? '>=' : '>') . $lower;
        }
        if ($hasUpper) {
            return ($upperIncluded ? '<=' : '<') . $upper;
        }

        return '0..*';
    }

    /**
     * Render a scalar as an ODIN literal: booleans as True/False, numbers bare, strings quoted.
     */
    private function scalar(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'True' : 'False';
        }
        if (\is_int($value)) {
            return (string) $value;
        }
        if (\is_float($value)) {
            $s = (string) $value;
            return str_contains($s, '.') || str_contains($s, 'e') || str_contains($s, 'E') ? $s : $s . '.0';
        }
        if ($value === null) {
            return '""';
        }

        $s = (string) $value;
        $s = str_replace(['\\', '"'], ['\\\\', '\\"'], $s);

        return '"' . $s . '"';
    }

    /**
     * Yield the entries of an attribute map in {@see self::KEY_ORDER}, with any unlisted keys after.
     *
     * @param array<array-key, mixed> $map
     * @return array<array-key, mixed>
     */
    private function ordered(array $map): array
    {
        if (array_is_list($map)) {
            return $map;
        }
        $ordered = [];
        foreach (self::KEY_ORDER as $key) {
            if (\array_key_exists($key, $map)) {
                $ordered[$key] = $map[$key];
            }
        }
        foreach ($map as $key => $value) {
            if (!\array_key_exists($key, $ordered)) {
                $ordered[$key] = $value;
            }
        }

        return $ordered;
    }
}
