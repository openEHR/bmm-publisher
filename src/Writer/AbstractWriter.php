<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Helper\Collection;
use OpenEHR\BmmPublisher\Helper\ConsoleTrait;
use RuntimeException;

abstract class AbstractWriter
{
    use ConsoleTrait;

    public const string DIR = __WRITER_DIR__ . DIRECTORY_SEPARATOR;

    public function __construct(
        protected readonly Collection $schemas,
    ) {
    }

    public function assureOutputDir(string $dir = ''): void
    {
        $dir = $dir ?: static::DIR;
        if (!is_dir($dir)) {
            if (is_file($dir) || is_link($dir)) {
                throw new RuntimeException(sprintf('The "%s" already exists but is not a directory.', $dir));
            }
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Directory "%s" does not exist and cannot be created.', $dir));
            }
        }
        if (!is_writable($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" is not writable.', $dir));
        }
    }

    protected function writeFile(string $filename, string $content): void
    {
        $bytes = file_put_contents($filename, $content);
        self::log('  Wrote %s bytes to %s file.', $bytes, $filename);
    }

    abstract public function write(): void;
}
