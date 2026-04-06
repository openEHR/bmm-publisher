<?php

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Helper\Collection;
use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\Writer\Formatter\PlantUml;
use RuntimeException;

class BmmPlantUmlWriter extends AbstractWriter
{
    public const string DIR = __WRITER_DIR__ . DIRECTORY_SEPARATOR . 'PlantUML' . DIRECTORY_SEPARATOR;

    private PlantUml $plantUml;

    public function __construct(Collection $schemas)
    {
        parent::__construct($schemas);
        $this->plantUml = new PlantUml();
    }

    public function write(): void
    {
        $this->assureOutputDir();
        /** @var BmmSchema $schema */
        foreach ($this->schemas as $schema) {
            /** @var BmmPackage $package */
            foreach ($schema->packages as $package) {
                $this->writePackage($package, $schema, '');
                /** @var BmmPackage $subPackage */
                foreach ($package->packages as $subPackage) {
                    $this->writePackage($subPackage, $schema, $package->name . '.');
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
        $namePrefix = $prefix . str_replace($prefix, '', $namePrefix);
        $packageName = rtrim($namePrefix . str_replace($namePrefix, '', $package->name), '.');
        $dir = self::DIR . $schema->getSchemaId() . '/';
        $packageDir = self::DIR . $schema->getSchemaId() . '/' . str_replace('.', '/', $packageName) . '/';
        $this->assureOutputDir($packageDir);
        foreach ($package->classes as $className) {
            /** @var AbstractBmmClass $class */
            $class = $schema->classDefinitions->get($className) ?? $schema->primitiveTypes->get($className);
            if (!$class) {
                throw new RuntimeException(sprintf('Class %s not found in schema', $className));
            }
            $filename = $className . '.puml';
            self::log('Writing class %s ...', $filename);
            $this->writeFile($packageDir . $filename, $this->plantUml->format($class, $packageName, $schema));
        }
        $filename = $packageName . '.puml';
        self::log('Writing package %s ...', $filename);
        $this->writeFile($dir . $filename, $this->plantUml->format($package, $packageName, $schema));
    }
}
