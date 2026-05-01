<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use OpenEHR\BmmPublisher\Helper\Filesystem;
use OpenEHR\BmmPublisher\Helper\OutputDir;
use OpenEHR\BmmPublisher\Writer\Formatter\SvgSanitiser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class EmbedSvg
{
    /**
     * Map plantUML/<source-kind>/ source dirs to their published images/uml/<target-kind>/ dirs.
     */
    private const KIND_MAP = [
        'classes' => 'classes',
        'packages' => 'diagrams',
    ];

    private SvgSanitiser $sanitiser;
    private LoggerInterface $logger;

    /**
     * @param list<string> $schemas Schema IDs (e.g. 'openehr_term_3.0.0') or ['all'] / [] for every dir present.
     */
    public function __construct(
        private readonly array $schemas = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->sanitiser = new SvgSanitiser();
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
            $this->logger->warning('No Adoc output dir at {base}; nothing to embed.', ['base' => $base]);
            return;
        }
        foreach ($this->resolveSchemaDirs($base) as $schemaDir) {
            foreach (self::KIND_MAP as $sourceKind => $targetKind) {
                $sourceDir = $schemaDir . DIRECTORY_SEPARATOR . 'plantUML' . DIRECTORY_SEPARATOR . $sourceKind;
                if (!is_dir($sourceDir)) {
                    continue;
                }
                $targetDir = $schemaDir . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'uml' . DIRECTORY_SEPARATOR . $targetKind;
                Filesystem::assureDir($targetDir);
                $svgFiles = glob($sourceDir . DIRECTORY_SEPARATOR . '*.svg') ?: [];
                foreach ($svgFiles as $svgPath) {
                    $this->processSvg($svgPath, $targetDir, $targetKind);
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

    private function processSvg(string $svgPath, string $targetDir, string $targetKind): void
    {
        $svg = (string) file_get_contents($svgPath);
        $base = basename($svgPath, '.svg');
        $sanitised = $this->sanitiser->sanitise($svg, $base . '.puml');
        $target = $targetDir . DIRECTORY_SEPARATOR . $base . '.svg';
        $this->logger->notice('Publishing {svg} -> images/uml/{kind}/{name}.svg', [
            'svg' => basename($svgPath),
            'kind' => $targetKind,
            'name' => $base,
        ]);
        Filesystem::writeFile($target, $sanitised, $this->logger);
        // The .puml is the source of truth and stays committed; the rendered .svg now lives under images/uml/<kind>/.
        @unlink($svgPath);
    }
}
