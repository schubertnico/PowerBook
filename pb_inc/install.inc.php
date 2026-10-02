<?php

/**
 * PowerBook - PHP Guestbook System
 * Installer (Logik und Seiten zu install.php, seit 3.1)
 *
 * Ablauf: Willkommen (Voraussetzungen) → 1 Datenbank → 2 Gästebuch →
 * 3 Administrator (legt Tabellen, Konfiguration, Administrator,
 * pb_inc/mysql.inc.php und install.lock an) → 4 Fertig (install.php löschen).
 *
 * Jeder Schritt ist ein POST-Formular mit eigenem Token; nach dem Absenden
 * folgt eine Weiterleitung. Nichts Zerstörendes per GET. Gesperrt ist der
 * Installer, sobald install.lock (oder .installed aus 3.0) existiert oder
 * pb_inc/mysql.inc.php zu einer Datenbank mit Administrator gehört.
 *
 * Gibt es pb_inc/mysql.inc.php schon (von Hand nach der Vorlage angelegt,
 * weil der Installer sie nicht schreiben durfte), aber noch keinen
 * Administrator, nutzt der Installer die Zugangsdaten aus der Datei.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/setup.inc.php';

if (!defined('PB_INSTALL_STEPS')) {
    define('PB_INSTALL_STEPS', [1 => 'Datenbank', 2 => 'Gästebuch', 3 => 'Administrator', 4 => 'Fertig']);
    define('PB_INSTALL_CSRF', 'pb_install_csrf');
    define('PB_INSTALL_SELF', 'install.php');
}

/**
 * Einstieg aus install.php.
 */
function pb_install_main(string $root): void
{
    pb_setup_timezone();
    pb_setup_start_session();
    pb_setup_send(pb_install_handle(pb_install_paths($root)));
}

/**
 * Pfade des Installers. Tests setzen eigene über die Konstanten
 * PB_INSTALL_LOCK_FILE und PB_INSTALL_CONFIG_FILE oder rufen
 * pb_install_handle() direkt mit eigenen Pfaden auf.
 *
 * @return array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>}
 */
function pb_install_paths(string $root): array
{
    return [
        'lock' => defined('PB_INSTALL_LOCK_FILE') ? (string) constant('PB_INSTALL_LOCK_FILE') : $root . '/install.lock',
        'installed' => $root . '/.installed',
        'config' => defined('PB_INSTALL_CONFIG_FILE') ? (string) constant('PB_INSTALL_CONFIG_FILE') : $root . '/pb_inc/mysql.inc.php',
        'schema' => $root . '/powerbook.sql',
        'logs' => $root . '/logs',
        'delete' => [$root . '/install.php', $root . '/pb_inc/install.inc.php', $root . '/install_deu.php'],
    ];
}

/**
 * Verarbeitet einen Aufruf und liefert die Antwort.
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_handle(array $paths): array
{
    if (!is_array($_SESSION['pb_install'] ?? null)) {
        $_SESSION['pb_install'] = [];
    }
    $step = (int) ($_GET['step'] ?? 0);
    $isPost = pb_setup_is_post();

    $blocked = pb_install_guard($paths, $step, $isPost);
    if ($blocked !== null) {
        return $blocked;
    }

    $manual = pb_install_done() || !is_file($paths['config']) ? null : pb_install_manual($paths['config']);
    if (isset($manual['response'])) {
        return $manual['response'];
    }

    return pb_install_dispatch($step, $paths, $manual, $isPost);
}

/**
 * Ruft den Schritt auf.
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 * @param array{db: array{host: string, port: int, database: string, user: string, password: string}, names: array<string, string>}|null $manual
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_dispatch(int $step, array $paths, ?array $manual, bool $isPost): array
{
    return match ($step) {
        1 => pb_install_step_database($paths, $manual, $isPost),
        2 => pb_install_step_guestbook($isPost),
        3 => pb_install_step_admin($paths, $isPost),
        4 => pb_install_step_done($paths, $isPost),
        default => pb_install_welcome($paths, $manual),
    };
}

/**
 * Hat diese Sitzung die Installation gerade abgeschlossen?
 */
function pb_install_done(): bool
{
    return ($_SESSION['pb_install']['done'] ?? false) === true;
}

/**
 * Token, Sperrdatei und abgeschlossene Installation prüfen.
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 *
 * @return array{status: int, headers: array<string, string>, body: string}|null Antwort, wenn der Aufruf hier endet
 */
function pb_install_guard(array $paths, int $step, bool $isPost): ?array
{
    if ($isPost && !pb_setup_csrf_ok(PB_INSTALL_CSRF)) {
        return pb_install_blocked(403, 'Sicherheitsfehler', '<p>Das Formular ist abgelaufen oder ungültig.</p>'
            . '<a id="pbInstallReload" class="btn btn-primary" href="' . PB_INSTALL_SELF . '">Installer neu laden</a>');
    }

    // Gesperrt: install.lock bzw. .installed (außer Schritt 4 direkt nach der eigenen Installation)
    $lockName = pb_install_lock_name($paths);
    if ($lockName !== '' && !(pb_install_done() && $step === 4)) {
        return pb_install_blocked(403, 'PowerBook ist bereits installiert', '<p>Der Installer ist durch die Datei <code>' . $lockName . '</code> gesperrt.</p>'
            . '<p class="mb-0">Eine bestehende Installation aktualisieren Sie mit <a href="update.php">update.php</a>. '
            . 'Für eine Neuinstallation löschen Sie <code>' . $lockName . '</code> und <code>pb_inc/mysql.inc.php</code>.</p>');
    }

    // Nach der Installation gibt es in dieser Sitzung nur noch Schritt 4.
    if (pb_install_done() && $step !== 4) {
        return pb_install_redirect(4);
    }

    return null;
}

