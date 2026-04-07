<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Console\Command;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Asciidoc;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'asciidoc',
    description: 'Convert BMM JSON schemas to AsciiDoc tables.',
    aliases: ['adoc'],
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
            $toRead = BmmSchemaCollection::availableSchemas();
        }

        try {
            $collection = new BmmSchemaCollection(new ConsoleLogger($output));
            foreach ($toRead as $schema) {
                if ($schema === 'legacy') {
                    $legacyFormat = true;
                    continue;
                }
                $collection->load($schema);
            }
            (new Asciidoc($collection, $legacyFormat))();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln((string) $e, OutputInterface::VERBOSITY_VERBOSE);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
