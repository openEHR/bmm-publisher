<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmClass;
use OpenEHR\BmmPublisher\BmmSchemaCollection;
use OpenEHR\BmmPublisher\Writer\Formatter\AsciidocEffective;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AsciidocEffectiveTest extends TestCase
{
    #[Test]
    public function inheritedNoteKeepsAClosingListingDelimiterIntact(): void
    {
        $formatter = new AsciidocEffective(new BmmSchemaCollection());
        $doc = "The code is structured as:\n\n----\na [ '-' b ]\n----";

        $output = $formatter->withInheritedNote($doc, " +\n_Inherited from X_");

        self::assertStringNotContainsString('---- +', $output);
        self::assertStringEndsWith("----\n\n_Inherited from X_", $output);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockDelimiters(): iterable
    {
        yield 'listing' => ['----'];
        yield 'literal' => ['....'];
        yield 'example' => ['===='];
        yield 'sidebar' => ['****'];
        yield 'quote' => ['____'];
        yield 'passthrough' => ['++++'];
        yield 'fenced code' => ['```'];
        yield 'open' => ['--'];
        yield 'comment' => ['////'];
        yield 'trailing whitespace' => ["----  \n"];
    }

    #[Test]
    #[DataProvider('blockDelimiters')]
    public function inheritedNoteStartsAParagraphAfterAnyClosingBlockDelimiter(string $delimiter): void
    {
        $formatter = new AsciidocEffective(new BmmSchemaCollection());

        $output = $formatter->withInheritedNote("Text:\n\n" . trim($delimiter) . "\ncontent\n" . $delimiter, " +\n_Inherited from X_");

        self::assertStringEndsWith(trim($delimiter) . "\n\n_Inherited from X_", $output);
    }

    #[Test]
    public function inheritedNoteAfterADelimiterDropsOnlyTheLineBreakPrefix(): void
    {
        $formatter = new AsciidocEffective(new BmmSchemaCollection());

        self::assertSame("----\n\n+ note", $formatter->withInheritedNote('----', " +\n+ note"));
    }

    #[Test]
    public function inheritedNoteFollowsPlainTextWithALineBreak(): void
    {
        $formatter = new AsciidocEffective(new BmmSchemaCollection());

        self::assertSame("Text. +\n_Inherited from X_", $formatter->withInheritedNote('Text.', " +\n_Inherited from X_"));
        self::assertSame('Text.', $formatter->withInheritedNote('Text.', ''));
    }

    #[Test]
    public function effectiveViewKeepsARedefinitionWhenASharedAncestorIsReachedAgain(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_rm_1.2.0');
        $collection->load('openehr_base_1.3.0');
        $rmSchema = iterator_to_array($collection, false)[0];
        // DV_DURATION inherits DV_AMOUNT, which redefines Any.is_equal, and Iso8601_duration, which reaches Any again
        $class = $rmSchema->classDefinitions->get('DV_DURATION');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new AsciidocEffective($collection))->format($class, 'org.openehr.rm.data_types', $rmSchema);

        self::assertMatchesRegularExpression('/\*is_equal\* \( \+\nother: `[^`]*\[DV_AMOUNT\]/', $output);
        self::assertDoesNotMatchRegularExpression('/\*is_equal\* \( \+\nother: `[^`]*\[Any\]/', $output);
    }

    #[Test]
    public function inheritsLineListsASharedAncestorOnce(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.3.0');
        $schema = iterator_to_array($collection, false)[0];
        // Integer -> Ordered_Numeric -> Ordered and Numeric, and both of those inherit Any
        $class = $schema->primitiveTypes->get('Integer') ?? $schema->classDefinitions->get('Integer');
        self::assertInstanceOf(AbstractBmmClass::class, $class);

        $output = (new AsciidocEffective($collection))->format($class, 'org.openehr.base.foundation_types', $schema);

        $inherits = array_values(preg_grep('/^2\+\|Inherits: /', explode("\n", $output)) ?: [])[0] ?? '';
        self::assertNotSame('', $inherits);
        self::assertSame(1, substr_count($inherits, '[Any]'));
    }

    #[Test]
    public function effectiveTableOfBmmTypeHasNoUnterminatedListing(): void
    {
        $collection = new BmmSchemaCollection();
        $collection->load('openehr_base_1.0.4'); // BMM_TYPE.type_category documents a ---- listing
        $schema = iterator_to_array($collection, false)[0];
        $class = $schema->classDefinitions->get('BMM_TYPE');
        self::assertInstanceOf(BmmClass::class, $class);

        $output = (new AsciidocEffective($collection))->format($class, 'org.openehr.base', $schema);

        self::assertStringNotContainsString('---- +', $output);
    }
}
