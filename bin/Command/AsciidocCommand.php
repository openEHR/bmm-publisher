<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Console\Command;

use OpenEHR\BmmPublisher\CodeGenerator;
use OpenEHR\BmmPublisher\Reader\BmmJsonReader;
use OpenEHR\BmmPublisher\Writer\BmmAsciidocWriter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'publish:asciidoc',
    description: 'Convert BMM JSON schemas to AsciiDoc tables.',
    aliases: ['publish:adoc'],
)]
class AsciidocCommand extends Command
{
    /**
     * @param array<int, string> $filename
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: "BMM schema name(s) to convert, or 'all' for every schema.")]
        array $filename = [],
    ): int {
        if (empty($filename)) {
            $output->writeln('<error>Please specify which BMM schema(s) to read. See --help for usage.</error>');
            return Command::INVALID;
        }

        $legacyFormat = false;
        $toRead = $filename;
        if ($toRead[0] === 'all') {
            $paths = glob(BmmJsonReader::DIR . '*.bmm.json');
            $toRead = array_map(static fn(string $f): string => basename($f), $paths !== false ? $paths : []);
        }

        try {
            $reader = new BmmJsonReader();
            foreach ($toRead as $schema) {
                if ($schema === 'legacy') {
                    $legacyFormat = true;
                    continue;
                }
                $reader->read($schema);
            }
            $generator = new CodeGenerator($reader);
            $generator->addWriter(new BmmAsciidocWriter($legacyFormat));
            $generator->generate();
        } catch (\UnhandledMatchError $e) {
            $output->writeln((string) $e);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
