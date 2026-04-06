<?php

/** @noinspection DuplicatedCode */

namespace OpenEHR\BmmPublisher\Writer\Formatter;

use Cadasto\OpenEHR\BMM\Helper\Collection;
use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\AbstractBmmProperty;
use Cadasto\OpenEHR\BMM\Model\BmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmConstant;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationInteger;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationString;
use Cadasto\OpenEHR\BMM\Model\BmmFunction;
use Cadasto\OpenEHR\BMM\Model\BmmInterface;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;

readonly class AsciidocEffective extends AsciidocDefinition
{
    protected function formatEnum(BmmEnumerationString|BmmEnumerationInteger $enum, string $prefix, BmmSchema $schema): string
    {
        $rows = [];
        $rows[] = '[cols="2,3", frame=none, grid=rows]';
        $rows[] = '|===';
        $rows[] = '2+^h|*' . $enum->name . '*';

        // Description
        if (!empty($enum->documentation)) {
            $rows[] = '';
            $rows[] = '2+a|' . $this->formatText($enum->documentation);
        }

        $ancestors = [];
        $this->aggregate('ancestors', $enum, $ancestors, $schema);
        $constants = [];
        $this->aggregate('itemNames', $enum, $constants, $schema);
        $functions = [];
        $this->aggregate('functions', $enum, $functions, $schema);

        // Ancestors
        if ($ancestors) {
            $rows[] = '';
            $rows[] = '2+|Inherits: ' . implode(', ', array_map(fn($ancestorName): string => $this->formatType($ancestorName, $prefix, $schema), $ancestors));
        }

        // Constants
        if ($constants) {
            $rows[] = '';
            $rows[] = '2+h|*Constants*';
            /** @var string $name */
            foreach ($constants as $i => [$name, $ancestorName]) {
                $rows[] = '';
                if (in_array('Integer', $enum->ancestors)) {
                    $signature = '*' . $name . '*: `' . $this->formatType('Integer', $prefix, $schema) . '{nbsp}={nbsp}' . ($enum->itemValues[$i] ?? '') . '`';
                } else {
                    $signature = $name;
                }
                $rows[] = '|' . $signature;
                $rows[] = 'a|' . $this->formatText($enum->itemDocumentations[$i] ?? '');
            }
        }

        // Functions
        if ($functions) {
            $rows[] = '';
            $rows[] = '2+h|*Functions*';
            /** @var BmmFunction $function */
            foreach ($functions as [$function, $ancestorName]) {
                $rows[] = '';
                [$card, $signature, $parameterDocs] = $this->formatFunctionSignature($function, $prefix, $schema);
                $ancestor = $this->formatAncestorName($ancestorName, $prefix, $enum, $schema);
                $rows[] = '|' . $ancestor . $signature . ' [' . $card . ']';
                $rows[] = 'a|' . $this->formatText($function->documentation ?? '');
            }
        }

        $rows[] = '|===';
        return implode("\n", $rows) . "\n";
    }

    protected function formatClass(BmmClass $class, string $prefix, BmmSchema $schema): string
    {
        $rows = [];
        $rows[] = '[cols="2,3", frame=none, grid=rows]';
        $rows[] = '|===';
        $className = $class->name;
        if ($class->genericParameterDefs->count() > 0) {
            $className = $className . '<' . implode(',', array_keys($class->genericParameterDefs->getArrayCopy())) . '>';
        }
        if ($class->isAbstract) {
            $className = '__' . $className . ' (abstract)__';
        }
        $rows[] = '2+^h|*' . $className . '*';

        // Description
        if (!empty($class->documentation)) {
            $rows[] = '';
            $rows[] = '2+a|' . $this->formatText($class->documentation);
        }

        $ancestors = [];
        $this->aggregate('ancestors', $class, $ancestors, $schema);
        $constants = [];
        $this->aggregate('constants', $class, $constants, $schema);
        $properties = [];
        $this->aggregate('properties', $class, $properties, $schema);
        $functions = [];
        $this->aggregate('functions', $class, $functions, $schema);
        $invariants = [];
        $this->aggregate('invariants', $class, $invariants, $schema);

        // Ancestors
        if ($ancestors) {
            $rows[] = '';
            $rows[] = '2+|Inherits: ' . implode(', ', array_map(fn($ancestorName): string => $this->formatType($ancestorName, $prefix, $schema), $ancestors));
        }

        // Constants
        if ($constants) {
            $rows[] = '';
            $rows[] = '2+h|*Constants*';
            /** @var BmmConstant $constant */
            foreach ($constants as [$constant, $ancestorName]) {
                [$card, $signature] = $this->formatConstantSignature($constant, $prefix, $schema);
                $ancestor = $this->formatAncestorName($ancestorName, $prefix, $class, $schema);
                $rows[] = '';
                $rows[] = '|' . $ancestor . $signature . ' [' . $card . ']';
                $doc = property_exists($constant, 'documentation') ? $this->formatText($constant->documentation ?? '') : '';
                $rows[] = 'a|' . $doc;
            }
        }

        // Attributes
        if ($properties) {
            $rows[] = '';
            $rows[] = '2+h|*Attributes*';
            /** @var AbstractBmmProperty $property */
            foreach ($properties as [$property, $ancestorName]) {
                [$card, $signature, $default] = $this->formatPropertySignature($property, $prefix, $schema);
                $ancestor = $this->formatAncestorName($ancestorName, $prefix, $class, $schema);
                $rows[] = '';
                $rows[] = '|' . $ancestor . $signature . ' [' . $card . ']' . $default;
                $doc = property_exists($property, 'documentation') ? $this->formatText($property->documentation ?? '') : '';
                $rows[] = 'a|' . $doc;
            }
        }

        // Functions
        if ($functions) {
            $rows[] = '';
            $rows[] = '2+h|*Functions*';
            /** @var BmmFunction $function */
            foreach ($functions as [$function, $ancestorName]) {
                $rows[] = '';
                [$card, $signature, $parameterDocs] = $this->formatFunctionSignature($function, $prefix, $schema);
                $abstract = '';
                if ($function->isAbstract) {
                    $abstract = '_(abstract)_ ';
                }
                $ancestor = $this->formatAncestorName($ancestorName, $prefix, $class, $schema);
                $rows[] = '|' . $abstract . $ancestor . $signature . ' [' . $card . ']';
                $rows[] = 'a|' . $this->formatText($function->documentation ?? '');
                if ($parameterDocs) {
                    $rows[] = '';
                    $rows[] = '.Parameters +';
                    $rows[] = '[horizontal]';
                    foreach ($parameterDocs as $parameterName => $doc) {
                        $rows[] = '`_' . $parameterName . '_`:: ' . $this->formatText($doc);
                    }
                }
            }
        }

        // extra line if no attributes or functions are missing
        if (!$constants && !$properties && !$functions) {
            $rows[] = '';
        }

        // Invariants
        if ($invariants) {
            $rows[] = '';
            $rows[] = '2+h|*Invariants*';
            foreach ($invariants as $name => [$expr, $ancestorName]) {
                $rows[] = '';
                $ancestor = $this->formatAncestorName($ancestorName, $prefix, $class, $schema);
                $rows[] = '2+a|' . $ancestor . '__' . $name . '__: `' . $this->formatText($expr) . '`';
            }
            $rows[] = '';
        }

        $rows[] = '|===';
        return implode("\n", $rows) . "\n";
    }

    protected function formatInterface(BmmInterface $class, string $prefix, BmmSchema $schema): string
    {
        $rows = [];
        $rows[] = '[cols="2,3", frame=none, grid=rows]';
        $rows[] = '|===';
        $rows[] = '2+^h|*' . $class->name . '*';

        // Description
        if (!empty($class->documentation)) {
            $rows[] = '';
            $rows[] = '2+a|' . $this->formatText($class->documentation);
        }

        $functions = [];
        $this->aggregate('functions', $class, $functions, $schema);

        // Functions
        if ($functions) {
            $rows[] = '';
            $rows[] = '2+h|*Functions*';
            /** @var BmmFunction $function */
            foreach ($functions as [$function, $ancestorName]) {
                $rows[] = '';
                [$card, $signature, $parameterDocs] = $this->formatFunctionSignature($function, $prefix, $schema);
                $ancestor = $this->formatAncestorName($ancestorName, $prefix, $class, $schema);
                $rows[] = '|' . $ancestor . $signature . ' [' . $card . ']';
                $rows[] = 'a|' . $this->formatText($function->documentation ?? '');
            }
        }

        $rows[] = '|===';
        return implode("\n", $rows) . "\n";
    }


    /**
     * @param array<mixed> $data
     */
    public function aggregate(string $feature, AbstractBmmClass $class, array &$data, BmmSchema $schema): void
    {
        if (property_exists($class, 'ancestors')) {
            foreach ($class->ancestors as $ancestorName) {
                $ancestor = $this->resolveClass($schema, $ancestorName);
                if ($ancestor) {
                    $this->aggregate($feature, $ancestor, $data, $schema);
                }
            }
        }
        if (property_exists($class, $feature)) {
            if ($feature === 'ancestors') {
                $data = array_merge($data, (array)$class->$feature);
                return;
            }
            if ($class->$feature instanceof Collection) {
                foreach ($class->$feature as $item) {
                    $data[$item->getName()] = [$item, $class->getName()];
                }
            } elseif (is_array($class->$feature)) {
                foreach ($class->$feature as $key => $value) {
                    $data[$key] = [$value, $class->getName()];
                }
            }
        }
    }

    public function formatAncestorName(string $ancestorName, string $prefix, AbstractBmmClass $currentClass, BmmSchema $schema): string
    {
        return $currentClass->getName() !== $ancestorName ? ($this->formatType($ancestorName, $prefix, $schema) . '.') : '';
    }
}
