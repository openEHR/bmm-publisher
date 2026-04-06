<?php

namespace OpenEHR\BmmPublisher\Reader;

use Cadasto\OpenEHR\BMM\Helper\Collection;
use OpenEHR\BmmPublisher\Helper\ConsoleTrait;

abstract class AbstractReader
{
    use ConsoleTrait;

    public function __construct(
        public readonly Collection $files = new Collection(),
    ) {
    }

    abstract public function read(string $filename): void;
}
