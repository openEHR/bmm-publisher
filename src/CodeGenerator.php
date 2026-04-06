<?php

namespace OpenEHR\BmmPublisher;

use OpenEHR\BmmPublisher\Reader\AbstractReader;
use OpenEHR\BmmPublisher\Writer\AbstractWriter;

class CodeGenerator
{

    /** @var AbstractWriter[] */
    protected array $writers = [];

    public function __construct(
        protected readonly AbstractReader $reader,
    )
    {
    }

    public function addWriter(AbstractWriter $writer): void
    {
        $writer->setReader($this->reader);
        $writer->assureOutputDir();
        $this->writers[] = $writer;
    }

    public function generate(): void
    {
        foreach ($this->writers as $writer) {
            $writer->write();
        }
    }

}
