<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Helper;

/**
 * Resolves the base resources directory (BMM schemas shipped with the project).
 */
final class ResourcesDir
{
    public static function path(): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . 'resources';
    }
}
