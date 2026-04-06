<?php

namespace OpenEHR\BmmPublisher\Writer\Formatter;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\AbstractBmmProperty;
use Cadasto\OpenEHR\BMM\Model\BmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmContainerFunctionParameter;
use Cadasto\OpenEHR\BMM\Model\BmmContainerProperty;
use Cadasto\OpenEHR\BMM\Model\BmmContainerType;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationInteger;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationString;
use Cadasto\OpenEHR\BMM\Model\BmmFunction;
use Cadasto\OpenEHR\BMM\Model\BmmGenericFunctionParameter;
use Cadasto\OpenEHR\BMM\Model\BmmGenericProperty;
use Cadasto\OpenEHR\BMM\Model\BmmGenericType;
use Cadasto\OpenEHR\BMM\Model\BmmInterface;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use Cadasto\OpenEHR\BMM\Model\BmmSimpleType;
use Cadasto\OpenEHR\BMM\Model\BmmSingleFunctionParameter;
use Cadasto\OpenEHR\BMM\Model\BmmSingleFunctionParameterOpen;
use Cadasto\OpenEHR\BMM\Model\BmmSingleProperty;
use Cadasto\OpenEHR\BMM\Model\BmmSinglePropertyOpen;

readonly class PlantUml
{
    private function resolveClass(BmmSchema $schema, string $name): ?AbstractBmmClass
    {
        $item = $schema->classDefinitions->get($name) ?? $schema->primitiveTypes->get($name);
        return $item instanceof AbstractBmmClass ? $item : null;
    }

    public function format(AbstractBmmClass|BmmPackage $bmmItem, string $prefix, BmmSchema $schema): string
    {
        $content = 'Unsupported *' . $bmmItem->getName() . '*, context *format-plantUML*';
        if ($bmmItem instanceof BmmInterface) {
            $content = $this->formatInterface($bmmItem);
        }
        if ($bmmItem instanceof BmmClass) {
            $content = $this->formatClass($bmmItem) . $this->formatClassAncestors($bmmItem, $schema);
        }
        if ($bmmItem instanceof BmmEnumerationString || $bmmItem instanceof BmmEnumerationInteger) {
            $content = $this->formatEnum($bmmItem);
        }
        if ($bmmItem instanceof BmmPackage && count($bmmItem->classes)) {
            $classesOutput = [];
            $relationshipOutput = [];
            foreach ($bmmItem->classes as $className) {
                $class = $this->resolveClass($schema, $className);
                if ($class instanceof BmmClass) {
                    $classesOutput[] = $this->formatClass($class);
                    $relationshipOutput[] = $this->generateRelationships($class);
                }
                if ($class instanceof BmmInterface) {
                    $classesOutput[] = $this->formatInterface($class);
                }
                if ($class instanceof BmmEnumerationString || $class instanceof BmmEnumerationInteger) {
                    $classesOutput[] = $this->formatEnum($class);
                }
            }
            $content = implode('', $classesOutput) . implode('', $relationshipOutput);
        }

        return <<<EOD
@startuml

{$content}
@enduml
EOD;
    }

    /**
     * Generate PlantUML class definition
     *
     * @param BmmClass $class The BMM class to convert
     * @return string The PlantUML class definition
     */
    private function formatClass(BmmClass $class): string
    {
        $output = '';

        if ($class->isAbstract) {
            $output .= "abstract ";
        }
        $output .= "class " . $class->name . " ";
        if ($class->genericParameterDefs->count() > 0) {
            $genericParameterDefs = array_map(fn($item) => $item->getName(), $class->genericParameterDefs->getArrayCopy());
            $output .= '<' . implode(', ', $genericParameterDefs) . '> ';
        }
        $output .= "{\n";

        /** @var AbstractBmmProperty $property */
        foreach ($class->properties as $property) {
            $output .= "  " . $this->formatProperty($property) . "\n";
        }

        /** @var BmmFunction $function */
        foreach ($class->functions as $function) {
            $output .= "  " . $this->formatFunction($function) . "\n";
        }

        $output .= "}\n\n";

        return $output;
    }

    private function formatInterface(BmmInterface $interface): string
    {
        $output = "interface " . $interface->name . " {\n";
        /** @var BmmFunction $function */
        foreach ($interface->functions as $function) {
            $output .= "  " . $this->formatFunction($function) . "\n";
        }
        $output .= "}\n\n";
        return $output;
    }

    /**
     * Generate PlantUML enumeration definition
     *
     * @param BmmEnumerationString|BmmEnumerationInteger $enum The BMM enumeration to convert
     * @return string The PlantUML enumeration definition
     */
    private function formatEnum(BmmEnumerationString|BmmEnumerationInteger $enum): string
    {
        $output = "enum " . $enum->name . " {\n";
        foreach ($enum->itemNames as $i => $itemName) {
            $itemValue = isset($enum->itemValues[$i]) ? "= {$enum->itemValues[$i]} " : '';
            $output .= "  $itemName $itemValue\n";
        }
        $output .= "}\n\n";
        return $output;
    }

    /**
     * Format a BMM class property for PlantUML
     *
     * @param AbstractBmmProperty $property The property to format
     * @return string The formatted property
     */
    private function formatProperty(AbstractBmmProperty $property): string
    {
        $type = '';
        $minOccurs = !empty($property->isMandatory) ? '1' : '0';
        $maxOccurs = '1';
        if ($property instanceof BmmContainerProperty) {
            $type = $this->formatContainerParameterType($property->typeDef);
            $card = $property->cardinality;
            $maxOccurs = $card === null
                ? '*'
                : ($card->upperUnbounded ? '*' : (string) ($card->upper ?? '*'));
        } elseif ($property instanceof BmmGenericProperty) {
            $type = $this->formatGenericParameterType($property->typeDef);
        } elseif ($property instanceof BmmSingleProperty || $property instanceof BmmSinglePropertyOpen) {
            $type = $this->formatType($property->type);
        }

        return '+ ' . $property->getName() . ' : ' . $type . $this->formatCardinality($minOccurs, $maxOccurs);
    }

    /**
     * Formats a BMM function for PlantUML representation
     *
     * @param BmmFunction $function The BMM function to format
     * @return string The formatted function signature in PlantUML syntax
     */
    private function formatFunction(BmmFunction $function): string
    {
        $abstract = $function->isAbstract ? '{abstract} ' : '';
        $type = '';
        $minOccurs = empty($function->isNullable) ? '1' : '0';
        $maxOccurs = '1';
        if ($function->result instanceof BmmContainerType) {
            $type = $this->formatContainerParameterType($function->result);
            $maxOccurs = '*';
        } elseif ($function->result instanceof BmmGenericType) {
            $type = $this->formatGenericParameterType($function->result);
        } elseif ($function->result instanceof BmmSimpleType) {
            $type = $this->formatType($function->result->type);
        }
        $arguments = '';
        if ($function->parameters->count()) {
            $arguments = ' ' . implode(', ', array_map(function ($parameter) {
                    $minOccurs = empty($parameter->isNullable) ? '1' : '0';
                    $maxOccurs = '1';
                    $cardinality = $this->formatCardinality($minOccurs, $maxOccurs);
                if ($parameter instanceof BmmContainerFunctionParameter) {
                    return $parameter->name . ' : ' . $this->formatContainerParameterType($parameter->typeDef) . $cardinality;
                } elseif ($parameter instanceof BmmGenericFunctionParameter) {
                    return $parameter->name . ' : ' . $this->formatGenericParameterType($parameter->typeDef) . $cardinality;
                } elseif ($parameter instanceof BmmSingleFunctionParameter || $parameter instanceof BmmSingleFunctionParameterOpen) {
                    return $parameter->name . ' : ' . $this->formatType($parameter->type) . $cardinality;
                }
                    return '';
            }, $function->parameters->getArrayCopy())) . ' ';
        }

        return '+ ' . $abstract . $function->name . '(' . $arguments . ') : ' . $type . $this->formatCardinality($minOccurs, $maxOccurs);
    }

    /**
     * Formats a BMM container type for PlantUML representation
     *
     * @param BmmContainerType $type The BMM container type to format
     * @return string The formatted container type with generic parameters
     */
    private function formatContainerParameterType(BmmContainerType $type): string
    {
        if ($type->typeDef instanceof BmmGenericType) {
            return $this->formatType($type->containerType) . '<' . $this->formatGenericParameterType($type->typeDef) . '>';
        } elseif ($type->typeDef instanceof BmmContainerType) {
            return $this->formatType($type->containerType) . '<' . $this->formatContainerParameterType($type->typeDef) . '>';
        }
        return $this->formatType($type->containerType) . '<' . $this->formatType($type->type ?? 'Any') . '>';
    }

    /**
     * Formats a BMM generic type for PlantUML representation
     *
     * @param BmmGenericType $type The BMM generic type to format
     * @return string The formatted generic type with parameters
     */
    private function formatGenericParameterType(BmmGenericType $type): string
    {
        if (!empty($type->genericParameters)) {
            $genericParameters = implode(',', array_map(
                fn(string $t): string => $t,
                $type->genericParameters,
            ));
        } elseif (!empty($type->genericParameterDefs)) {
            $genericParameters = implode(',', array_map(function ($t) {
                if ($t instanceof BmmGenericType) {
                    return $this->formatGenericParameterType($t);
                } elseif ($t instanceof BmmSimpleType) {
                    return $this->formatType($t->type);
                }
                return '';
            }, $type->genericParameterDefs->getArrayCopy()));
        } else {
            $genericParameters = '';
        }
        return $this->formatType($type->rootType) . '<' . $genericParameters . '>';
    }

    /**
     * Determines if a class name should be hidden from the diagram
     *
     * @param string $className The class name to check
     * @return bool True if the class should be hidden, false otherwise
     */
    private function isHidden(string $className): bool
    {
        return in_array(strtolower($className), ['openehr_definitions', 'any']);
    }

    /**
     * @param string $minOccurs
     * @param string $maxOccurs
     * @return string
     */
    private function formatCardinality(string|int $minOccurs, string|int $maxOccurs): string
    {
        return match ($minOccurs . '..' . $maxOccurs) {
            '1..1' => ' [1]',
            '0..*' => ' [*]',
            default => ' [' . $minOccurs . '..' . $maxOccurs . ']'
        };
    }

    /**
     * Generates PlantUML representation for all ancestor classes and their inheritance relationships
     *
     * @param BmmClass $class The BMM class to generate ancestors for
     * @return string The PlantUML representation of ancestors and inheritance relationships
     */
    private function formatClassAncestors(BmmClass $class, BmmSchema $schema): string
    {
        $output = '';
        foreach ($class->ancestors as $ancestorName) {
            if ($this->isHidden($ancestorName)) {
                continue;
            }
            $ancestor = $this->resolveClass($schema, $ancestorName);
            if ($ancestor instanceof BmmClass) {
                $output .= $this->formatClass($ancestor);
                $output .= $this->formatClassAncestors($ancestor, $schema);
            }
            $output .= $ancestorName . " <|-- " . $class->name . "\n\n";
        }
        return (string) preg_replace('/\bclass ([^{#]+)\{/', "class $1 #whitesmoke;line:gray;line.dotted;text:gray {", $output);
    }

    /**
     * Generate PlantUML relationships for a class
     *
     * @param BmmClass $class The BMM class to generate relationships for
     * @return string The PlantUML relationships
     */
    private function generateRelationships(BmmClass $class): string
    {
        $output = '';

        // Inheritance
        if ($class->ancestors) {
            foreach ($class->ancestors as $ancestorName) {
                if ($this->isHidden($ancestorName)) {
                    continue;
                }
                $output .= $ancestorName . " <|-- " . $class->name . "\n";
            }
        }

//        // Associations (properties that reference other classes)
//        foreach ($class->properties as $property) {
//            if ($property instanceof BmmContainerProperty) {
//                $output .= $class->name . " o-- \"*\" " . $property->typeDef->type . " : " . $property->name . "\n";
//            } elseif ($property instanceof BmmGenericProperty) {
//                $output .= $class->name . " --> " . $property->typeDef->rootType . " : " . $property->name . "\n";
//            } elseif ($property instanceof BmmSingleProperty || $property instanceof BmmSinglePropertyOpen) {
//                $output .= $class->name . " --> " . $property->type . " : " . $property->name . "\n";
//            }
//        }

        return $output;
    }

    public function formatType(string $typeName): string
    {
        // notice: for UML with hyperlinks, this needs to be enabled, but now is disabled because antora+kroki become very slow
//        if (strlen($typeName) === 1 || in_array(strtolower($typeName), ['operation', 'void', 'null', 'false', 'true'])) {
//            return $typeName;
//        }
//        return '[[https://specifications.openehr.org/classes/' . $typeName . ' ' . $typeName . ']]';
        return $typeName;
    }
}
