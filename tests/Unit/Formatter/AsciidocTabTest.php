<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use Cadasto\OpenEHR\BMM\Model\BmmClass;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocTab;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AsciidocTabTest extends TestCase
{
    private BmmClass $class;

    protected function setUp(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.0.4');
        $schema = array_values(iterator_to_array($collection))[0];
        $class = $schema->classDefinitions->get('TERMINOLOGY_ID');
        self::assertInstanceOf(BmmClass::class, $class);
        $this->class = $class;
    }

    #[Test]
    public function formatOmitsExampleTabByDefault(): void
    {
        $output = (new AsciidocTab())->format($this->class, 'terminology_id.adoc');

        self::assertStringNotContainsString('Example::', $output);
        self::assertStringNotContainsString('examples/', $output);
    }

    /** @return array<string, array{bool, string}> */
    public static function exampleTabCases(): array
    {
        return [
            'without example' => [false, ''],
            'with example' => [true, "Example::\n+\n--\ninclude::ROOT:partial\$examples/terminology_id.adoc[]\n--\n\n"],
        ];
    }

    #[Test]
    #[DataProvider('exampleTabCases')]
    public function formatPutsTheExampleTabBetweenEffectiveAndBmm(bool $withExample, string $exampleTab): void
    {
        $output = (new AsciidocTab())->format($this->class, 'terminology_id.adoc', $withExample);

        self::assertStringContainsString(
            "Definition::\n+\ninclude::ROOT:partial\$definitions/terminology_id.adoc[]\n\n"
            . "Effective::\n+\ninclude::ROOT:partial\$effective/terminology_id.adoc[]\n\n"
            . $exampleTab
            . "BMM::\n+\ninclude::ROOT:partial\$BMMs/terminology_id.adoc[]\n\n"
            . "UML::\n+\nimage::ROOT:uml/classes/terminology_id.svg[]\n",
            $output,
        );
    }
}
