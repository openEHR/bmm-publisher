<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Console\Command;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Asciidoc;
use OpenEHR\BmmPublisher\Writer\EmbedSvg;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'asciidoc',
    description: 'Convert BMM JSON schemas to AsciiDoc tables (writer → PlantUML → SVG → images/).',
    aliases: ['adoc'],
)]
class AsciidocCommand extends Command
{
    /**
     * @param array<int, string> $input
     * @param array<int, string> $dependency
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: "Schema id(s) or .bmm.json path(s) to convert, or 'all' for every bundled schema.")]
        array $input = [],
        #[Option(description: 'Dependency schema id or .bmm.json path, loaded for cross-refs only, not exported. Repeatable.', shortcut: 'd')]
        array $dependency = [],
    ): int {
        if (empty($input)) {
            $output->writeln('<error>Please specify which BMM schema(s) to read. See --help for usage.</error>');
            return Command::INVALID;
        }

        $toRead = $input === ['all'] ? BmmSchemaCollection::availableSchemas() : $input;

        try {
            $logger = new ConsoleLogger($output);
            $collection = new BmmSchemaCollection($logger);
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
            (new Asciidoc($collection, $exportSchemaIds))();

            $this->renderDiagrams($exportSchemaIds, $output);
            (new EmbedSvg($exportSchemaIds, $logger))();
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

    /**
     * Invoke the PlantUML CLI on each schema's plantUML directory in a single batch
     * (one JVM start), producing <name>.svg next to each <name>.puml. The SVGs are
     * relocated to the schema's images/ directory by the subsequent EmbedSvg pass.
     *
     * @param list<string> $schemaIds
     */
    private function renderDiagrams(array $schemaIds, OutputInterface $output): void
    {
        // PlantUML's CLI does not recurse from a parent directory; pass each leaf
        // (classes/, packages/) explicitly so it picks up every .puml in one batch.
        $dirs = [];
        foreach ($schemaIds as $id) {
            foreach (['classes', 'packages'] as $kind) {
                $dir = Asciidoc::outputDir() . $id . DIRECTORY_SEPARATOR . 'plantUML' . DIRECTORY_SEPARATOR . $kind;
                if (is_dir($dir)) {
                    $dirs[] = $dir;
                }
            }
        }
        if ($dirs === []) {
            return;
        }

        $output->writeln(
            \sprintf('Rendering PlantUML diagrams in %d schema dir(s)...', \count($dirs)),
            OutputInterface::VERBOSITY_VERBOSE,
        );
        $process = new Process(['plantuml', '-tsvg', '-nometadata', ...$dirs]);
        $process->setTimeout(null);
        $process->mustRun(function ($type, $buffer) use ($output): void {
            $output->write($buffer, false, OutputInterface::VERBOSITY_VERBOSE);
        });
    }
}
