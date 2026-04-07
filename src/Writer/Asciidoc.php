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
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocPlantUml;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocTab;
use RuntimeException;

class Asciidoc
{
    private AsciidocTab $tab;
    private AsciidocDefinition $definition;
    private AsciidocEffective $effective;
    private AsciidocBmmJson $bmmJson;
    private AsciidocPlantUml $plantUml;

    public function __construct(
        private readonly BmmSchemaCollection $schemas,
        private readonly bool $legacyFormat = false,
    ) {
        $this->tab = new AsciidocTab($this->legacyFormat);
        $this->definition = new AsciidocDefinition($schemas, $this->legacyFormat);
        $this->effective = new AsciidocEffective($schemas, $this->legacyFormat);
        $this->bmmJson = new AsciidocBmmJson();
        $this->plantUml = new AsciidocPlantUml($schemas);
    }

    public static function outputDir(): string
    {
        return OutputDir::path() . DIRECTORY_SEPARATOR . 'Adoc' . DIRECTORY_SEPARATOR;
    }

    public function __invoke(): void
    {
        Filesystem::assureDir(self::outputDir());
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
        $definitionsDir = self::outputDir() . $schema->getSchemaId() . '/definitions/';
        Filesystem::assureDir($definitionsDir);
        $effectiveDir = self::outputDir() . $schema->getSchemaId() . '/effective/';
        Filesystem::assureDir($effectiveDir);
        $tabsDir = self::outputDir() . $schema->getSchemaId() . '/classes/';
        Filesystem::assureDir($tabsDir);
        $bmmJsonDir = self::outputDir() . $schema->getSchemaId() . '/BMMs/';
        Filesystem::assureDir($bmmJsonDir);
        $plantUmlClassesDir = self::outputDir() . $schema->getSchemaId() . '/plantUML/classes/';
        Filesystem::assureDir($plantUmlClassesDir);
        $plantUmlPackagesDir = self::outputDir() . $schema->getSchemaId() . '/plantUML/packages/';
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
            $logger->notice('Writing {file} class ...', ['file' => $filename]);
            Filesystem::writeFile($definitionsDir . $filename, $this->definition->format($class, $prefix, $schema), $logger);
            Filesystem::writeFile($effectiveDir . $filename, $this->effective->format($class, $prefix, $schema), $logger);
            Filesystem::writeFile($tabsDir . $filename, $this->tab->format($class, $filename), $logger);
            Filesystem::writeFile($bmmJsonDir . $filename, $this->bmmJson->format($class), $logger);
            Filesystem::writeFile($plantUmlClassesDir . $filename, $this->plantUml->format($class, $prefix, $schema), $logger);
        }
        $prefix = 'org.openehr.' . strtolower($schema->schemaName) . '.';
        $namePrefix = $prefix . str_replace($prefix, '', $namePrefix);
        if ($this->legacyFormat) {
            $packageName = rtrim($namePrefix . str_replace($namePrefix, '', $package->name), '.');
        } else {
            $packageName = strtoupper($schema->schemaName) . '-' . $pkg . rtrim(str_replace($namePrefix, '', $package->name), '.');
        }
        $logger->notice('Writing {package} package ...', ['package' => $packageName]);
        Filesystem::writeFile($plantUmlPackagesDir . $packageName . '.adoc', $this->plantUml->format($package, $packageName, $schema), $logger);
    }
}
