<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;

/**
 * Shared naming rules for the legacy AsciiDoc layout (the pre-Antora
 * `docs/UML/classes/org.openehr.<schema>.<package>.<class>.adoc` convention).
 *
 * Centralised so the {@see Asciidoc} writer and the dedicated
 * {@see LegacyClassDefinitions} writer cannot drift apart.
 */
final class LegacyClassNaming
{
    /**
     * Fully-qualified package prefix for a class, e.g. `org.openehr.lang.beom`.
     *
     * This is the same prefix the formatters receive for cross-reference link
     * generation, so it is computed identically for both legacy and current layouts.
     */
    public static function packagePrefix(BmmSchema $schema, string $namePrefix, BmmPackage $package): string
    {
        $prefix = 'org.openehr.' . strtolower($schema->schemaName) . '.';
        return $prefix . explode('.', str_replace($prefix, '', $namePrefix . $package->name))[0];
    }

    /**
     * Legacy per-class filename, e.g. `org.openehr.lang.beom.assertion.adoc`.
     */
    public static function classFilename(string $prefix, string $className): string
    {
        return $prefix . '.' . strtolower($className) . '.adoc';
    }
}
