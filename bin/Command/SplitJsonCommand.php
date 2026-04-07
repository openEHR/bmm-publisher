<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Console\Command;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\BmmJsonSplit;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'split-json',
    description: 'Split latest BMM JSON of each component into per-type files.',
)]
class SplitJsonCommand extends Command
{
    public function __invoke(OutputInterface $output): int
    {
        try {
            $latest = $this->findLatestSchemas();
            if (empty($latest)) {
                $output->writeln('<comment>No BMM JSON files found.</comment>');
                return Command::SUCCESS;
            }

            $collection = new BmmSchemaCollection(new ConsoleLogger($output));
            foreach ($latest as $filename) {
                $collection->load(basename($filename));
            }
            $collection->load('openehr_am_1.4.0');
            (new BmmJsonSplit($collection))();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln((string) $e, OutputInterface::VERBOSITY_VERBOSE);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string> full paths to latest schema files by component
     */
    private function findLatestSchemas(): array
    {
        $files = glob(BmmSchemaCollection::inputDir() . '*.bmm.json');
        if ($files === false) {
            return [];
        }
        $byComponent = [];
        foreach ($files as $path) {
            $base = basename($path, '.bmm.json');
            $parts = explode('_', $base);
            if (\count($parts) < 2) {
                $component = $base;
                $version = '0.0.0';
            } else {
                $version = array_pop($parts);
                $component = implode('_', $parts);
            }
            $current = $byComponent[$component] ?? null;
            if (!$current) {
                $byComponent[$component] = [$version, $path];
            } elseif (version_compare($version, $current[0]) > 0) {
                $byComponent[$component] = [$version, $path];
            }
        }
        return array_map(fn($tuple) => $tuple[1], array_values($byComponent));
    }
}
