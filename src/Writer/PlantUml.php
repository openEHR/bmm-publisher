<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\Filesystem;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use OpenEHR\BmmPublisher\Writer\Formatter\PlantUml as PlantUmlFormatter;
use RuntimeException;

class PlantUml
{
    private PlantUmlFormatter $plantUml;

    public function __construct(
        private readonly BmmSchemaCollection $schemas,
    ) {
        $this->plantUml = new PlantUmlFormatter($schemas);
    }

    public static function outputDir(): string
    {
        return OutputDir::path() . DIRECTORY_SEPARATOR . 'PlantUML' . DIRECTORY_SEPARATOR;
    }

    public function __invoke(): void
    {
        $logger = $this->schemas->logger;
        Filesystem::assureDir(self::outputDir());
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
        $logger = $this->schemas->logger;
        if (!\count($package->classes)) {
            $logger->warning('Empty package {package}.', ['package' => $package->name]);
            return;
        }
        $prefix = 'org.openehr.' . strtolower($schema->schemaName) . '.';
        $namePrefix = $prefix . str_replace($prefix, '', $namePrefix);
        $packageName = rtrim($namePrefix . str_replace($namePrefix, '', $package->name), '.');
        $dir = self::outputDir() . $schema->getSchemaId() . '/';
        $packageDir = self::outputDir() . $schema->getSchemaId() . '/' . str_replace('.', '/', $packageName) . '/';
        Filesystem::assureDir($packageDir);
        foreach ($package->classes as $className) {
            /** @var AbstractBmmClass $class */
            $class = $schema->classDefinitions->get($className) ?? $schema->primitiveTypes->get($className);
            if (!$class) {
                throw new RuntimeException(\sprintf('Class %s not found in schema', $className));
            }
            $filename = $className . '.puml';
            $logger->notice('Writing class {file} ...', ['file' => $filename]);
            Filesystem::writeFile($packageDir . $filename, $this->plantUml->format($class, $packageName, $schema), $logger);
        }
        $filename = $packageName . '.puml';
        $logger->notice('Writing package {file} ...', ['file' => $filename]);
        Filesystem::writeFile($dir . $filename, $this->plantUml->format($package, $packageName, $schema), $logger);
    }
}
