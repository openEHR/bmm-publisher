<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\Filesystem;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocBmmJson;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocDefinition;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocEffective;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocTab;
use OpenEHR\BmmPublisher\Writer\Formatter\PlantUml as PlantUmlFormatter;
use RuntimeException;

class Asciidoc
{
    private AsciidocTab $tab;
    private AsciidocDefinition $definition;
    private AsciidocEffective $effective;
    private AsciidocBmmJson $bmmJson;
    private PlantUmlFormatter $plantUml;

    /** @var array<string, true> Guards prune-once per (schema id, filename namespace). */
    private array $cleanedNamespaces = [];

    /** @var array<string, array<string, string>> Package diagram name by package path, per schema id. */
    private array $packageDiagramNames = [];

    /**
     * @param array<int, string> $exportSchemaIds Schema ids to export; an empty list exports every
     *        loaded schema. Schemas loaded for cross-reference resolution only (dependencies) are
     *        omitted from this list so their artefacts are not written.
     */
    public function __construct(
        private readonly BmmSchemaCollection $schemas,
        private readonly array $exportSchemaIds = [],
    ) {
        $this->tab = new AsciidocTab();
        $this->definition = new AsciidocDefinition($schemas);
        $this->effective = new AsciidocEffective($schemas);
        $this->bmmJson = new AsciidocBmmJson();
        $this->plantUml = new PlantUmlFormatter($schemas);
    }

    public static function outputDir(): string
    {
        return OutputDir::path() . DIRECTORY_SEPARATOR . 'Adoc' . DIRECTORY_SEPARATOR;
    }

    public function __invoke(): void
    {
        Filesystem::assureDir(self::outputDir());
        $this->cleanedNamespaces = [];
        $this->packageDiagramNames = [];
        $this->schemas->forEachPackage($this->writePackage(...));
    }

    private function writePackage(BmmPackage $package, BmmSchema $schema, string $namePrefix): void
    {
        // Dependency schemas stay in the collection for cross-reference resolution but are not exported.
        if ($this->exportSchemaIds !== [] && !\in_array($schema->getSchemaId(), $this->exportSchemaIds, true)) {
            return;
        }

        $logger = $this->schemas->logger;
        if (!\count($package->classes)) {
            $logger->warning('Empty package {package}.', ['package' => $package->name]);
            return;
        }
        $prefix = LegacyClassNaming::packagePrefix($schema, $namePrefix, $package);
        $pkg = LegacyClassNaming::filenamePrefix($schema, $prefix);
        $schemaDir = self::outputDir() . $schema->getSchemaId();
        $this->pruneNamespaceOnce($schema->getSchemaId(), $pkg, $schemaDir);

        $definitionsDir = $schemaDir . '/definitions/';
        Filesystem::assureDir($definitionsDir);
        $effectiveDir = $schemaDir . '/effective/';
        Filesystem::assureDir($effectiveDir);
        $tabsDir = $schemaDir . '/classes/';
        Filesystem::assureDir($tabsDir);
        $bmmJsonDir = $schemaDir . '/BMMs/';
        Filesystem::assureDir($bmmJsonDir);
        $plantUmlClassesDir = $schemaDir . '/plantUML/classes/';
        Filesystem::assureDir($plantUmlClassesDir);
        $plantUmlPackagesDir = $schemaDir . '/plantUML/packages/';
        Filesystem::assureDir($plantUmlPackagesDir);
        foreach ($package->classes as $className) {
            /** @var AbstractBmmClass $class */
            $class = $schema->classDefinitions->get($className) ?? $schema->primitiveTypes->get($className);
            if (!$class) {
                throw new RuntimeException(\sprintf('WARN: Class %s not found in schema', $className));
            }
            $filename = $pkg . strtolower($className) . '.adoc';
            $pumlFilename = preg_replace('/\.adoc$/', '.puml', $filename) ?? $filename;
            $logger->notice('Writing {file} class ...', ['file' => $filename]);
            Filesystem::writeFile($definitionsDir . $filename, $this->definition->format($class, $prefix, $schema), $logger);
            Filesystem::writeFile($effectiveDir . $filename, $this->effective->format($class, $prefix, $schema), $logger);
            Filesystem::writeFile($tabsDir . $filename, $this->tab->format($class, $filename), $logger);
            Filesystem::writeFile($bmmJsonDir . $filename, $this->bmmJson->format($class), $logger);
            Filesystem::writeFile($plantUmlClassesDir . $pumlFilename, $this->plantUml->format($class, $prefix, $schema), $logger);
        }
        $packageName = $this->packageDiagramName($schema, $namePrefix, $package);
        $logger->notice('Writing {package} package ...', ['package' => $packageName]);
        Filesystem::writeFile($plantUmlPackagesDir . $packageName . '.puml', $this->plantUml->format($package, $packageName, $schema), $logger);
    }

