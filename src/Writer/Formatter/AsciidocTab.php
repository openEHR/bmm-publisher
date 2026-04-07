<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer\Formatter;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationInteger;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationString;
use Cadasto\OpenEHR\BMM\Model\BmmInterface;

readonly class AsciidocTab
{
    public function __construct(private bool $legacyFormat = false)
    {
    }

    public function format(AbstractBmmClass $class, string $classFilename): string
    {
        $className = $class->getName();
        $classType = match (get_class($class)) {
            BmmInterface::class => 'Interface',
            BmmEnumerationString::class, BmmEnumerationInteger::class => 'Enumeration',
            default => 'Class',
        };
        $location = $this->legacyFormat ? '../' : 'ROOT:partial$';

        return <<<ASCIIDOC
=== {$className} $classType

[tabs]
====
Definition::
+
include::{$location}definitions/{$classFilename}[]

Effective::
+
include::{$location}effective/{$classFilename}[]

BMM::
+
include::{$location}BMMs/{$classFilename}[]

UML::
+
include::{$location}plantUML/classes/{$classFilename}[]

====
ASCIIDOC;
    }
}
