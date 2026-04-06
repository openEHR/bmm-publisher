<?php

declare(strict_types=1);

namespace Tests\Unit;

use OpenEHR\BmmPublisher\Helper\ResourcesDir;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResourcesDirTest extends TestCase
{
    #[Test]
    public function returnsPathEndingWithResources(): void
    {
        self::assertStringEndsWith(DIRECTORY_SEPARATOR . 'resources', ResourcesDir::path());
    }

    #[Test]
    public function returnsCwdBasedPath(): void
    {
        self::assertSame(getcwd() . DIRECTORY_SEPARATOR . 'resources', ResourcesDir::path());
    }
}