/**
 * Name der vorhandenen Sperrdatei oder ''.
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 */
function pb_install_lock_name(array $paths): string
{
    if (is_file($paths['lock'])) {
        return 'install.lock';
    }

    return is_file($paths['installed']) ? '.installed' : '';
}

/**
 * Prüft eine schon vorhandene pb_inc/mysql.inc.php.
 *
 * @return array{response?: array{status: int, headers: array<string, string>, body: string}, db: array{host: string, port: int, database: string, user: string, password: string}, names: array<string, string>}
 */
function pb_install_manual(string $configFile): array
{
    $config = pb_setup_load_config($configFile);

    try {
        $pdo = pb_setup_connect($config['db']);
        $hasAdmin = pb_setup_existing_tables($pdo, [$config['names']['pb_admins']]) !== []
            && pb_setup_count_rows($pdo, $config['names']['pb_admins']) > 0;
    } catch (PDOException $e) {
        pb_db_log('install.php: ' . $e->getMessage());

        return $config + ['response' => pb_install_blocked(500, 'Keine Verbindung zur Datenbank', '<p>Die Datei <code>pb_inc/mysql.inc.php</code> ist vorhanden, '
            . 'aber mit ihren Angaben ließ sich keine Verbindung zur Datenbank herstellen. Prüfen Sie die Werte in dieser Datei.</p>'
            . '<p class="mb-0">Möchten Sie die Zugangsdaten lieber im Installer eingeben, löschen Sie die Datei und laden Sie diese Seite neu.</p>')];
    }
    if ($hasAdmin) {
        return $config + ['response' => pb_install_blocked(403, 'PowerBook ist bereits eingerichtet', '<p>Hier gibt es schon die Datei <code>pb_inc/mysql.inc.php</code>, '
            . 'und die Datenbank enthält eine PowerBook-Installation.</p>'
            . '<p class="mb-0">Eine bestehende Installation aktualisieren Sie mit <a href="update.php">update.php</a>. '
            . 'Für eine Neuinstallation löschen Sie zuerst <code>pb_inc/mysql.inc.php</code>.</p>')];
    }

    return $config;
}

// =============================================================================
// Seiten
// =============================================================================

/**
 * Seite eines Schritts mit Schrittliste.
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_render(int $step, string $title, string $heading, string $content): array
{
    $page = pb_setup_page($title . ' – PowerBook-Installer', pb_setup_card($heading, $content), [
        'brand' => 'PowerBook-Installer',
        'version' => true,
        'aside' => pb_setup_steps('pbInstallSteps', PB_INSTALL_STEPS, $step),
    ]);

    return pb_setup_response($page, 200, pb_install_headers());
}

/**
 * Seite für gesperrte Zustände (bereits installiert, Sicherheitsfehler).
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_blocked(int $status, string $heading, string $html): array
{
    $page = pb_setup_page($heading . ' – PowerBook-Installer', pb_setup_card($heading, $html), [
        'brand' => 'PowerBook-Installer',
        'version' => true,
    ]);

    return pb_setup_response($page, $status, pb_install_headers());
}

/**
 * @return array<string, string>
 */
function pb_install_headers(): array
{
    return ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex'];
}

/**
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_redirect(int $step): array
{
    return pb_setup_redirect(PB_INSTALL_SELF . '?step=' . $step);
}

/**
 * Knöpfe „Weiter“ bzw. „PowerBook installieren“ und „Zurück“.
 */
function pb_install_buttons(string $next, string $back): string
{
    return '<div class="d-flex flex-wrap gap-2">'
        . '<button id="pbInstallNext" type="submit" class="btn btn-primary">' . $next . '</button>'
        . '<a id="pbInstallBack" class="btn btn-outline-secondary" href="' . $back . '">Zurück</a>'
        . '</div>';
}

/**
 * Willkommen mit Prüfung der Voraussetzungen.
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 * @param array<string, mixed>|null $manual
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_welcome(array $paths, ?array $manual): array
{
    [$list, $blocked] = pb_install_checks_html(pb_install_checks($paths, $manual !== null));
    $hint = $blocked
        ? pb_install_blocked_hint($manual === null && !pb_setup_writable($paths['config']))
        : '<a id="pbInstallNext" class="btn btn-primary" href="' . PB_INSTALL_SELF . '?step=1">Installation starten &raquo;</a>';

    $content = '<p>In drei Schritten richten Sie PowerBook ein: Datenbank, Gästebuch und Administrator. '
        . 'Halten Sie die Zugangsdaten Ihrer MySQL-Datenbank vom Hoster bereit.</p>'
        . '<h2 class="h6 text-uppercase text-body-secondary mt-4 mb-2">Voraussetzungen</h2>'
        . '<ul id="pbInstallChecks" class="list-group mb-4">' . $list . '</ul>'
        . $hint
        . '<p class="form-text mt-4 mb-0">Sie aktualisieren eine bestehende Installation? Dann rufen Sie stattdessen <a href="update.php">update.php</a> auf.</p>';

    return pb_install_render(0, 'Willkommen', 'Willkommen beim PowerBook-Installer', $content);
}

/**
 * Voraussetzungen: Beschriftung, erfüllt?, Pflicht?
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 *
 * @return list<array{0: string, 1: bool, 2: bool}>
 */
