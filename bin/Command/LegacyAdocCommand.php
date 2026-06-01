<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Console\Command;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use OpenEHR\BmmPublisher\Writer\LegacyClassDefinitions;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'legacy-adoc',
    description: 'Generate the legacy docs/UML/classes layout (class-definition tables only) from a BMM JSON file into a chosen directory.',
)]
class LegacyAdocCommand extends Command
{
    /**
     * @param array<int, string> $input
     * @param array<int, string> $dependency
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: "Schema id(s) or .bmm.json path(s) to export, or 'all'. These are the schemas whose class tables are written.")]
        array $input = [],
        #[Option(description: 'Dependency schema id or .bmm.json path, loaded for cross-refs only, not exported. Repeatable.', shortcut: 'd')]
        array $dependency = [],
        #[Option(description: 'Output directory for class .adoc files. Default: <output>/legacy-adoc/<schema_id> per schema.', shortcut: 'o')]
        string $outputDir = '',
    ): int {
        if (empty($input)) {
            $output->writeln('<error>Please specify the BMM schema id(s) or JSON file(s) to read. See --help for usage.</error>');
            return Command::INVALID;
        }

        $toRead = $input === ['all'] ? BmmSchemaCollection::availableSchemas() : $input;

        try {
            $collection = new BmmSchemaCollection(new ConsoleLogger($output));
            // Load the schemas to export first, then capture their ids so dependencies stay unexported.
            foreach ($toRead as $schema) {
                $this->loadInput($collection, $schema);
            }
            $exportSchemaIds = [];
            foreach ($collection as $schema) {
                $exportSchemaIds[] = $schema->getSchemaId();
            }
            $exportSchemaIds = array_values(array_unique($exportSchemaIds));
            // Dependencies are loaded for cross-reference resolution only.
            foreach ($dependency as $schema) {
                $this->loadInput($collection, $schema);
            }
            if ($outputDir !== '') {
                // Explicit target: all exported schemas are written flat into the given directory.
                (new LegacyClassDefinitions($collection, $outputDir, $exportSchemaIds))();
            } else {
                // Default: one directory per schema, inferred from its id, under <output>/legacy-adoc.
                $base = OutputDir::path() . DIRECTORY_SEPARATOR . 'legacy-adoc';
                foreach ($exportSchemaIds as $schemaId) {
                    (new LegacyClassDefinitions($collection, $base . DIRECTORY_SEPARATOR . $schemaId, [$schemaId]))();
                }
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln((string) $e, OutputInterface::VERBOSITY_VERBOSE);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Load a single input: a literal `.bmm.json` path wins, otherwise it is treated as a bundled schema id.
     */
    private function loadInput(BmmSchemaCollection $collection, string $input): void
    {
        if (is_file($input)) {
            $collection->loadPath($input);
        } else {
            $collection->load($input);
        }
    }
}
