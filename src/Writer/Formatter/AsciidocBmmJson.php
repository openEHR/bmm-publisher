<?php

namespace OpenEHR\BmmPublisher\Writer\Formatter;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;

readonly class AsciidocBmmJson
{
    public function format(AbstractBmmClass $class): string
    {
        $data = $class->jsonSerialize();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return <<<ASCIIDOC
[source,json]
--------
{$json}
--------
ASCIIDOC;
    }
}
