<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Console\Command;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Asciidoc;
use OpenEHR\BmmPublisher\Writer\InlineSvg;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'asciidoc',
    description: 'Convert BMM JSON schemas to AsciiDoc tables (writer → PlantUML → SVG → inline passthrough).',
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
            $logger = new ConsoleLogger($output);
            $collection = new BmmSchemaCollection($logger);
            foreach ($toRead as $schema) {
                if ($schema === 'legacy') {
                    $legacyFormat = true;
                    continue;
                }
                $collection->load($schema);
            }
            (new Asciidoc($collection, $legacyFormat))();

            $schemaIds = $this->collectSchemaIds($collection);
            $this->renderDiagrams($schemaIds, $output);
            (new InlineSvg($schemaIds, $logger))();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln((string) $e, OutputInterface::VERBOSITY_VERBOSE);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function collectSchemaIds(BmmSchemaCollection $collection): array
    {
        $ids = [];
        foreach ($collection as $schema) {
            $ids[] = $schema->getSchemaId();
        }
        return array_values(array_unique($ids));
    }

    /**
     * Invoke the PlantUML CLI on each schema's plantUML directory in a single batch
     * (one JVM start), producing <name>.svg next to each <name>.puml. The SVGs are
     * consumed and removed by the subsequent InlineSvg pass.
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
