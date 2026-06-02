<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Helper\Filesystem;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocDefinition;
use RuntimeException;

/**
 * Writes the legacy `docs/UML/classes/` layout: one AsciiDoc class-definition
 * table per class, named `org.openehr.<schema>.<package>.<class>.adoc`, flat in
 * a single target directory.
 *
 * Unlike {@see Asciidoc}, this writer emits *only* the definition tables — no
 * effective views, tabs partials, BMM JSON, or PlantUML — and writes straight
 * to a caller-supplied directory rather than the `output/Adoc/<schema>/` tree.
 * It is the generator behind the `legacy-adoc` command, used to regenerate the
 * pre-Antora class tables committed in the specification repositories.
 */
final class LegacyClassDefinitions
{
    private readonly AsciidocDefinition $definition;

    /**
     * @param array<int, string> $exportSchemaIds Schema ids to export; an empty list exports every
     *        loaded schema. Schemas loaded for cross-reference resolution only (dependencies) are
     *        omitted from this list so their class tables are not written.
     */
    public function __construct(
        private readonly BmmSchemaCollection $schemas,
        private readonly string $outputDir,
        private readonly array $exportSchemaIds = [],
    ) {
        // Legacy mode emits the `=== <CLASS> Class` heading above each table.
        $this->definition = new AsciidocDefinition($schemas, true);
    }

    public function __invoke(): void
    {
        Filesystem::assureDir($this->outputDir);
        $this->schemas->forEachPackage($this->writePackage(...));
    }

    private function writePackage(BmmPackage $package, BmmSchema $schema, string $namePrefix): void
    {
        // Dependency schemas stay in the collection for cross-reference resolution but are not exported.
        if ($this->exportSchemaIds !== [] && !\in_array($schema->getSchemaId(), $this->exportSchemaIds, true)) {
            return;
        }

        $logger = $this->schemas->logger;
        if (!\count($package->classes)) {
            $logger->warning('Empty package {package}.', ['package' => $package->name]);
            return;
        }

        $prefix = LegacyClassNaming::packagePrefix($schema, $namePrefix, $package);
        foreach ($package->classes as $className) {
            /** @var AbstractBmmClass|null $class */
            $class = $schema->classDefinitions->get($className) ?? $schema->primitiveTypes->get($className);
            if (!$class) {
                throw new RuntimeException(\sprintf('WARN: Class %s not found in schema', $className));
            }
            $filename = LegacyClassNaming::classFilename($prefix, $className);
            $logger->notice('Writing {file} class ...', ['file' => $filename]);
            Filesystem::writeFile(
                $this->outputDir . DIRECTORY_SEPARATOR . $filename,
                $this->definition->format($class, $prefix, $schema),
                $logger,
            );
        }
    }
}
