<?php

declare(strict_types=1);

namespace Tests\Integration;

use OpenEHR\BmmPublisher\Console\Command\OdinCommand;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class OdinCommandTest extends TestCase
{
    private string $tempOutput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bmm_publisher_odin_cmd_' . uniqid('', true);
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
     * The headline behaviour: two input files that resolve to the same schema id
     * (`openehr_lang_1.1.0` and the `-bmm3` overlay) must produce two distinct, non-overwriting
     * ODIN files named after their inputs.
     */
    #[Test]
    public function distinctInputsSharingASchemaIdProduceDistinctFiles(): void
    {
        $tester = new CommandTester(new OdinCommand());
        $exit = $tester->execute(['input' => ['openehr_lang_1.1.0', 'openehr_lang_1.1.0-bmm3']]);

        self::assertSame(0, $exit, $tester->getDisplay());

        $dir = $this->tempOutput . DIRECTORY_SEPARATOR . 'BMM-ODIN' . DIRECTORY_SEPARATOR;
        $base = $dir . 'openehr_lang_1.1.0.bmm';
        $variant = $dir . 'openehr_lang_1.1.0-bmm3.bmm';
        self::assertFileExists($base);
        self::assertFileExists($variant);
        self::assertNotSame(
            (string) file_get_contents($base),
            (string) file_get_contents($variant),
            'the variant must not overwrite the base schema',
        );
    }

    #[Test]
    public function emptyInputIsRejected(): void
    {
        $tester = new CommandTester(new OdinCommand());
        self::assertNotSame(0, $tester->execute(['input' => []]));
    }

    /**
     * @param non-empty-string $input
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\TestWith(['openehr_lang_1.1.0-bmm3', 'openehr_lang_1.1.0-bmm3'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['resources/openehr_lang_1.1.0-bmm3.bmm.json', 'openehr_lang_1.1.0-bmm3'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['/abs/path/openehr_term_3.0.0.json', 'openehr_term_3.0.0'])]
    public function odinBasenameStripsDirectoryAndSuffix(string $input, string $expected): void
    {
        $method = new \ReflectionMethod(OdinCommand::class, 'odinBasename');
        self::assertSame($expected, $method->invoke(null, $input));
    }
}
