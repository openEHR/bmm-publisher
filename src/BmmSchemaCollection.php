<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher;

use Cadasto\OpenEHR\BMM\Helper\Collection;
use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\Helper\ResourcesDir;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * @implements \IteratorAggregate<string, BmmSchema>
 */
class BmmSchemaCollection implements \IteratorAggregate
{
    private readonly Collection $schemas;
    public readonly LoggerInterface $logger;

    /** @var array<string, AbstractBmmClass|null> */
    private array $classCache = [];

    /** @var array<string, string|null> */
    private array $qnameCache = [];

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->schemas = new Collection();
        $this->logger = $logger ?? new NullLogger();
    }

    public static function inputDir(): string
    {
        return ResourcesDir::path() . DIRECTORY_SEPARATOR;
    }

    public function load(string $filename): void
    {
        if (!str_ends_with($filename, '.bmm.json')) {
            $filename .= '.bmm.json';
        }
        $filename = basename($filename);
        $path = self::inputDir() . $filename;
        if (!is_readable($path) || !is_file($path)) {
            throw new RuntimeException("File missing or not readable: $path.");
        }
        $this->logger->notice('Reading {file}...', ['file' => $path]);
        $jsonContent = file_get_contents($path);
        if ($jsonContent === false) {
            throw new RuntimeException("Failed to read file: $path");
        }
        $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);

        $this->logger->info('Deserializing to BMM objects...');
        $schema = BmmSchema::fromArray($data);
        $this->logger->notice('  Read {count} BMM Classes from {schema}.', [
            'count' => $schema->classDefinitions->count(),
            'schema' => $schema->getSchemaId(),
        ]);
        $this->schemas->add($schema);
    }

    /**
     * Walk all packages (up to 3 levels deep) across all loaded schemas.
     *
     * @param callable(BmmPackage, BmmSchema, string): void $callback receives (package, schema, namePrefix)
     */
    public function forEachPackage(callable $callback): void
    {
        /** @var BmmSchema $schema */
        foreach ($this->schemas as $schema) {
            /** @var BmmPackage $package */
            foreach ($schema->packages as $package) {
                $callback($package, $schema, '');
                /** @var BmmPackage $subPackage */
                foreach ($package->packages as $subPackage) {
                    $callback($subPackage, $schema, $package->name . '.');
                    /** @var BmmPackage $subSubPackage */
                    foreach ($subPackage->packages as $subSubPackage) {
                        $callback($subSubPackage, $schema, $package->name . '.' . $subPackage->name . '.');
                    }
                }
            }
        }
    }

    public function count(): int
    {
        return $this->schemas->count();
    }

    /** @return \ArrayIterator<string, BmmSchema> */
    public function getIterator(): \ArrayIterator
    {
        /** @var \ArrayIterator<string, BmmSchema> */
        return $this->schemas->getIterator();
    }

    /**
     * Resolve a class by name across all loaded schemas (cached).
     */
    public function getClass(string $className): ?AbstractBmmClass
    {
        if (\array_key_exists($className, $this->classCache)) {
            return $this->classCache[$className];
        }

        /** @var BmmSchema $schema */
        foreach ($this->schemas as $schema) {
            $class = $schema->classDefinitions->get($className) ?? $schema->primitiveTypes->get($className);
            if ($class instanceof AbstractBmmClass) {
                return $this->classCache[$className] = $class;
            }
        }
        return $this->classCache[$className] = null;
    }

    /**
     * Resolve the package-qualified name of a class across all loaded schemas (cached).
     */
    public function getClassPackageQName(string $className): ?string
    {
        if (\array_key_exists($className, $this->qnameCache)) {
            return $this->qnameCache[$className];
        }

        /** @var BmmSchema $schema */
        foreach ($this->schemas as $schema) {
            $qname = $schema->getClassPackageQName($className);
            if (!empty($qname)) {
                return $this->qnameCache[$className] = $qname;
            }
        }
        return $this->qnameCache[$className] = null;
    }

    /**
     * @return list<string> all .bmm.json filenames (basename only) in the input directory
     */
    public static function availableSchemas(): array
    {
        $paths = glob(self::inputDir() . '*.bmm.json');

        return array_map(static fn(string $f): string => basename($f), $paths !== false ? $paths : []);
    }
}
