<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Console\Command;

use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\BmmYaml;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'yaml',
    description: 'Convert BMM JSON schemas to YAML format.',
)]
class YamlCommand extends Command
{
    /**
     * @param array<int, string> $input
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: "Schema id(s) or .bmm.json path(s) to convert, or 'all' for every bundled schema.")]
        array $input = [],
    ): int {
        if (empty($input)) {
            $output->writeln('<error>Please specify which BMM schema(s) to read. See --help for usage.</error>');
            return Command::INVALID;
        }

        $toRead = $input === ['all'] ? BmmSchemaCollection::availableSchemas() : $input;

        try {
            $logger = new ConsoleLogger($output);
            $count = 0;
            foreach ($toRead as $inputName) {
                // Each input is loaded into its own collection and written to a YAML file named
                // after the input. Processing per file (rather than one shared collection keyed
                // by schema id) keeps inputs that share a schema id — e.g. openehr_lang_1.1.0 and
                // openehr_lang_1.1.0-bmm3 — from overwriting one another. The YAML output is a pure
                // per-schema serialization, so no cross-schema dependencies are needed.
                $collection = new BmmSchemaCollection($logger);
                $this->loadInput($collection, $inputName);
                $writer = new BmmYaml($collection);
                foreach ($collection as $schema) {
                    $writer->writeSchema($schema, self::yamlBasename($inputName));
                    $count++;
                }
            }
            $logger->notice('Done - wrote {count} file(s).', ['count' => $count]);
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
     * Derive the YAML output basename from an input id or path, mirroring the input filename:
     * strips any directory and a trailing `.bmm.json`/`.json` suffix. e.g. both
     * `openehr_lang_1.1.0-bmm3` and `resources/openehr_lang_1.1.0-bmm3.bmm.json`
     * yield `openehr_lang_1.1.0-bmm3`.
     */
    private static function yamlBasename(string $input): string
    {
        $name = basename($input);
        foreach (['.bmm.json', '.json'] as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return substr($name, 0, -\strlen($suffix));
            }
        }
        return $name;
    }
}
