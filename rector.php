<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/pbook.php',
        __DIR__ . '/pb_inc',
        __DIR__ . '/install_deu.php',
    ])
    ->withSkip([
        __DIR__ . '/pb_inc/smilies',
        __DIR__ . '/vendor',
    ])
    ->withPhpSets(php84: true)
    ->withSets([
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
    ])
    ->withImportNames(importShortClasses: false)
    ->withTypeCoverageLevel(0);
