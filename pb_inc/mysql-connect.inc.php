<?php

/**
 * PowerBook - PHP Guestbook System
 * Datenbankverbindung für Gästebuch und AdminCenter
 *
 * Fehlt pb_inc/mysql.inc.php (frisch hochgeladen, noch nicht installiert),
 * erscheint die Seite „PowerBook ist noch nicht eingerichtet“ mit Link zum
 * Installer. Scheitert die Verbindung, erscheint eine neutrale Seite; die
 * Meldung des Servers steht nur in logs/error.log.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/database.inc.php';

// Ist $pdo schon gesetzt (z. B. in der Testumgebung), wird nichts verbunden.
if (!isset($pdo) || !$pdo instanceof PDO) {
    $pbConfigFile = __DIR__ . '/mysql.inc.php';
    if (!is_file($pbConfigFile)) {
        require_once __DIR__ . '/setup.inc.php';
        pb_setup_send(pb_setup_not_installed_response());

        exit;
    }

    require_once $pbConfigFile;

    try {
        $pdo = getDatabase();
    } catch (RuntimeException) {
        require_once __DIR__ . '/setup.inc.php';
        pb_setup_send(pb_setup_unavailable_response());

        exit;
    }
}
