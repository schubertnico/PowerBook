<?php

/**
 * PowerBook - PHP Guestbook System
 * Gästebuch (öffentliche Seite)
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// pbook.php/irgendwas: relative Pfade (CSS, Bilder) würden ins Leere zeigen.
$pbPathInfo = $_SERVER['PATH_INFO'] ?? '';
if (is_string($pbPathInfo) && $pbPathInfo !== '' && PHP_SAPI !== 'cli') {
    header('Location: ' . (string) ($_SERVER['SCRIPT_NAME'] ?? '/pbook.php'), true, 301);
    exit;
}

// guestbook.inc.php lehnt Aufrufe ohne diese Konstante ab.
if (!defined('PB_ENTRY')) {
    define('PB_ENTRY', true);
}

require_once __DIR__ . '/pb_inc/config.inc.php';
require_once __DIR__ . '/pb_inc/layout.inc.php';

// Inhalt zuerst erzeugen: Nach dem Eintragen leitet guestbook.inc.php um,
// bevor etwas ausgegeben ist; außerdem setzt es den Seitentitel.
ob_start();
include __DIR__ . '/pb_inc/guestbook.inc.php';
$pbContent = (string) ob_get_clean();

$pbSiteTitle = trim((string) ($config_title ?? '')) !== '' ? (string) $config_title : 'Gästebuch';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    // Skripte nur von hier und vom Bootstrap-CDN – auch wenn ein Design oder
    // ein Eintrag doch einmal Markup durchschmuggeln sollte.
    header("Content-Security-Policy: script-src 'self' https://cdn.jsdelivr.net; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
}

pb_layout_header(isset($pbPageTitle) && is_string($pbPageTitle) && $pbPageTitle !== '' ? $pbPageTitle : $pbSiteTitle, [
    'showNav' => true,
    'adminLink' => 'pb_inc/admincenter/',
    'siteName' => $pbSiteTitle,
    'homeLink' => trim((string) ($config_guestbook_name ?? '')) !== '' ? (string) $config_guestbook_name : 'pbook.php',
    'brandHeading' => true,
]);

echo $pbContent;

pb_layout_footer();