function pb_install_checks(array $paths, bool $manual): array
{
    return [
        ['PHP 8.4 oder neuer (installiert: ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.4.0', '>='), true],
        ['PHP-Erweiterung pdo_mysql', extension_loaded('pdo_mysql'), true],
        ['PHP-Erweiterung mbstring', extension_loaded('mbstring'), true],
        $manual
            ? ['Zugangsdaten in pb_inc/mysql.inc.php vorhanden', true, true]
            : ['Schreibrecht für pb_inc/ (für die Datei mysql.inc.php)', pb_setup_writable($paths['config']), true],
        ['Schreibrecht im PowerBook-Ordner (für die Sperrdatei install.lock)', pb_setup_writable($paths['lock']), true],
        ['Schreibrecht für logs/ (Fehlerprotokoll, empfohlen)', is_dir($paths['logs']) && is_writable($paths['logs']), false],
        ['Datei powerbook.sql vorhanden', is_file($paths['schema']), true],
    ];
}

/**
 * @param list<array{0: string, 1: bool, 2: bool}> $checks
 *
 * @return array{0: string, 1: bool} Listeneinträge, fehlt etwas Nötiges?
 */
function pb_install_checks_html(array $checks): array
{
    $list = '';
    $blocked = false;
    foreach ($checks as [$label, $ok, $required]) {
        $blocked = $blocked || (!$ok && $required);
        $badge = '<span class="badge text-bg-success">in Ordnung</span>';
        if (!$ok) {
            $badge = '<span class="badge ' . ($required ? 'text-bg-danger' : 'text-bg-warning') . '">fehlt</span>';
        }
        $list .= '<li class="list-group-item d-flex justify-content-between align-items-center gap-3">'
            . '<span>' . e($label) . '</span>' . $badge . '</li>';
    }

    return [$list, $blocked];
}

/**
 * Roter Hinweis und „Erneut prüfen“, wenn eine Voraussetzung fehlt.
 */
function pb_install_blocked_hint(bool $configNotWritable): string
{
    return '<div class="alert alert-danger" role="alert">Bitte beheben Sie zuerst die rot markierten Punkte. '
        . 'Schreibrechte setzen Sie per FTP (meist chmod 755, bei manchen Hostern 775); PHP-Version und Erweiterungen stellen Sie beim Hoster ein.'
        . ($configNotWritable ? ' ' . pb_install_manual_hint() : '') . '</div>'
        . '<a id="pbInstallRecheck" class="btn btn-outline-secondary" href="' . PB_INSTALL_SELF . '">Erneut prüfen</a>';
}

/**
 * Anleitung, falls PowerBook pb_inc/mysql.inc.php nicht schreiben darf (ohne Passwort im Browser).
 */
function pb_install_manual_hint(): string
{
    return 'Sie können die Zugangsdaten auch selbst eintragen: Speichern Sie die Vorlage <code>pb_inc/mysql.inc.php.example</code> '
        . 'als <code>pb_inc/mysql.inc.php</code>, tragen Sie die Werte vom Hoster ein, laden Sie die Datei per FTP hoch und rufen Sie den Installer erneut auf.';
}

// =============================================================================
// Schritt 1: Datenbank
// =============================================================================

/**
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 * @param array{db: array{host: string, port: int, database: string, user: string, password: string}, names: array<string, string>}|null $manual
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_step_database(array $paths, ?array $manual, bool $isPost): array
{
    $result = ['values' => pb_install_database_values(), 'errors' => [], 'existing' => []];
    if ($isPost) {
        $result = pb_install_database_post($paths, $manual, $result['values']);
        if ($result['errors'] === []) {
            return pb_install_redirect(2);
        }
    }

    return pb_install_database_page($manual, $result['values'], $result['errors'], $result['existing']);
}

/**
 * Vorbelegung der Felder (nach „Zurück“ aus der Sitzung, nie das Passwort).
 *
 * @return array<string, string>
 */
function pb_install_database_values(): array
{
    $saved = $_SESSION['pb_install']['db'] ?? null;
    $saved = is_array($saved) ? $saved : [];
    $defaults = ['host' => 'localhost', 'port' => '3306', 'database' => '', 'user' => ''];
    $values = [];
    foreach ($defaults as $key => $default) {
        $values['mysql_' . $key] = (string) ($saved[$key] ?? $default);
    }

    return $values;
}

