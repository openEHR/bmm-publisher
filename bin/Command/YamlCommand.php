<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Console\Command;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\BmmYamlWriter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'publish:yaml',
    description: 'Convert BMM JSON schemas to YAML format.',
)]
class YamlCommand extends Command
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

        $toRead = $filename;
        if ($toRead[0] === 'all') {
            $toRead = BmmSchemaCollection::availableSchemas();
        }

        try {
            $collection = new BmmSchemaCollection();
            foreach ($toRead as $schema) {
                $collection->load($schema);
            }
            $writer = new BmmYamlWriter($collection->schemas);
            $writer->write();
        } catch (\UnhandledMatchError $e) {
            $output->writeln((string) $e);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
