<?php

/**
 * PowerBook - PHP Guestbook System
 * Ausgabe eines Eintrags
 *
 * Wird von guestbook.inc.php für jeden Eintrag eingebunden ($entry mit den
 * Rohwerten aus pb_entries). Die Arbeit macht pb_render_entry().
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.inc.php';

echo pb_render_entry(is_array($entry ?? null) ? $entry : [], [
    'design' => $config_design ?? '',
    'date' => $config_date ?? 'd.m.Y',
    'time' => $config_time ?? 'H:i',
    'icons' => $config_icons ?? 'N',
    'smilies' => $config_smilies ?? 'N',
    'text_format' => $config_text_format ?? 'N',
    'statements' => $config_statements ?? 'N',
    'smiley_base' => 'pb_inc/smilies/',
]);
