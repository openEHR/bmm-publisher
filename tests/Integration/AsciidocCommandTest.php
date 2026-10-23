<?php

declare(strict_types=1);

namespace Tests\Integration;

use OpenEHR\BmmPublisher\Console\Command\AsciidocCommand;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\ExecutableFinder;

final class AsciidocCommandTest extends TestCase
{
    private string $tempOutput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bmm_publisher_adoc_cmd_' . uniqid('', true);
        self::assertTrue(mkdir($this->tempOutput, 0700, true));
        putenv('BMM_OUTPUT_DIR=' . $this->tempOutput);
        OutputDir::reset();
    }

    protected function tearDown(): void
    {
        putenv('BMM_OUTPUT_DIR');
        OutputDir::reset();
        if (is_dir($this->tempOutput)) {
            $this->deleteTree($this->tempOutput);
        }
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

    /**
     * A typo or an unmounted path must not publish every class without its Example tab and exit 0.
     */
    #[Test]
    public function missingExamplesDirIsRejectedBeforeAnythingIsWritten(): void
    {
        $tester = new CommandTester(new AsciidocCommand());
        $exit = $tester->execute([
            'input' => ['openehr_term_3.0.0'],
            '--examples-dir' => $this->tempOutput . DIRECTORY_SEPARATOR . 'no-such-dir',
        ]);

        self::assertSame(Command::INVALID, $exit, $tester->getDisplay());
        self::assertStringContainsString('no-such-dir not found', $tester->getDisplay());
        self::assertDirectoryDoesNotExist($this->tempOutput . DIRECTORY_SEPARATOR . 'Adoc');
    }

    #[Test]
    public function examplesDirAddsAnExampleTabToTheMatchingClass(): void
    {
        if ((new ExecutableFinder())->find('plantuml') === null) {
            self::markTestSkipped('The asciidoc command renders diagrams with the plantuml CLI, which is not on PATH.');
        }
        $examplesDir = $this->tempOutput . DIRECTORY_SEPARATOR . 'examples';
        self::assertTrue(mkdir($examplesDir));
        file_put_contents($examplesDir . DIRECTORY_SEPARATOR . 'terminology.adoc', "[source,json]\n----\n{}\n----\n");

        $tester = new CommandTester(new AsciidocCommand());
        $exit = $tester->execute(['input' => ['openehr_term_3.0.0'], '--examples-dir' => $examplesDir]);

        self::assertSame(Command::SUCCESS, $exit, $tester->getDisplay());
        $classesDir = $this->tempOutput . '/Adoc/openehr_term_3.0.0/classes/';
        self::assertStringContainsString(
            "Example::\n+\n--\ninclude::ROOT:partial\$examples/terminology.adoc[]\n--\n",
            (string) file_get_contents($classesDir . 'terminology.adoc'),
        );
        self::assertStringNotContainsString('Example::', (string) file_get_contents($classesDir . 'code.adoc'));
    }
}
