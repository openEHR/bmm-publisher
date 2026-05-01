<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Console\Command;

use OpenEHR\BmmPublisher\Writer\EmbedSvg;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'embed-svg',
    description: 'Sanitise rendered SVGs and publish them under output/Adoc/<schema>/images/.',
)]
class EmbedSvgCommand extends Command
{
    /**
     * @param array<int, string> $filename
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: "Schema name(s) or 'all'.")]
        array $filename = [],
    ): int {
        if (empty($filename)) {
            $output->writeln('<error>Please specify which BMM schema(s) to embed. See --help for usage.</error>');
            return Command::INVALID;
        }

        $schemas = ($filename[0] === 'all') ? ['all'] : array_values($filename);

        try {
            (new EmbedSvg($schemas, new ConsoleLogger($output)))();
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln((string) $e, OutputInterface::VERBOSITY_VERBOSE);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
