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
            $logger = new ConsoleLogger($output);
            [$main, $variants] = $this->discoverSchemas();
            if ($main === [] && $variants === []) {
                $output->writeln('<comment>No BMM JSON files found.</comment>');
                return Command::SUCCESS;
            }

            // Latest numeric version per component, plus AM 1.4 explicitly (it is never the latest AM).
            $collection = new BmmSchemaCollection($logger);
            foreach ($main as $filename) {
                $collection->load(basename($filename));
            }
            $collection->load('openehr_am_1.4.0');
            (new BmmJsonSplit($collection))();

            // Variant schemas (e.g. openehr_lang_1.1.0-bmm3) share a schema id with their base
            // component, so they are split separately into a suffix-qualified directory
            // (e.g. LANG-bmm3) to avoid overwriting the base component's per-type files.
            foreach ($variants as $variant) {
                $variantCollection = new BmmSchemaCollection($logger);
                $variantCollection->load(basename($variant['path']));
                (new BmmJsonSplit($variantCollection, $variant['suffix']))();
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln((string) $e, OutputInterface::VERBOSITY_VERBOSE);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Discover schema files to split, grouped into the latest numeric version per component
     * ("main") and version-suffixed variants (e.g. `1.1.0-bmm3`). Variants are tracked under
     * a distinct `<component>-<suffix>` key so they neither compete with their base component's
     * "latest" selection nor get dropped.
     *
     * @return array{0: list<string>, 1: list<array{path: string, suffix: string}>}
     *         [mainPaths, variantEntries]
     */
    private function discoverSchemas(): array
    {
        $files = glob(BmmSchemaCollection::inputDir() . '*.bmm.json');
        if ($files === false) {
            return [[], []];
        }
        /** @var array<string, array{version: string, path: string, suffix: ?string}> $byKey */
        $byKey = [];
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
            $suffix = null;
            if (str_contains($version, '-')) {
                [$version, $suffix] = explode('-', $version, 2);
            }
            $key = $suffix === null ? $component : $component . '-' . $suffix;
            $current = $byKey[$key] ?? null;
            if ($current === null || version_compare($version, $current['version']) > 0) {
                $byKey[$key] = ['version' => $version, 'path' => $path, 'suffix' => $suffix];
            }
        }
        $main = [];
        $variants = [];
        foreach ($byKey as $entry) {
            if ($entry['suffix'] === null) {
                $main[] = $entry['path'];
            } else {
                $variants[] = ['path' => $entry['path'], 'suffix' => $entry['suffix']];
            }
        }
        return [$main, $variants];
    }
}
