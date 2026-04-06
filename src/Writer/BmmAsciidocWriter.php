<?php

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Helper\Collection;
use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocBmmJson;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocDefinition;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocEffective;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocPlantUml;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocTab;
use RuntimeException;

class BmmAsciidocWriter extends AbstractWriter
{
    public const string DIR = __WRITER_DIR__ . DIRECTORY_SEPARATOR . 'Adoc' . DIRECTORY_SEPARATOR;

    private AsciidocTab $tab;
    private AsciidocDefinition $definition;
    private AsciidocEffective $effective;
    private AsciidocBmmJson $bmmJson;
    private AsciidocPlantUml $plantUml;

    public function __construct(Collection $schemas, private readonly bool $legacyFormat = false)
    {
        parent::__construct($schemas);
        $this->tab = new AsciidocTab($this->legacyFormat);
        $this->definition = new AsciidocDefinition($this->legacyFormat);
        $this->effective = new AsciidocEffective($this->legacyFormat);
        $this->bmmJson = new AsciidocBmmJson();
        $this->plantUml = new AsciidocPlantUml();
    }

    public function write(): void
    {
        $this->assureOutputDir();
        /** @var BmmSchema $schema */
        foreach ($this->schemas as $schema) {
            // Build prefix e.g. org.openehr.rm
            /** @var BmmPackage $package */
            foreach ($schema->packages as $package) {
                $this->writePackage($package, $schema, '');
                /** @var BmmPackage $subPackage */
                foreach ($package->packages as $subPackage) {
                    $this->writePackage($subPackage, $schema, $package->name . '.');
                    // one level deeper for sub-packages (consistent with other writers)
                    /** @var BmmPackage $subSubPackage */
                    foreach ($subPackage->packages as $subSubPackage) {
                        $this->writePackage($subSubPackage, $schema, $package->name . '.' . $subPackage->name . '.');
                    }
                }
            }
        }
    }

    private function writePackage(BmmPackage $package, BmmSchema $schema, string $namePrefix): void
    {
        if (!count($package->classes)) {
            self::log('WARN: Empty package %s.', $package->name);
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
        $definitionsDir = self::DIR . $schema->getSchemaId() . '/definitions/';
        $this->assureOutputDir($definitionsDir);
        $effectiveDir = self::DIR . $schema->getSchemaId() . '/effective/';
        $this->assureOutputDir($effectiveDir);
        $tabsDir = self::DIR . $schema->getSchemaId() . '/classes/';
        $this->assureOutputDir($tabsDir);
        $bmmJsonDir = self::DIR . $schema->getSchemaId() . '/BMMs/';
        $this->assureOutputDir($bmmJsonDir);
        $plantUmlClassesDir = self::DIR . $schema->getSchemaId() . '/plantUML/classes/';
        $this->assureOutputDir($plantUmlClassesDir);
        $plantUmlPackagesDir = self::DIR . $schema->getSchemaId() . '/plantUML/packages/';
        $this->assureOutputDir($plantUmlPackagesDir);
        foreach ($package->classes as $className) {
            /** @var AbstractBmmClass $class */
            $class = $schema->classDefinitions->get($className) ?? $schema->primitiveTypes->get($className);
            if (!$class) {
                throw new RuntimeException(sprintf('WARN: Class %s not found in schema', $className));
            }
            if ($this->legacyFormat) {
                $filename = $prefix . '.' . strtolower($className) . '.adoc';
            } else {
                $filename = $pkg . strtolower($className) . '.adoc';
            }
            self::log('Writing %s class ...', $filename);
            $this->writeFile($definitionsDir . $filename, $this->definition->format($class, $prefix, $schema));
            $this->writeFile($effectiveDir . $filename, $this->effective->format($class, $prefix, $schema));
            $this->writeFile($tabsDir . $filename, $this->tab->format($class, $filename));
            $this->writeFile($bmmJsonDir . $filename, $this->bmmJson->format($class));
            $this->writeFile($plantUmlClassesDir . $filename, $this->plantUml->format($class, $prefix, $schema));
        }
        $prefix = 'org.openehr.' . strtolower($schema->schemaName) . '.';
        $namePrefix = $prefix . str_replace($prefix, '', $namePrefix);
        if ($this->legacyFormat) {
            $packageName = rtrim($namePrefix . str_replace($namePrefix, '', $package->name), '.');
        } else {
            $packageName = strtoupper($schema->schemaName) . '-' . $pkg . rtrim(str_replace($namePrefix, '', $package->name), '.');
        }
        self::log('Writing %s package ...', $packageName);
        $this->writeFile($plantUmlPackagesDir . $packageName . '.adoc', $this->plantUml->format($package, $packageName, $schema));
    }
}
