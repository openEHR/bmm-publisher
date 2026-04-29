<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use OpenEHR\BmmPublisher\Helper\Filesystem;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use OpenEHR\BmmPublisher\Writer\Formatter\SvgPassthrough;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class InlineSvg
{
    private SvgPassthrough $formatter;
    private LoggerInterface $logger;

    /**
     * @param list<string> $schemas Schema IDs (e.g. 'openehr_term_3.0.0') or ['all'] / [] for every dir present.
     */
    public function __construct(
        private readonly array $schemas = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->formatter = new SvgPassthrough();
        $this->logger = $logger ?? new NullLogger();
    }

    public static function outputDir(): string
    {
        return OutputDir::path() . DIRECTORY_SEPARATOR . 'Adoc' . DIRECTORY_SEPARATOR;
    }

    public function __invoke(): void
    {
        $base = rtrim(self::outputDir(), DIRECTORY_SEPARATOR);
        if (!is_dir($base)) {
            $this->logger->warning('No Adoc output dir at {base}; nothing to inline.', ['base' => $base]);
            return;
        }
        foreach ($this->resolveSchemaDirs($base) as $schemaDir) {
            foreach (['classes', 'packages'] as $kind) {
                $dir = $schemaDir . DIRECTORY_SEPARATOR . 'plantUML' . DIRECTORY_SEPARATOR . $kind;
                if (!is_dir($dir)) {
                    continue;
                }
                $svgFiles = glob($dir . DIRECTORY_SEPARATOR . '*.svg') ?: [];
                foreach ($svgFiles as $svgPath) {
                    $this->processSvg($svgPath);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveSchemaDirs(string $base): array
    {
        if ($this->schemas === [] || $this->schemas === ['all']) {
            $entries = glob($base . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
            return array_values(array_map('strval', $entries));
        }
        return array_values(array_map(
            static fn (string $schema): string => $base . DIRECTORY_SEPARATOR . $schema,
            $this->schemas,
        ));
    }

    private function processSvg(string $svgPath): void
    {
        $svg = (string) file_get_contents($svgPath);
        $base = basename($svgPath, '.svg');
        $adocPath = \dirname($svgPath) . DIRECTORY_SEPARATOR . $base . '.adoc';
        $this->logger->notice('Inlining {svg} -> {adoc}', ['svg' => basename($svgPath), 'adoc' => basename($adocPath)]);
        Filesystem::writeFile($adocPath, $this->formatter->format($svg, $base . '.puml'), $this->logger);
        // The SVG is now embedded in the .adoc passthrough block; keep the .puml as source-of-truth
        // but discard the rendered .svg so it does not duplicate committed content.
        @unlink($svgPath);
    }
}