/**
 * Seite von Schritt 1.
 *
 * @param array{db: array{host: string, port: int, database: string, user: string, password: string}, names: array<string, string>}|null $manual
 * @param array<string, string> $values
 * @param list<string> $errors
 * @param list<string> $existing
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_database_page(?array $manual, array $values, array $errors, array $existing): array
{
    $intro = $manual === null
        ? 'Tragen Sie die Zugangsdaten ein, die Sie beim Hoster für Ihre MySQL-Datenbank bekommen haben.'
        : 'Die Zugangsdaten stehen bereits in der Datei <code>pb_inc/mysql.inc.php</code>. Der Installer verwendet sie und ändert die Datei nicht.';
    $overwriteBox = $existing !== []
        ? pb_setup_checkbox('overwrite', 'Vorhandene PowerBook-Tabellen löschen und neu anlegen (alle Einträge darin gehen verloren)', false)
        : '';

    $content = '<p class="mb-4">' . $intro . '</p>'
        . pb_setup_errors($errors)
        . '<form action="' . PB_INSTALL_SELF . '?step=1" method="post" novalidate>'
        . pb_setup_csrf_field(PB_INSTALL_CSRF)
        . ($manual === null ? pb_install_database_fields($values) : pb_install_manual_info($manual['db']))
        . $overwriteBox
        . pb_install_buttons('Weiter &raquo;', PB_INSTALL_SELF)
        . '</form>';

    return pb_install_render(1, 'Datenbank', 'Schritt 1 von 4: Datenbank', $content);
}

/**
 * Prüft das abgeschickte Formular von Schritt 1 und merkt sich die Zugangsdaten.
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 * @param array{db: array{host: string, port: int, database: string, user: string, password: string}, names: array<string, string>}|null $manual
 * @param array<string, string> $values
 *
 * @return array{values: array<string, string>, errors: list<string>, existing: list<string>}
 */
function pb_install_database_post(array $paths, ?array $manual, array $values): array
{
    $errors = [];
    if ($manual === null) {
        foreach (array_keys($values) as $key) {
            $values[$key] = pb_setup_post($key);
        }
        $errors = pb_install_validate_database($values);
        $db = [
            'host' => $values['mysql_host'],
            'port' => (int) $values['mysql_port'],
            'database' => $values['mysql_database'],
            'user' => $values['mysql_user'],
            'password' => pb_setup_post_raw('mysql_password'),
        ];
        $names = pb_setup_table_names();
    } else {
        $db = $manual['db'];
        $names = $manual['names'];
    }

    $existing = [];
    if ($errors === []) {
        [$errors, $existing] = pb_install_check_database($db, $names);
    }
    if ($errors === []) {
        $errors = pb_install_database_conflicts($paths, $manual === null, $existing);
    }
    if ($errors === []) {
        $_SESSION['pb_install']['db'] = ($manual === null ? $db : ['manual' => true]) + ['overwrite' => $existing !== []];
    }

    return ['values' => $values, 'errors' => $errors, 'existing' => $existing];
}

/**
 * Vorhandene Tabellen ohne Haken, keine Schreibrechte für mysql.inc.php.
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 * @param list<string> $existing
 *
 * @return list<string> Meldungen (HTML-sicher)
 */
function pb_install_database_conflicts(array $paths, bool $writesConfig, array $existing): array
{
    if ($existing !== [] && ($_POST['overwrite'] ?? '') !== '1') {
        return ['In dieser Datenbank gibt es schon PowerBook-Tabellen (' . e(implode(', ', $existing)) . '). '
            . 'Wollen Sie eine bestehende Installation aktualisieren, nehmen Sie <a class="alert-link" href="update.php">update.php</a>. '
            . 'Für eine Neuinstallation haken Sie unten an, dass die vorhandenen Tabellen gelöscht werden dürfen'
            . ($writesConfig ? ', und geben Sie das Passwort erneut ein.' : '.')];
    }
    if ($writesConfig && !pb_setup_writable($paths['config'])) {
        return ['Die Datenbank passt, aber PowerBook darf die Datei <code>pb_inc/mysql.inc.php</code> nicht anlegen. '
            . 'Geben Sie dem Ordner <code>pb_inc/</code> per FTP Schreibrechte (meist chmod 755, bei manchen Hostern 775) und klicken Sie erneut auf „Weiter“. '
            . pb_install_manual_hint()];
    }

    return [];
}

/**
 * @param array<string, string> $values
 *
 * @return list<string> Meldungen (HTML-sicher)
 */
function pb_install_validate_database(array $values): array
{
    $errors = [];
    $hostProblem = pb_install_validate_host($values['mysql_host']);
    if ($hostProblem !== null) {
        $errors[] = $hostProblem;
    }
    $port = $values['mysql_port'];
    if (preg_match('/^\d{1,5}$/', $port) !== 1 || (int) $port < 1 || (int) $port > 65535) {
        $errors[] = 'Der Port muss eine Zahl zwischen 1 und 65535 sein (fast immer 3306).';
    }
    $databaseProblem = pb_install_validate_database_name($values['mysql_database']);
    if ($databaseProblem !== null) {
        $errors[] = $databaseProblem;
    }
    if ($values['mysql_user'] === '') {
        $errors[] = 'Bitte geben Sie den Benutzernamen der Datenbank an.';
    }

    return $errors;
}

/**
 * @return string|null Meldung oder null
 */
function pb_install_validate_database_name(string $database): ?string
{
    if ($database === '') {
        return 'Bitte geben Sie den Namen der Datenbank an.';
    }
    if (str_contains($database, ';') || mb_strlen($database) > 64) {
        return 'Der Datenbankname darf kein Semikolon enthalten und höchstens 64 Zeichen lang sein.';
    }

    return null;
}

/**
 * @return string|null Meldung oder null
 */
function pb_install_validate_host(string $host): ?string
{
    if ($host === '') {
        return 'Bitte geben Sie den Datenbankserver an.';
    }
    if (preg_match('/[\s;=]/', $host) === 1 || mb_strlen($host) > 200) {
        return 'Der Datenbankserver darf keine Leerzeichen, Semikolons oder Gleichheitszeichen enthalten.';
    }

    return null;
}

