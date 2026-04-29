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

    /** @var array<string, true> */
    private array $cleanedSchemas = [];

    public function __construct(
        private readonly BmmSchemaCollection $schemas,
        private readonly bool $legacyFormat = false,
    ) {
        $this->tab = new AsciidocTab($this->legacyFormat);
        $this->definition = new AsciidocDefinition($schemas, $this->legacyFormat);
        $this->effective = new AsciidocEffective($schemas, $this->legacyFormat);
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
        $this->cleanedSchemas = [];
        $this->schemas->forEachPackage($this->writePackage(...));
    }

    private function writePackage(BmmPackage $package, BmmSchema $schema, string $namePrefix): void
    {
        $logger = $this->schemas->logger;
        if (!\count($package->classes)) {
            $logger->warning('Empty package {package}.', ['package' => $package->name]);
            return;
        }
        $prefix = 'org.openehr.' . strtolower($schema->schemaName) . '.';
        $prefix .= explode('.', str_replace($prefix, '', $namePrefix . $package->name))[0];
        if (!$this->legacyFormat && $schema->schemaName === 'am') {
            $parts = explode('.', $prefix);
            $pkg = end($parts) . '.';
        } else {
            $pkg = '';
        }
        $schemaDir = self::outputDir() . $schema->getSchemaId();
        $this->cleanPlantUmlDirOnce($schema->getSchemaId(), $schemaDir);

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
            if ($this->legacyFormat) {
                $filename = $prefix . '.' . strtolower($className) . '.adoc';
            } else {
                $filename = $pkg . strtolower($className) . '.adoc';
            }
            $pumlFilename = preg_replace('/\.adoc$/', '.puml', $filename) ?? $filename;
            $logger->notice('Writing {file} class ...', ['file' => $filename]);
            Filesystem::writeFile($definitionsDir . $filename, $this->definition->format($class, $prefix, $schema), $logger);
            Filesystem::writeFile($effectiveDir . $filename, $this->effective->format($class, $prefix, $schema), $logger);
            Filesystem::writeFile($tabsDir . $filename, $this->tab->format($class, $filename), $logger);
            Filesystem::writeFile($bmmJsonDir . $filename, $this->bmmJson->format($class), $logger);
            Filesystem::writeFile($plantUmlClassesDir . $pumlFilename, $this->plantUml->format($class, $prefix, $schema), $logger);
        }
        $prefix = 'org.openehr.' . strtolower($schema->schemaName) . '.';
        $namePrefix = $prefix . str_replace($prefix, '', $namePrefix);
        if ($this->legacyFormat) {
            $packageName = rtrim($namePrefix . str_replace($namePrefix, '', $package->name), '.');
        } else {
            $packageName = strtoupper($schema->schemaName) . '-' . $pkg . rtrim(str_replace($namePrefix, '', $package->name), '.');
        }
        $logger->notice('Writing {package} package ...', ['package' => $packageName]);
        Filesystem::writeFile($plantUmlPackagesDir . $packageName . '.puml', $this->plantUml->format($package, $packageName, $schema), $logger);
    }

    /**
     * Recursively delete output/Adoc/<schema>/plantUML/ on first encounter of <schema>
     * within this writer invocation, so that orphaned files (e.g. classes renamed
     * across BMM versions) cannot linger in committed output.
     */
    private function cleanPlantUmlDirOnce(string $schemaId, string $schemaDir): void
    {
        if (isset($this->cleanedSchemas[$schemaId])) {
            return;
        }
        $this->cleanedSchemas[$schemaId] = true;
        $plantUmlDir = $schemaDir . '/plantUML';
        if (!is_dir($plantUmlDir)) {
            return;
        }
        $this->rrmdir($plantUmlDir);
    }

    private function rrmdir(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
