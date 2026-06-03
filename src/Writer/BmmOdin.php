<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\Filesystem;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use OpenEHR\BmmPublisher\Writer\Formatter\Odin;

/**
 * Writes each loaded schema as an ODIN `.bmm` file — the same format as the hand-authored
 * schemas in the openEHR `specifications-ITS-BMM` repository, generated from the BMM JSON.
 */
class BmmOdin
{
    private readonly Odin $odin;

    public function __construct(
        private readonly BmmSchemaCollection $schemas,
    ) {
        $this->odin = new Odin();
    }

    public static function outputDir(): string
    {
        return OutputDir::path() . DIRECTORY_SEPARATOR . 'BMM-ODIN' . DIRECTORY_SEPARATOR;
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
     * Write a single schema to `<outputDir>/<basename>.bmm`.
     *
     * The basename is supplied by the caller (the source filename, not the schema id) so that
     * distinct input files sharing a schema id — e.g. `openehr_lang_1.1.0.bmm.json` and
     * `openehr_lang_1.1.0-bmm3.bmm.json` — produce distinct ODIN outputs instead of overwriting
     * one another.
     */
    public function writeSchema(BmmSchema $schema, string $basename): void
    {
        $logger = $this->schemas->logger;
        Filesystem::assureDir(self::outputDir());
        $filename = self::outputDir() . $basename . '.bmm';
        $logger->notice('Writing to {file}.', ['file' => $filename]);
        /** @var array<string, mixed> $data */
        $data = $schema->jsonSerialize();
        Filesystem::writeFile($filename, $this->odin->format($data), $logger);
    }
}