/**
 * Verbindung, Serverversion, Recht zum Anlegen von Tabellen und vorhandene Tabellen.
 *
 * @param array{host: string, port: int, database: string, user: string, password: string} $db
 * @param array<string, string> $names
 *
 * @return array{0: list<string>, 1: list<string>} Meldungen (HTML-sicher) und vorhandene Tabellen
 */
function pb_install_check_database(array $db, array $names): array
{
    try {
        $pdo = pb_setup_connect($db);
    } catch (PDOException $e) {
        pb_db_log('install.php: ' . $e->getMessage());

        return [[e(pb_setup_connect_error($e, $db['host'], $db['database']))], []];
    }
    $problem = pb_setup_server_problem($pdo);
    if ($problem !== null) {
        return [[e($problem)], []];
    }

    try {
        $pdo->exec('DROP TABLE IF EXISTS pb_install_check');
        $pdo->exec('CREATE TABLE pb_install_check (id INT NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB');
        $pdo->exec('DROP TABLE pb_install_check');
    } catch (PDOException $e) {
        pb_db_log('install.php: ' . $e->getMessage());

        return [['Die Verbindung klappt, aber der Benutzer darf in der Datenbank keine Tabellen anlegen. '
            . 'Geben Sie ihm beim Hoster alle Rechte für diese Datenbank.'], []];
    }

    return [[], pb_setup_existing_tables($pdo, array_values($names))];
}

/**
 * @param array<string, string> $values
 */
function pb_install_database_fields(array $values): string
{
    return pb_setup_field('mysql_host', 'Datenbankserver', 'text', $values['mysql_host'], 'Beim Hoster oft nicht <code>localhost</code>, sondern ein eigener Servername.', 'maxlength="200" required')
        . pb_setup_field('mysql_port', 'Port', 'number', $values['mysql_port'], 'Fast immer 3306.', 'min="1" max="65535" required')
        . pb_setup_field('mysql_database', 'Datenbankname', 'text', $values['mysql_database'], '', 'maxlength="64" required')
        . pb_setup_field('mysql_user', 'Benutzername', 'text', $values['mysql_user'], '', 'maxlength="200" required autocomplete="off"')
        . pb_setup_field('mysql_password', 'Passwort', 'password', '', '', 'maxlength="200" autocomplete="new-password"');
}

/**
 * @param array{host: string, port: int, database: string, user: string, password: string} $db
 */
function pb_install_manual_info(array $db): string
{
    $rows = '';
    foreach (['Datenbankserver' => $db['host'], 'Port' => (string) $db['port'], 'Datenbankname' => $db['database'], 'Benutzername' => $db['user']] as $label => $value) {
        $rows .= '<dt class="col-sm-4">' . e($label) . '</dt><dd class="col-sm-8">' . e($value) . '</dd>';
    }

    return '<dl id="pbInstallManual" class="row mb-4">' . $rows . '</dl>';
}

// =============================================================================
// Schritt 2: Gästebuch
// =============================================================================

/**
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_step_guestbook(bool $isPost): array
{
    if (!is_array($_SESSION['pb_install']['db'] ?? null)) {
        return pb_install_redirect(1);
    }
    $saved = is_array($_SESSION['pb_install']['guestbook'] ?? null) ? $_SESSION['pb_install']['guestbook'] : [];
    $values = [
        'gb_title' => (string) ($saved['title'] ?? 'Gästebuch'),
        'gb_url' => (string) ($saved['url'] ?? pb_setup_base_url()),
        'gb_email' => (string) ($saved['email'] ?? ''),
    ];
    $notify = (bool) ($saved['notify'] ?? true);
    $release = (bool) ($saved['release'] ?? true);
    $errors = [];

    if ($isPost) {
        foreach (array_keys($values) as $key) {
            $values[$key] = pb_setup_post($key);
        }
        $values['gb_url'] = pb_install_normalize_url($values['gb_url']);
        $notify = ($_POST['gb_notify'] ?? '') === '1';
        $release = ($_POST['gb_release'] ?? '') === '1';
        $errors = pb_install_validate_guestbook($values);
        if ($errors === []) {
            $_SESSION['pb_install']['guestbook'] = [
                'title' => $values['gb_title'],
                'url' => $values['gb_url'],
                'email' => $values['gb_email'],
                'notify' => $notify,
                'release' => $release,
            ];

            return pb_install_redirect(3);
        }
    }

    $content = '<p class="mb-4">Wie heißt Ihr Gästebuch, und wer soll über neue Einträge Bescheid bekommen? '
        . 'Alles lässt sich später im AdminCenter unter „Konfiguration“ ändern.</p>'
        . pb_setup_errors($errors)
        . '<form action="' . PB_INSTALL_SELF . '?step=2" method="post" novalidate>'
        . pb_setup_csrf_field(PB_INSTALL_CSRF)
        . pb_setup_field('gb_title', 'Name des Gästebuchs', 'text', $values['gb_title'], 'Erscheint als Überschrift und als Absendername der Mails.', 'maxlength="150" required')
        . pb_setup_field('gb_url', 'Adresse des Gästebuchs', 'url', $values['gb_url'], 'Vorgeschlagen aus der aufgerufenen Adresse. Daraus entsteht der Link zum AdminCenter in Mails.', 'maxlength="230" required')
        . pb_setup_field('gb_email', 'E-Mail-Adresse für Benachrichtigungen', 'email', $values['gb_email'], '', 'maxlength="250" required autocomplete="email"')
        . '<div class="row mb-3"><div class="col-sm-8 offset-sm-4">'
        . pb_setup_checkbox('gb_notify', 'Bei jedem neuen Eintrag eine E-Mail an diese Adresse schicken', $notify)
        . pb_setup_checkbox('gb_release', 'Neue Einträge erst nach Freischaltung im AdminCenter zeigen (empfohlen gegen Werbung)', $release)
        . '</div></div>'
        . pb_install_buttons('Weiter &raquo;', PB_INSTALL_SELF . '?step=1')
        . '</form>';

    return pb_install_render(2, 'Gästebuch', 'Schritt 2 von 4: Gästebuch', $content);
}

/**
 * Adresse des Ordners: ohne Dateinamen, mit / am Ende.
 */
