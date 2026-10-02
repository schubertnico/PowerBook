<?php

/**
 * PowerBook - PHP Guestbook System
 * Aktualisierung einer bestehenden Installation (Einstieg)
 *
 * Bringt die Datenbank von PowerBook 1.x, 2.0 oder 3.0 auf den Stand der
 * hochgeladenen Version. Die Logik liegt in pb_inc/update.inc.php (per
 * .htaccess gesperrt). Gestartet wird nach Anmeldung mit einem Konto, das
 * die Konfiguration ändern darf.
 *
 * Diese Datei verwendet bewusst keine neue PHP-Syntax: Auch ältere
 * PHP-Versionen können sie lesen und zeigen eine verständliche Meldung,
 * statt mit einem Syntaxfehler abzubrechen.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

$pbRequiredPhp = '8.4.0';

if (version_compare(PHP_VERSION, $pbRequiredPhp, '<')) {
    header('Content-Type: text/html; charset=UTF-8', true, 500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex">'
        . '<title>PHP-Version zu alt – PowerBook-Aktualisierung</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem">'
        . '<h1>PHP-Version zu alt</h1>'
        . '<p>PowerBook benötigt PHP ' . htmlspecialchars($pbRequiredPhp, ENT_QUOTES, 'UTF-8')
        . ' oder neuer. Auf diesem Server läuft PHP ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '.</p>'
        . '<p>Bei den meisten Hostern lässt sich die PHP-Version im Kundenmenü umstellen. '
        . 'Stellen Sie sie um, bevor Sie die Aktualisierung starten.</p>'
        . '</body></html>';
    exit;
}

if (!is_file(__DIR__ . '/pb_inc/update.inc.php') || !is_file(__DIR__ . '/pb_inc/setup.inc.php')) {
    header('Content-Type: text/html; charset=UTF-8', true, 500);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="robots" content="noindex">'
        . '<title>Aktualisierung unvollständig – PowerBook</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem">'
        . '<h1>Aktualisierung unvollständig</h1>'
        . '<p>Es fehlen Dateien im Ordner <code>pb_inc/</code>. Bitte laden Sie alle Dateien von PowerBook erneut hoch.</p>'
        . '</body></html>';
    exit;
}

require_once __DIR__ . '/pb_inc/update.inc.php';

pb_update_main(__DIR__);
