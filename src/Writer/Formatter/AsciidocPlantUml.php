<?php

namespace OpenEHR\BmmPublisher\Writer\Formatter;

use Cadasto\OpenEHR\BMM\Model\AbstractBmmClass;
use Cadasto\OpenEHR\BMM\Model\BmmPackage;
use Cadasto\OpenEHR\BMM\Model\BmmSchema;

readonly class AsciidocPlantUml
{
    private PlantUml $plantUml;

    public function __construct()
    {
        $this->plantUml = new PlantUml();
    }

    public function format(AbstractBmmClass|BmmPackage $bmmItem, string $prefix, BmmSchema $schema): string
    {
        $content = $this->plantUml->format($bmmItem, $prefix, $schema);
        // notice: for UML with hyperlinks need "opts=inline", thus : [plantuml,$bmmItem->name,format=svg,opts=inline]
        return <<<ASCIIDOC
[plantuml,{$bmmItem->getName()},format=svg]
-----
$content
-----
ASCIIDOC;
    }
}
