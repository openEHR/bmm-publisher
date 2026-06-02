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
            $this->writeSchema($bmmSchema, $bmmSchema->getSchemaId());
        }
        $logger->notice('Done - wrote {count} file(s).', ['count' => $this->schemas->count()]);
    }

    /**
     * Write a single schema to `<outputDir>/<basename>.bmm.yaml`.
     *
     * The basename is supplied by the caller (the source filename, not the
     * schema id) so that distinct input files sharing a schema id — e.g.
     * `openehr_lang_1.1.0.bmm.json` and `openehr_lang_1.1.0-bmm3.bmm.json`,
     * which both resolve to schema id `openehr_lang_1.1.0` — produce distinct
     * YAML outputs instead of silently overwriting one another.
     */
    public function writeSchema(BmmSchema $schema, string $basename): void
    {
        $logger = $this->schemas->logger;
        Filesystem::assureDir(self::outputDir());
        $filename = self::outputDir() . $basename . '.bmm.yaml';
        $logger->notice('Writing to {file}.', ['file' => $filename]);
        /** @var array<string, mixed> $data */
        $data = $schema->jsonSerialize();
        $tagged = self::convertTypeTags($data);
        Filesystem::writeFile($filename, Yaml::dump($tagged, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK), $logger);
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