function pb_install_normalize_url(string $url): string
{
    if ($url === '') {
        return '';
    }
    $url = (string) preg_replace('~[?#].*$~', '', $url);
    $url = (string) preg_replace('~/[^/]+\.(php|html?)$~i', '/', $url);

    return rtrim($url, '/') . '/';
}

/**
 * @param array<string, string> $values
 *
 * @return list<string> Meldungen (HTML-sicher)
 */
function pb_install_validate_guestbook(array $values): array
{
    $errors = [];
    if ($values['gb_title'] === '') {
        $errors[] = 'Bitte geben Sie den Namen des Gästebuchs an.';
    } elseif (mb_strlen($values['gb_title']) > 150) {
        $errors[] = 'Der Name des Gästebuchs darf höchstens 150 Zeichen lang sein.';
    }
    if (!pb_setup_valid_url($values['gb_url'])) {
        $errors[] = 'Bitte geben Sie die Adresse des Gästebuchs vollständig an, zum Beispiel https://www.example.org/.';
    } elseif (mb_strlen($values['gb_url']) > 230) {
        $errors[] = 'Die Adresse des Gästebuchs darf höchstens 230 Zeichen lang sein.';
    }
    if (filter_var($values['gb_email'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($values['gb_email']) > 250) {
        $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse für Benachrichtigungen an.';
    }

    return $errors;
}

// =============================================================================
// Schritt 3: Administrator und Installation
// =============================================================================

/**
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_step_admin(array $paths, bool $isPost): array
{
    if (!is_array($_SESSION['pb_install']['db'] ?? null)) {
        return pb_install_redirect(1);
    }
    if (!is_array($_SESSION['pb_install']['guestbook'] ?? null)) {
        return pb_install_redirect(2);
    }
    $values = ['admin_name' => '', 'admin_email' => (string) ($_SESSION['pb_install']['guestbook']['email'] ?? '')];
    $errors = [];

    if ($isPost) {
        $values = ['admin_name' => pb_setup_post('admin_name'), 'admin_email' => pb_setup_post('admin_email')];
        // Passwörter werden nicht getrimmt: gespeichert wird genau die Eingabe.
        $password = pb_setup_post_raw('admin_password');
        $errors = pb_install_validate_admin($values, $password, pb_setup_post_raw('admin_password2'));

        if ($errors === []) {
            $result = pb_install_run($paths, $_SESSION['pb_install'], [
                'name' => $values['admin_name'],
                'email' => $values['admin_email'],
                'password' => $password,
            ]);
            if ($result['error'] === null) {
                pb_setup_regenerate_session();
                $_SESSION['pb_install'] = ['done' => true, 'admin' => $values['admin_name'], 'lockFailed' => $result['lockFailed']];

                return pb_install_redirect(4);
            }
            $errors[] = $result['error'];
        }
    }

    $content = '<p class="mb-4">Mit diesem Konto melden Sie sich im AdminCenter an. Es hat alle Rechte und lässt sich nicht löschen (Superadmin).</p>'
        . pb_setup_errors($errors)
        . '<form action="' . PB_INSTALL_SELF . '?step=3" method="post" novalidate>'
        . pb_setup_csrf_field(PB_INSTALL_CSRF)
        . pb_setup_field('admin_name', 'Name (zum Anmelden)', 'text', $values['admin_name'], '', 'maxlength="100" required autocomplete="username"')
        . pb_setup_field('admin_email', 'E-Mail-Adresse', 'email', $values['admin_email'], 'Für „Passwort vergessen“.', 'maxlength="250" required autocomplete="email"')
        . pb_setup_field('admin_password', 'Passwort', 'password', '', 'Mindestens 8 Zeichen.', 'maxlength="100" required autocomplete="new-password"')
        . pb_setup_field('admin_password2', 'Passwort wiederholen', 'password', '', '', 'maxlength="100" required autocomplete="new-password"')
        . pb_install_buttons('PowerBook installieren', PB_INSTALL_SELF . '?step=2')
        . '</form>';

    return pb_install_render(3, 'Administrator', 'Schritt 3 von 4: Administrator', $content);
}

/**
 * @param array<string, string> $values
 *
 * @return list<string> Meldungen (HTML-sicher)
 */
function pb_install_validate_admin(array $values, string $password, string $password2): array
{
    $errors = [];
    if ($values['admin_name'] === '') {
        $errors[] = 'Bitte geben Sie einen Namen zum Anmelden an.';
    } elseif (mb_strlen($values['admin_name']) > 100) {
        $errors[] = 'Der Name darf höchstens 100 Zeichen lang sein.';
    }
    if (filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($values['admin_email']) > 250) {
        $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif (mb_strlen($password) > 100) {
        $errors[] = 'Das Passwort darf höchstens 100 Zeichen lang sein.';
    } elseif ($password !== $password2) {
        $errors[] = 'Die beiden Passwörter stimmen nicht überein.';
    }

    return $errors;
}

/**
 * Führt die Installation aus: Tabellen, Konfiguration, Administrator,
 * pb_inc/mysql.inc.php und install.lock.
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 * @param array<string, mixed> $state Sitzungsdaten der Schritte 1 und 2
 * @param array{name: string, email: string, password: string} $admin
 *
 * @return array{error: string|null, lockFailed: bool} Fehlermeldung (HTML-sicher) oder null
 */
function pb_install_run(array $paths, array $state, array $admin): array
{
    /** @var array<string, mixed> $dbState */
    $dbState = $state['db'];
    /** @var array{title: string, url: string, email: string, notify: bool, release: bool} $guestbook */
    $guestbook = $state['guestbook'];
    $manual = ($dbState['manual'] ?? false) === true;

    $target = pb_install_target($paths, $dbState, $manual);
    if ($target['error'] !== null) {
        return ['error' => $target['error'], 'lockFailed' => false];
    }
    $problem = pb_install_create_tables($target['db'], $target['schema'], $target['names'], ($dbState['overwrite'] ?? false) === true, $guestbook, $admin);
    if ($problem === null && !$manual && !pb_setup_write_file($paths['config'], pb_setup_config_content($target['db'], $target['names']))) {
        $problem = 'Die Tabellen und Ihr Administrator sind angelegt, aber die Datei <code>pb_inc/mysql.inc.php</code> konnte nicht geschrieben werden. '
            . pb_install_manual_hint() . ' Rufen Sie danach <a class="alert-link" href="update.php">update.php</a> auf; es legt die Sperrdatei <code>install.lock</code> an.';
    }
    if ($problem !== null) {
        return ['error' => $problem, 'lockFailed' => false];
    }
    $lockText = 'PowerBook ' . PB_VERSION . ' installiert am ' . date('c') . "\n";

    return ['error' => null, 'lockFailed' => !pb_setup_write_file($paths['lock'], $lockText)];
}

/**
 * Was die Installation braucht: Schreibrecht, Schema, Zugangsdaten und Tabellennamen.
 *
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 * @param array<string, mixed> $dbState
 *
 * @return array{error: string|null, schema: string, db: array{host: string, port: int, database: string, user: string, password: string}, names: array<string, string>}
 */
function pb_install_target(array $paths, array $dbState, bool $manual): array
{
    $target = ['error' => null, 'schema' => '', 'db' => ['host' => '', 'port' => 3306, 'database' => '', 'user' => '', 'password' => ''], 'names' => pb_setup_table_names()];
    if (!$manual && !pb_setup_writable($paths['config'])) {
        return ['error' => 'PowerBook darf die Datei <code>pb_inc/mysql.inc.php</code> nicht anlegen. Geben Sie dem Ordner <code>pb_inc/</code> per FTP Schreibrechte '
            . '(meist chmod 755, bei manchen Hostern 775) und klicken Sie erneut auf „PowerBook installieren“.'] + $target;
    }
    $schema = is_file($paths['schema']) ? file_get_contents($paths['schema']) : false;
    if ($schema === false) {
        return ['error' => 'Die Datei <code>powerbook.sql</code> konnte nicht gelesen werden. Bitte laden Sie PowerBook vollständig hoch.'] + $target;
    }
    $target['schema'] = $schema;
    if ($manual) {
        $config = pb_setup_load_config($paths['config']);
        $target['db'] = $config['db'];
        $target['names'] = $config['names'];

        return $target;
    }
    $target['db'] = [
        'host' => (string) ($dbState['host'] ?? ''),
        'port' => (int) ($dbState['port'] ?? 3306),
        'database' => (string) ($dbState['database'] ?? ''),
        'user' => (string) ($dbState['user'] ?? ''),
        'password' => (string) ($dbState['password'] ?? ''),
    ];

    return $target;
}

/**
 * Verbindet, prüft vorhandene Tabellen, legt die Tabellen aus powerbook.sql an
 * und trägt Konfiguration und Administrator ein. Scheitert ein Schritt, werden
 * die gerade angelegten Tabellen wieder entfernt.
 *
 * @param array{host: string, port: int, database: string, user: string, password: string} $db
 * @param array<string, string> $names
 * @param array{title: string, url: string, email: string, notify: bool, release: bool} $guestbook
 * @param array{name: string, email: string, password: string} $admin
 *
 * @return string|null Fehlermeldung (HTML-sicher) oder null
 */
function pb_install_create_tables(array $db, string $schema, array $names, bool $overwrite, array $guestbook, array $admin): ?string
{
    try {
        $pdo = pb_setup_connect($db);
    } catch (PDOException $e) {
        pb_db_log('install.php: ' . $e->getMessage());

        return e(pb_setup_connect_error($e, $db['host'], $db['database']));
    }
    if (!$overwrite && pb_setup_existing_tables($pdo, array_values($names)) !== []) {
        return 'In der Datenbank gibt es inzwischen PowerBook-Tabellen. Gehen Sie zurück zu <a class="alert-link" href="' . PB_INSTALL_SELF . '?step=1">Schritt 1</a> '
            . 'und bestätigen Sie, dass sie gelöscht werden dürfen.';
    }

    try {
        foreach (pb_setup_schema_statements($schema, $names) as $statement) {
            $pdo->exec($statement);
        }
        $pdo->prepare("UPDATE `{$names['pb_config']}` SET title = ?, admin_url = ?, email = ?, send_email = ?, `release` = ? WHERE id = 1")->execute([
            $guestbook['title'],
            $guestbook['url'] . 'pb_inc/admincenter/',
            $guestbook['email'],
            $guestbook['notify'] ? 'Y' : 'N',
            $guestbook['release'] ? 'U' : 'R',
        ]);
        $pdo->prepare("INSERT INTO `{$names['pb_admins']}` (id, name, email, password, config, `release`, entries, admins, pw_changed)"
            . " VALUES (1, ?, ?, ?, 'Y', 'Y', 'Y', 'Y', ?)")
            ->execute([$admin['name'], $admin['email'], password_hash($admin['password'], PASSWORD_DEFAULT), time()]);
    } catch (PDOException $e) {
        pb_db_log('install.php: ' . $e->getMessage());
        pb_install_drop_tables($pdo, $names);

        return 'Die Tabellen konnten nicht angelegt werden. Die Datenbank meldet: ' . e($e->getMessage());
    }

    return null;
}

/**
 * Entfernt die Tabellen nach einem Fehlschlag (nach bestem Bemühen).
 *
 * @param array<string, string> $names
 */
function pb_install_drop_tables(PDO $pdo, array $names): void
{
    foreach (array_reverse(array_values($names)) as $table) {
        try {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        } catch (PDOException) {
            // weiter mit der nächsten Tabelle
        }
    }
}

// =============================================================================
// Schritt 4: Fertig
// =============================================================================

/**
 * @param array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} $paths
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_install_step_done(array $paths, bool $isPost): array
{
    if (!pb_install_done()) {
        return pb_install_redirect(1);
    }
    $deleteResult = '';
    $deleted = false;
    if ($isPost && ($_POST['action'] ?? '') === 'delete') {
        [$deleted, $deleteResult] = pb_install_delete_files($paths['delete']);
    }

    $lockWarning = ($_SESSION['pb_install']['lockFailed'] ?? false) === true
        ? '<div id="pbInstallLockFailed" class="alert alert-danger" role="alert"><strong>Fast fertig:</strong> Die Datei <code>install.lock</code> konnte nicht angelegt werden. '
            . 'Löschen Sie <code>install.php</code> jetzt per FTP, sonst bleibt der Installer erreichbar.</div>'
        : '';

    $content = '<div class="alert alert-success" role="alert">Tabellen, Konfiguration und Administrator sind angelegt. '
        . 'Melden Sie sich im AdminCenter mit dem Namen <strong>' . e((string) ($_SESSION['pb_install']['admin'] ?? '')) . '</strong> und Ihrem Passwort an.</div>'
        . $lockWarning
        . $deleteResult
        . ($deleted ? '' : pb_install_delete_box())
        . '<div class="d-flex flex-wrap gap-2 mt-4">'
        . '<a id="pbInstallAdmin" class="btn btn-primary" href="pb_inc/admincenter/">Zum AdminCenter &raquo;</a>'
        . '<a id="pbInstallSite" class="btn btn-outline-secondary" href="pbook.php">Zum Gästebuch</a>'
        . '</div>';

    return pb_install_render(4, 'Fertig', 'Fertig: PowerBook ist installiert', $content);
}

/**
 * Kasten „install.php jetzt löschen“.
 */
function pb_install_delete_box(): string
{
    return '<div class="alert alert-warning border-2 p-4" role="alert">'
        . '<h2 class="h5 alert-heading">install.php jetzt löschen</h2>'
        . '<p>Die Datei <code>install.php</code> wird nicht mehr gebraucht. Die Datei <code>install.lock</code> sperrt sie zwar, '
        . 'aber sicher ist nur eine gelöschte Datei. Der Knopf versucht, sie für Sie zu löschen.</p>'
        . '<form action="' . PB_INSTALL_SELF . '?step=4" method="post" class="mb-0">'
        . pb_setup_csrf_field(PB_INSTALL_CSRF)
        . '<input type="hidden" name="action" value="delete">'
        . '<button id="pbInstallDelete" type="submit" class="btn btn-danger btn-lg">install.php jetzt löschen</button>'
        . '</form></div>';
}

/**
 * Löscht install.php samt Logik und dem Platzhalter install_deu.php.
 *
 * @param list<string> $files erste Datei: install.php
 *
 * @return array{0: bool, 1: string} gelöscht?, Meldung (HTML)
 */
function pb_install_delete_files(array $files): array
{
    $left = pb_setup_delete_files($files);
    $main = $files[0] ?? '';
    if ($main !== '' && is_file($main)) {
        return [false, '<div id="pbInstallDeleteFailed" class="alert alert-danger" role="alert"><strong>install.php konnte nicht gelöscht werden</strong> – '
            . 'PowerBook hat dafür keine Schreibrechte. Löschen Sie die Datei bitte per FTP. Bis dahin sperrt <code>install.lock</code> den Installer.</div>'];
    }
    $rest = $left !== []
        ? ' Folgende Dateien ließen sich nicht löschen; sie sind ohne install.php wirkungslos, löschen Sie sie bei Gelegenheit per FTP: '
            . e(implode(', ', $left)) . '.'
        : '';

    return [true, '<div id="pbInstallDeleted" class="alert alert-success" role="alert"><strong>install.php wurde gelöscht.</strong> '
        . 'Der Installer ist damit vom Server verschwunden.' . $rest . '</div>'];
}
