<?php

declare(strict_types=1);

namespace Tests\Unit;

use OpenEHR\BmmPublisher\Helper\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class FilesystemTest extends TestCase
{
    private string $tempBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bmm_publisher_fs_test_' . uniqid('', true);
        self::assertTrue(mkdir($this->tempBase, 0700, true));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempBase)) {
            parent::tearDown();

            return;
        }
        $this->deleteTree($this->tempBase);
        parent::tearDown();
    }

    private function deleteTree(string $dir): void
    {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) && !is_link($path) ? $this->deleteTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    #[Test]
    public function assureDirCreatesNestedPath(): void
    {
        $dir = $this->tempBase . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b' . DIRECTORY_SEPARATOR . 'c';

        Filesystem::assureDir($dir);

        self::assertDirectoryExists($dir);
        self::assertTrue(is_writable($dir));
    }

    #[Test]
    public function assureDirSucceedsWhenDirectoryAlreadyExists(): void
    {
        $dir = $this->tempBase . DIRECTORY_SEPARATOR . 'existing';
        self::assertTrue(mkdir($dir, 0700, true));

        Filesystem::assureDir($dir);

        self::assertDirectoryExists($dir);
    }

    #[Test]
    public function assureDirThrowsWhenPathIsAFile(): void
    {
        $path = $this->tempBase . DIRECTORY_SEPARATOR . 'not_a_dir';
        self::assertSame(1, file_put_contents($path, 'x'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists but is not a directory');

        Filesystem::assureDir($path);
    }

    #[Test]
    public function assureDirToleratesExistingNonWritableDirectory(): void
    {
        // Mirrors the Docker bind-mount case: Docker auto-creates the parent path
        // with root ownership when only a child is mounted, so the container's
        // app user sees an unwritable parent even though it only writes into
        // children. Pre-existing dirs must not be rejected at preflight; any
        // genuine permission problem surfaces at write time via writeFile().
        $dir = $this->tempBase . DIRECTORY_SEPARATOR . 'readonly_parent';
        self::assertTrue(mkdir($dir, 0500, true));

        try {
            Filesystem::assureDir($dir);
            self::assertDirectoryExists($dir);
        } finally {
            // Restore writable mode so tearDown can remove it.
            chmod($dir, 0700);
        }
    }

    #[Test]
    public function writeFileWritesContent(): void
    {
        $file = $this->tempBase . DIRECTORY_SEPARATOR . 'out.txt';
        $content = "line1\nline2\n";

        Filesystem::writeFile($file, $content, new NullLogger());

        self::assertFileExists($file);
        self::assertSame($content, (string) file_get_contents($file));
    }
}
