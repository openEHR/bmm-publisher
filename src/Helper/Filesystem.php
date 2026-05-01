<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Helper;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

final class Filesystem
{
    public static function assureDir(string $dir): void
    {
        if (is_dir($dir)) {
            // Pre-existing dirs (including Docker bind mounts whose parents Docker
            // auto-creates with root ownership) get the benefit of the doubt; an
            // unwritable target will surface at write time via writeFile().
            return;
        }
        if (is_file($dir) || is_link($dir)) {
            throw new RuntimeException(\sprintf('The "%s" already exists but is not a directory.', $dir));
        }
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(\sprintf('Directory "%s" does not exist and cannot be created.', $dir));
        }
        if (!is_writable($dir)) {
            throw new RuntimeException(\sprintf('Directory "%s" is not writable.', $dir));
        }
    }

    public static function writeFile(string $filename, string $content, ?LoggerInterface $logger = null): void
    {
        $bytes = file_put_contents($filename, $content);
        if ($bytes === false) {
            throw new RuntimeException(\sprintf('Failed to write file: %s', $filename));
        }
        ($logger ?? new NullLogger())->info('  Wrote {bytes} bytes to {file}.', ['bytes' => $bytes, 'file' => $filename]);
    }
}
