<?php

declare(strict_types=1);

namespace Tests\Unit;

use OpenEHR\BmmPublisher\Helper\OutputDir;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OutputDirTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('BMM_OUTPUT_DIR');
        OutputDir::reset();
    }

    #[Test]
    public function defaultsToCwdOutput(): void
    {
        OutputDir::reset();

        self::assertSame(getcwd() . DIRECTORY_SEPARATOR . 'output', OutputDir::path());
    }

    #[Test]
    public function readsEnvVar(): void
    {
        putenv('BMM_OUTPUT_DIR=/custom/output');
        OutputDir::reset();

        self::assertSame('/custom/output', OutputDir::path());
    }

    #[Test]
    public function cachesResult(): void
    {
        putenv('BMM_OUTPUT_DIR=/first');
        OutputDir::reset();

        self::assertSame('/first', OutputDir::path());

        putenv('BMM_OUTPUT_DIR=/second');

        self::assertSame('/first', OutputDir::path());
    }

    #[Test]
    public function resetClearsCache(): void
    {
        putenv('BMM_OUTPUT_DIR=/before');
        OutputDir::reset();
        self::assertSame('/before', OutputDir::path());

        putenv('BMM_OUTPUT_DIR=/after');
        OutputDir::reset();
        self::assertSame('/after', OutputDir::path());
    }
}
