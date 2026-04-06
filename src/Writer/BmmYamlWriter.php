<?php

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Writer class for converting BMM objects to YAML format
 */
class BmmYamlWriter extends AbstractWriter
{
    public const string DIR = __WRITER_DIR__ . DIRECTORY_SEPARATOR . 'BMM-YAML' . DIRECTORY_SEPARATOR;

    /**
     * Write the BMM schema to a YAML file
     *
     * @return void
     * @throws RuntimeException If the schema is not set
     */
    public function write(): void
    {
        /** @var BmmSchema $bmmSchema */
        foreach ($this->reader->files as $bmmSchema) {
            $schemaId = $bmmSchema->getSchemaId();
            $filename = self::DIR . $schemaId . '.bmm.yaml';
            self::log('Writing to [%s] filename.', $filename);
            /** @var array<string, mixed> $data */
            $data = $bmmSchema->jsonSerialize();
            $this->writeFile($filename, Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_COMPACT_NESTED_MAPPING));
        }
        self::log('Done - wrote %s file(s).', $this->reader->files->count());
    }
}
