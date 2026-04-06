<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher;

use Cadasto\OpenEHR\BMM\Helper\Collection;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\Helper\ConsoleTrait;
use RuntimeException;

class BmmSchemaCollection
{
    use ConsoleTrait;

    public const string DIR = __READER_DIR__ . DIRECTORY_SEPARATOR . 'BMM-JSON' . DIRECTORY_SEPARATOR;

    public readonly Collection $schemas;

    public function __construct()
    {
        $this->schemas = new Collection();
    }

    public function load(string $filename): void
    {
        if (!str_ends_with($filename, '.bmm.json')) {
            $filename .= '.bmm.json';
        }
        $path = self::DIR . $filename;
        if (!is_readable($path) || !is_file($path)) {
            throw new RuntimeException("File missing or not readable: $path.");
        }
        self::log('Reading [%s] filename...', $path);
        $jsonContent = file_get_contents($path);
        if ($jsonContent === false) {
            throw new RuntimeException("Failed to read file: $path");
        }
        $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);

        self::log("Deserializing to BMM objects...");
        $schema = BmmSchema::fromArray($data);
        self::log("  Read %d BMM Classes from %s.", $schema->classDefinitions->count(), $schema->getSchemaId());
        $this->schemas->add($schema);
    }

    /**
     * @return list<string> all .bmm.json filenames (basename only) in the input directory
     */
    public static function availableSchemas(): array
    {
        $paths = glob(self::DIR . '*.bmm.json');

        return array_map(static fn(string $f): string => basename($f), $paths !== false ? $paths : []);
    }
}
