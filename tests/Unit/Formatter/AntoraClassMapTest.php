<?php

declare(strict_types=1);

namespace Tests\Unit\Formatter;

use OpenEHR\BmmPublisher\Writer\Formatter\AntoraClassMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Tests\Unit\Support\RecordingLogger;

final class AntoraClassMapTest extends TestCase
{
    private RecordingLogger $logger;
    private AntoraClassMap $classMap;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->classMap = new AntoraClassMap([
            'class_dv_date_time' => '{page_rm_data_types_date_time}#_dv_date_time_class',
            'class_proportion_kind' => '{page_rm_data_types_quantity}#_proportion_kind_class',
            'class_message_content' => '{page_rm_ehr_extract_message}',
            'class_aom2_constraint_status' => '{page_am_aom2_constraint_model}#_constraint_status_enumeration',
            'class_constraint_status' => '{class_aom2_constraint_status}',
        ], 'global-vars.yml', $this->logger);
    }

    #[Test]
    public function anEntryWithTheClassAnchorIsNotReported(): void
    {
        $this->classMap->check('class_dv_date_time', '_dv_date_time_class', 'openehr_rm_1.2.0');

        self::assertSame([], $this->logger->recordsAt(LogLevel::WARNING));
    }

    #[Test]
    public function anEntryThatIsAnotherClassAttributeIsFollowed(): void
    {
        $this->classMap->check('class_constraint_status', '_constraint_status_enumeration', 'openehr_am_2.4.0');

        self::assertSame([], $this->logger->recordsAt(LogLevel::WARNING));
    }

    #[Test]
    public function aMissingEntryIsReported(): void
    {
        $this->classMap->check('class_p_c_object', '_p_c_object_class', 'openehr_am_2.4.0');

        $warnings = $this->logger->recordsAt(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertStringContainsString('has no entry', $warnings[0]['message']);
        self::assertSame('openehr_am_2.4.0', $warnings[0]['context']['schema']);
    }

    /**
     * An enumeration's partial carries an `_enumeration` anchor, so a `_class` target misses it.
     */
    #[Test]
    public function anEntryWithAnotherAnchorIsReported(): void
    {
        $this->classMap->check('class_proportion_kind', '_proportion_kind_enumeration', 'openehr_rm_1.2.0');

        $warnings = $this->logger->recordsAt(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertSame('{page_rm_data_types_quantity}#_proportion_kind_class', $warnings[0]['context']['target']);
    }

    #[Test]
    public function anEntryWithoutAnchorIsReported(): void
    {
        $this->classMap->check('class_message_content', '_message_content_class', 'openehr_rm_1.2.0');

        self::assertCount(1, $this->logger->recordsAt(LogLevel::WARNING));
    }

    #[Test]
    public function eachAttributeIsReportedOnce(): void
    {
        $this->classMap->check('class_p_c_object', '_p_c_object_class', 'openehr_am_2.4.0');
        $this->classMap->check('class_p_c_object', '_p_c_object_class', 'openehr_am_2.4.0');

        self::assertCount(1, $this->logger->recordsAt(LogLevel::WARNING));
    }

    #[Test]
    public function fromFileReadsOnlyTheClassAttributes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'global-vars');
        self::assertIsString($path);
        file_put_contents($path, <<<'YAML'
            page_rm_ehr: "RM:ehr:ehr_package.adoc"
            class_ehr: "{page_rm_ehr}#_ehr_class"
            YAML);
        try {
            $classMap = AntoraClassMap::fromFile($path, $this->logger);
            $classMap->check('class_ehr', '_ehr_class', 'openehr_rm_1.2.0');
            $classMap->check('page_rm_ehr', '_ehr_class', 'openehr_rm_1.2.0');
        } finally {
            unlink($path);
        }

        $warnings = $this->logger->recordsAt(LogLevel::WARNING);
        self::assertCount(1, $warnings);
        self::assertSame('page_rm_ehr', $warnings[0]['context']['attribute']);
    }
}
