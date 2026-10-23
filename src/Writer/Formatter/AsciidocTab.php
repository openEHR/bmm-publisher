<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer\Formatter;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationInteger;
use Cadasto\OpenEHR\BMM\Model\BmmEnumerationString;
use Cadasto\OpenEHR\BMM\Model\BmmInterface;

readonly class AsciidocTab
{
    /**
     * @param bool $withExample Add an `Example` tab that includes `ROOT:partial$examples/<classFilename>`.
     */
    public function format(AbstractBmmClass $class, string $classFilename, bool $withExample = false): string
    {
        $className = $class->getName();
        $classType = match (get_class($class)) {
            BmmInterface::class => 'Interface',
            BmmEnumerationString::class, BmmEnumerationInteger::class => 'Enumeration',
            default => 'Class',
        };
        $location = 'ROOT:partial$';
        $svgFilename = str_ends_with($classFilename, '.adoc')
            ? substr($classFilename, 0, -\strlen('.adoc')) . '.svg'
            : $classFilename . '.svg';
        // Qualify the image target with ROOT: so Antora resolves it in the ROOT
        // module's images/ tree even when the tabs partial is included from a
        // page in another module (foundation_types, base_types, …).
        $locationSvg = 'ROOT:';
        $exampleTab = $withExample
            // Open block: an example partial may hold several blocks (prose + source), and a list
            // continuation attaches only one block to the tab.
            ? "Example::\n+\n--\ninclude::{$location}examples/{$classFilename}[]\n--\n\n"
            : '';

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

{$exampleTab}BMM::
+
include::{$location}BMMs/{$classFilename}[]

UML::
+
image::{$locationSvg}uml/classes/{$svgFilename}[]

====
ASCIIDOC;
    }
}
