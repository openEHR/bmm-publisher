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
     * Every non-empty filename namespace prefix the publisher can emit (see
     * {@see self::filenamePrefix()}). Used by {@see self::belongsToNamespace()}
     * to tell apart artefacts of parallel model generations that share an
     * output directory. Keep in sync with the prefixes {@see self::filenamePrefix()}
     * produces.
     *
     * @var list<string>
     */
    public const NAMESPACE_PREFIXES = ['aom14.', 'aom2.', 'bmm3.'];

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
     * Short filename prefix that namespaces parallel model generations which
     * share a schema id (and therefore an output directory), e.g. `aom14.`,
     * `aom2.`, `bmm3.`.
     *
     * The whole AM schema is namespaced because AOM 1.4 and AOM 2 coexist within
     * it. For other schemas only the `bmm3` top-level package is namespaced, so
     * the BMM v3 class tables can live alongside the legacy LANG `bmm`/`beom`
     * tables — which resolve to the same schema id `openehr_lang_1.1.0` — without
     * colliding on names such as `bmm_class` or `bmm_model`. Returns '' when no
     * namespacing applies (the common case: a single model generation per schema).
     *
     * @param string $packagePrefix The value returned by {@see self::packagePrefix()},
     *        e.g. `org.openehr.am.aom2` or `org.openehr.lang.bmm3`.
     */
    public static function filenamePrefix(BmmSchema $schema, string $packagePrefix): string
    {
        $parts = explode('.', $packagePrefix);
        $topPackage = end($parts);
        return ($schema->schemaName === 'am' || $topPackage === 'bmm3')
            ? $topPackage . '.'
            : '';
    }

    /**
     * Whether a generated artefact filename belongs to the given namespace.
     *
     * Namespaced artefacts carry their prefix as a dotted token, either at the
     * start of a class/page filename (`bmm3.bmm_class.puml`) or after the
     * component segment of a package diagram (`LANG-bmm3.core.svg`). The empty
     * namespace owns everything carrying no known prefix — i.e. the legacy,
     * un-namespaced artefacts of a sibling schema sharing the directory.
     *
     * @param string $namespace Either '' or one of {@see self::NAMESPACE_PREFIXES}.
     */
    public static function belongsToNamespace(string $filename, string $namespace): bool
    {
        if ($namespace !== '') {
            return str_contains($filename, $namespace);
        }
        foreach (self::NAMESPACE_PREFIXES as $prefix) {
            if (str_contains($filename, $prefix)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Legacy per-class filename, e.g. `org.openehr.lang.beom.assertion.adoc`.
     */
    public static function classFilename(string $prefix, string $className): string
    {
        return $prefix . '.' . strtolower($className) . '.adoc';
    }
}
