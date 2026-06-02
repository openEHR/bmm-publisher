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

    /**
     * @param array<int, string> $exportSchemaIds Schema ids to export; an empty list exports every
     *        loaded schema. Schemas loaded for cross-reference resolution only (dependencies) are
     *        omitted from this list so their diagrams are not written.
     */
    public function __construct(
        private readonly BmmSchemaCollection $schemas,
        private readonly array $exportSchemaIds = [],
    ) {
        $this->plantUml = new PlantUmlFormatter($schemas);
    }

    public static function outputDir(): string
    {
        return OutputDir::path() . DIRECTORY_SEPARATOR . 'PlantUML' . DIRECTORY_SEPARATOR;
    }

    public function __invoke(): void
    {
        Filesystem::assureDir(self::outputDir());
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
