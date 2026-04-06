<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Helper;

/**
 * Resolves the base output directory (generated artefacts).
 *
 * Override via BMM_OUTPUT_DIR environment variable (used in Docker).
 * Defaults to {cwd}/output.
 */
final class OutputDir
{
    private static ?string $resolved = null;

    public static function path(): string
    {
        if (self::$resolved === null) {
            $env = getenv('BMM_OUTPUT_DIR');
            self::$resolved = $env !== false ? $env : getcwd() . DIRECTORY_SEPARATOR . 'output';
        }

        return self::$resolved;
    }

    public static function reset(): void
    {
        self::$resolved = null;
    }
}
