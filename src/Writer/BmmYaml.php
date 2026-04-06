<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\Filesystem;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class BmmYaml
{
    public function __construct(
        private readonly BmmSchemaCollection $schemas,
    ) {
    }

    public static function outputDir(): string
    {
        return OutputDir::path() . DIRECTORY_SEPARATOR . 'BMM-YAML' . DIRECTORY_SEPARATOR;
    }

    public function __invoke(): void
    {
        $logger = $this->schemas->logger;
        Filesystem::assureDir(self::outputDir());
        /** @var BmmSchema $bmmSchema */
        foreach ($this->schemas as $bmmSchema) {
            $schemaId = $bmmSchema->getSchemaId();
            $filename = self::outputDir() . $schemaId . '.bmm.yaml';
            $logger->notice('Writing to {file}.', ['file' => $filename]);
            /** @var array<string, mixed> $data */
            $data = $bmmSchema->jsonSerialize();
            $tagged = self::convertTypeTags($data);
            Filesystem::writeFile($filename, Yaml::dump($tagged, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK), $logger);
        }
        $logger->notice('Done - wrote {count} file(s).', ['count' => $this->schemas->count()]);
    }

    /**
     * Recursively convert `_type` keys into Symfony YAML TaggedValue objects.
     *
     * Transforms `['_type' => 'P_BMM_SINGLE_PROPERTY', 'name' => 'foo', ...]`
     * into `new TaggedValue('P_BMM_SINGLE_PROPERTY', ['name' => 'foo', ...])`.
     */
    private static function convertTypeTags(mixed $data): mixed
    {
        if (!\is_array($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (\is_array($value) && isset($value['_type'])) {
                $tag = $value['_type'];
                unset($value['_type']);
                $result[$key] = new TaggedValue($tag, self::convertTypeTags($value));
            } else {
                $result[$key] = self::convertTypeTags($value);
            }
        }

        return $result;
    }
}
