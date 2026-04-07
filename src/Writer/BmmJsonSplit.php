<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\Filesystem;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use RuntimeException;

class BmmJsonSplit
{
    public function __construct(
        private readonly BmmSchemaCollection $schemas,
    ) {
    }

    public static function outputDir(): string
    {
        return OutputDir::path() . DIRECTORY_SEPARATOR . 'BMM-JSON-development-types' . DIRECTORY_SEPARATOR;
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
        $packagePrefixName = 'org.openehr.' . strtolower($schema->schemaName) . '.';
        $packageName = $packagePrefixName . str_replace($packagePrefixName, '', $namePrefix . $package->name);
        if ($schema->schemaName === 'am') {
            $outputDir = self::outputDir() . 'AM' . (str_starts_with($schema->rmRelease, '2') ? '2' : '') . '/';
        } else {
            $outputDir = self::outputDir() . strtoupper($schema->schemaName) . '/';
        }
        Filesystem::assureDir($outputDir);
        foreach ($package->classes as $className) {
            /** @var AbstractBmmClass $class */
            $class = $schema->classDefinitions->get($className) ?? $schema->primitiveTypes->get($className);
            if (!$class) {
                throw new RuntimeException(\sprintf('WARN: Class %s not found in schema', $className));
            }
            $filename = strtoupper($className) . '.bmm.json';
            $logger->info('Preparing {file} class ...', ['file' => $filename]);
            $data = $class->jsonSerialize();
            $data['package'] = $packageName;
            $parts = array_reverse(explode('.', str_replace($packagePrefixName, '', $packageName)));
            if (!empty($parts)) {
                $fragment = match ($parts[0]) {
                    'assertion' => '_the_assertion_package',
                    'archetype' => '_the_archetype_package',
                    'rm_overlay' => '_the_rm_overlay_package',
                    default => '_' . $parts[0] . '_package',
                };
                $page = $parts[2] ?? $parts[1] ?? $parts[0];
                $page = match ($page) {
                    'aom14' => 'AOM1.4.html',
                    'aom2' => 'AOM2.html',
                    default => $page . '.html',
                };
                $component = strtoupper($schema->schemaName);
                $data['specUrl'] = "https://specifications.openehr.org/releases/{$component}/development/{$page}#{$fragment}";
            }
            $logger->notice('Writing {file} class ...', ['file' => $filename]);
            $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            Filesystem::writeFile($outputDir . $filename, $content, $logger);
        }
    }
}