    /**
     * Name of a package diagram, e.g. `RM-composition` or `AM-aom2.archetype`.
     */
    private function packageDiagramName(BmmSchema $schema, string $namePrefix, BmmPackage $package): string
    {
        $schemaId = $schema->getSchemaId();
        $this->packageDiagramNames[$schemaId] ??= $this->resolvePackageDiagramNames($schema);

        return $this->packageDiagramNames[$schemaId][LegacyClassNaming::packagePath($schema, $namePrefix, $package)];
    }

    /**
     * Diagram name per package path, for every package of the schema that has classes.
     *
     * A diagram is named after the package's last name segment, which is how the spec pages
     * refer to it. Packages in different branches can share that segment (AM `aom2.archetype`
     * and `aom2.persistence.archetype`), and the later one would overwrite the earlier one's
     * files. Of such a group the package nearest the component root keeps the short name, with
     * ties going to the first in traversal order; the others are named by their full path
     * below the component (`AM-aom2.persistence.archetype`).
     *
     * @return array<string, string>
     */
    private function resolvePackageDiagramNames(BmmSchema $schema): array
    {
        $component = strtoupper($schema->schemaName) . '-';
        $prefix = 'org.openehr.' . strtolower($schema->schemaName) . '.';

        $pathsByShortName = [];
        $this->schemas->forEachPackage(
            static function (BmmPackage $package, BmmSchema $other, string $namePrefix) use ($schema, $component, $prefix, &$pathsByShortName): void {
                if ($other !== $schema || $package->classes === []) {
                    return;
                }
                $filenamePrefix = LegacyClassNaming::filenamePrefix($schema, LegacyClassNaming::packagePrefix($schema, $namePrefix, $package));
                $parentPrefix = $prefix . str_replace($prefix, '', $namePrefix);
                $shortName = $component . $filenamePrefix . rtrim(str_replace($parentPrefix, '', $package->name), '.');
                $pathsByShortName[$shortName][] = LegacyClassNaming::packagePath($schema, $namePrefix, $package);
            },
        );

        $names = [];
        foreach ($pathsByShortName as $shortName => $paths) {
            usort($paths, static fn (string $a, string $b): int => substr_count($a, '.') <=> substr_count($b, '.'));
            foreach ($paths as $i => $path) {
                $names[$path] = $i === 0 ? $shortName : $component . $path;
                if ($i > 0) {
                    $this->schemas->logger->notice('Package diagram name {short} is taken in {schema}; using {name}.', [
                        'short' => $shortName,
                        'schema' => $schema->getSchemaId(),
                        'name' => $names[$path],
                    ]);
                }
            }
        }

        return $names;
    }

    /**
     * Delete the generated diagrams under output/Adoc/<schema>/{plantUML,images}/
     * that belong to <namespace>, on first encounter of (schema id, namespace)
     * within this writer invocation. This drops orphans (e.g. classes renamed or
     * removed across BMM versions) before fresh artefacts are written, without
     * disturbing a sibling schema that shares the same schema id and output
     * directory but uses a different filename namespace — for example the legacy
     * LANG `bmm`/`beom` tables (namespace '') and the BMM v3 `bmm3.` tables, both
     * of which resolve to schema id `openehr_lang_1.1.0`.
     */
    private function pruneNamespaceOnce(string $schemaId, string $namespace, string $schemaDir): void
    {
        $key = $schemaId . "\0" . $namespace;
        if (isset($this->cleanedNamespaces[$key])) {
            return;
        }
        $this->cleanedNamespaces[$key] = true;
        foreach (['plantUML', 'images'] as $sub) {
            $dir = $schemaDir . '/' . $sub;
            if (is_dir($dir)) {
                $this->pruneNamespace($dir, $namespace);
            }
        }
    }

    /**
     * Recursively delete files under $dir owned by $namespace, then remove any
     * directory left empty. Files of other namespaces are left untouched.
     */
    private function pruneNamespace(string $dir, string $namespace): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->pruneNamespace($path, $namespace);
            } elseif (LegacyClassNaming::belongsToNamespace($item, $namespace)) {
                @unlink($path);
            }
        }
        $remaining = scandir($dir);
        if ($remaining !== false && array_diff($remaining, ['.', '..']) === []) {
            @rmdir($dir);
        }
    }
}
