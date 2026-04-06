<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/../src', __DIR__ . '/../bin', __DIR__])
    ->withPreparedSets(deadCode: true, codeQuality: true);
