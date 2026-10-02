<?php

/**
 * PowerBook - PHP Guestbook System
 * Aktualisierung einer bestehenden Installation (Logik zu update.php, seit 3.1)
 *
 * Bringt Datenbanken von PowerBook 1.x, 2.0 und 3.0 auf den Stand 3.1:
 * fehlende Tabellen, Spalten, Indizes und Vorgabewerte aus powerbook.sql,
 * Rechte PERMITTED/FORBIDDEN → Y/N, MyISAM → InnoDB, Primärschlüssel für
 * pb_config, Zahlen- und Zeitspalten, Adresse des AdminCenters, Sperrdatei
 * install.lock und das Entfernen des alten Installers install_deu.php.
 *
 * Jede Aufgabe ist für sich wiederholbar; nach einem vollständigen Lauf ist
 * der Plan leer. Einträge, Admins und Einstellungen bleiben erhalten (auch die
 * alte ICQ-Spalte). Gestartet wird nach Anmeldung mit einem Konto, das die
 * Konfiguration ändern darf.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/setup.inc.php';

if (!defined('PB_UPDATE_CSRF')) {
    define('PB_UPDATE_CSRF', 'pb_update_csrf');
    define('PB_UPDATE_SELF', 'update.php');

    /** Kurze Erklärung für die Anzeige im Plan. */
    define('PB_UPDATE_PURPOSES', [
        'pb_entries' => 'die Einträge',
        'pb_login_attempts' => 'Schutz vor dem Durchprobieren von Passwörtern',
        'pb_admins.password:widen' => 'für sichere Passwort-Hashes',
        'pb_admins.reset_token' => 'für „Passwort vergessen“',
        'pb_admins.reset_token_expires' => 'für „Passwort vergessen“',
        'pb_admins.reset_token_expires:number' => 'Zeitpunkte nach dem 19.01.2038',
        'pb_admins.pw_changed' => 'Abmelden anderer Sitzungen nach einem Passwortwechsel',
        'pb_config.title' => 'Name des Gästebuchs',
        'pb_config.mail_from' => 'Absenderadresse für Mails',
        'pb_config.spam_check:number' => 'Zahl statt Text',
        'pb_config.show_entries:number' => 'Zahl statt Text',
        'pb_entries.ip:widen' => 'IPv6-Adressen',
        'pb_entries.homepage:widen' => 'lange Adressen',
        'pb_entries.date:number' => 'Datum nach dem 19.01.2038',
        'key' => 'schnellere Abfragen',
    ]);

    /**
     * Unveränderte Standarddesigns früherer Versionen (verglichen ohne Leerraum).
     * Schlüssel: Art der Ersetzung.
     */
    define('PB_UPDATE_OLD_DESIGNS', [
        'time' => '<article class="card pb-entry-card shadow-sm"><header class="card-header d-flex flex-wrap justify-content-between align-items-center">'
            . '<span>(#ICON#)<b>(#DATE#)</b>, <small class="text-body-secondary">(#TIME#)h</small></span><span>(#EMAIL_NAME#)</span></header>'
            . '<div class="card-body">(#TEXT#)</div><footer class="card-footer d-flex flex-wrap justify-content-end gap-3 align-items-center text-end">'
            . '<span>(#URL#)</span></footer></article>',
        'table' => "<table width=\"560\" border=\"0\">\n<tr bgcolor=\"#001329\"><td align=\"left\">\n(#ICON#)<b>(#DATE#)</b>, <small>(#TIME#)h</small>\n"
            . "</td><td align=\"right\" width=\"121\">\n(#EMAIL_NAME#)\n</td></tr><tr><td valign=\"top\" bgcolor=\"#001930\">\n(#TEXT#)\n"
            . "</td><td width=\"121\" align=\"right\" valign=\"top\" bgcolor=\"#001329\">\n(#URL#)<br>\n(#ICQ#)\n</td></tr></table><br>",
    ]);

    /** Unveränderte Danke-Mails früherer Versionen (1.21 englisch, 2.0, 3.0). */
    define('PB_UPDATE_OLD_THANKS', [
        "Hello (#NAME#)!\n\nThank you for your entry in my guestbook!\n\nGreetings\nThe Admin",
        "Hallo (#NAME#)!\n\nVielen Dank für Ihren Eintrag in meinem Gästebuch!\n\nMit freundlichen Grüßen\nDer Admin",
        "Hallo (#NAME#)!\n\nVielen Dank für Ihren Eintrag in meinem Gästebuch!\n\nMit freundlichen Gruessen\nDer Admin",
    ]);
    define('PB_UPDATE_OLD_THANKS_TITLES', ['Thank you for your entry!']);
}

/**
 * Einstieg aus update.php.
 */
function pb_update_main(string $root): void
{
    pb_setup_timezone();
    pb_setup_start_session();
    pb_setup_send(pb_update_entry($root));
}

/**
 * Pfade der Aktualisierung. Tests rufen pb_update_handle() mit eigenen Pfaden auf.
 *
 * @return array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>}
 */
function pb_update_paths(string $root): array
{
    return [
        'config' => $root . '/pb_inc/mysql.inc.php',
        'lock' => defined('PB_UPDATE_LOCK_FILE') ? (string) constant('PB_UPDATE_LOCK_FILE') : $root . '/install.lock',
        'schema' => $root . '/powerbook.sql',
        'installDeu' => $root . '/install_deu.php',
        'delete' => [
            $root . '/update.php',
            $root . '/pb_inc/update.inc.php',
            $root . '/install.php',
            $root . '/pb_inc/install.inc.php',
            $root . '/install_deu.php',
        ],
    ];
}

/**
 * Liest pb_inc/mysql.inc.php, verbindet und verarbeitet den Aufruf.
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_update_entry(string $root): array
{
    $paths = pb_update_paths($root);
    if (!is_file($paths['config'])) {
        return pb_update_render('Keine Installation gefunden', '<p class="mb-0">Es gibt keine Datei <code>pb_inc/mysql.inc.php</code>. '
            . 'Für eine neue Installation rufen Sie <a href="install.php">install.php</a> auf.</p>');
    }
    $config = pb_setup_load_config($paths['config']);

    try {
        $pdo = pb_setup_connect($config['db']);
    } catch (PDOException $e) {
        pb_db_log('update.php: ' . $e->getMessage());

        return pb_update_render('Keine Verbindung zur Datenbank', '<p class="mb-0">Mit den Angaben aus <code>pb_inc/mysql.inc.php</code> ließ sich keine Verbindung herstellen. '
            . 'Prüfen Sie Datenbankserver, Port, Benutzername, Passwort und Datenbankname in dieser Datei.</p>', 500);
    }

    return pb_update_handle($pdo, $config['names'], $paths);
}

/**
 * Seite der Aktualisierung.
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_update_render(string $heading, string $content, int $status = 200): array
{
    $page = pb_setup_page($heading . ' – PowerBook-Aktualisierung', pb_setup_card($heading, $content), [
        'brand' => 'PowerBook-Aktualisierung',
        'version' => true,
    ]);

    return pb_setup_response($page, $status, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
}

/**
 * Zielstand als „3.1“.
 */
function pb_update_target(): string
{
    return implode('.', array_slice(explode('.', PB_VERSION), 0, 2));
}

// =============================================================================
// Ablauf
// =============================================================================

/**
 * Verarbeitet einen Aufruf von update.php.
 *
 * @param array<string, string> $names Tabellennamen (Standardname => tatsächlicher Name)
 * @param array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} $paths
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_update_handle(PDO $pdo, array $names, array $paths): array
{
    $heading = 'PowerBook auf Version ' . pb_update_target() . ' aktualisieren';
    $schemaSql = is_file($paths['schema']) ? (string) file_get_contents($paths['schema']) : '';
    if ($schemaSql === '') {
        return pb_update_render($heading, '<div class="alert alert-danger" role="alert">Die Datei <code>powerbook.sql</code> fehlt. '
            . 'Bitte laden Sie alle Dateien von PowerBook ' . e(pb_update_target()) . ' hoch.</div>', 500);
    }
    $isPost = pb_setup_is_post();
    if ($isPost && !pb_setup_csrf_ok(PB_UPDATE_CSRF)) {
        return pb_update_render($heading, '<div class="alert alert-danger" role="alert">Das Formular ist abgelaufen oder ungültig. '
            . '<a class="alert-link" href="' . PB_UPDATE_SELF . '">Seite neu laden</a></div>', 403);
    }

    $plan = pb_update_plan($pdo, $schemaSql, $names, $paths, pb_setup_base_url());
    if ($plan['error'] !== null) {
        return pb_update_render($heading, '<div class="alert alert-danger" role="alert">' . $plan['error'] . '</div>');
    }
    $action = $isPost && is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    return match ($action) {
        'delete' => pb_update_action_delete($heading, $plan, $paths),
        'start' => pb_update_action_start($pdo, $names, $paths, $schemaSql, $plan),
        default => pb_update_page_overview($heading, $plan, $paths),
    };
}

/**
 * Hat sich in dieser Sitzung ein berechtigtes Konto angemeldet?
 */
function pb_update_authenticated(): bool
{
    return ($_SESSION['pb_update_auth'] ?? false) === true;
}

/**
 * Plan mit Anmeldung oder „bereits auf dem Stand“.
 *
 * @param array{error: string|null, tasks: list<array{label: string, run: Closure(PDO): void}>, notes: list<string>} $plan
 * @param array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} $paths
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_update_page_overview(string $heading, array $plan, array $paths): array
{
    return $plan['tasks'] === []
        ? pb_update_page_current($heading, $plan, $paths)
        : pb_update_page_plan($heading, $plan, [], pb_update_authenticated(), '');
}

/**
 * Knopf „update.php jetzt löschen“ – erst nach der Aktualisierung.
 *
 * @param array{error: string|null, tasks: list<array{label: string, run: Closure(PDO): void}>, notes: list<string>} $plan
 * @param array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} $paths
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_update_action_delete(string $heading, array $plan, array $paths): array
{
    if (!pb_update_authenticated() && $plan['tasks'] !== []) {
        return pb_update_page_plan($heading, $plan, ['Bitte führen Sie zuerst die Aktualisierung durch.'], false, '');
    }

    return pb_update_render($heading, pb_update_delete_files($paths['delete']) . pb_update_links());
}

/**
 * „Aktualisierung starten“: anmelden, Aufgaben ausführen, Plan neu berechnen.
 *
 * @param array<string, string> $names
 * @param array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} $paths
 * @param array{error: string|null, tasks: list<array{label: string, run: Closure(PDO): void}>, notes: list<string>} $plan
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_update_action_start(PDO $pdo, array $names, array $paths, string $schemaSql, array $plan): array
{
    $heading = 'PowerBook auf Version ' . pb_update_target() . ' aktualisieren';
    if ($plan['tasks'] === []) {
        return pb_update_page_current($heading, $plan, $paths);
    }
    if (!pb_update_authenticated()) {
        if (!pb_update_verify_admin($pdo, $names['pb_admins'], pb_setup_post('update_name'), pb_setup_post_raw('update_password'))) {
            usleep(300000);

            return pb_update_page_plan($heading, $plan, ['Anmeldung fehlgeschlagen: Name oder Passwort stimmt nicht, oder das Konto darf die Konfiguration nicht ändern.'], false, '');
        }
        pb_setup_regenerate_session();
        $_SESSION['pb_update_auth'] = true;
    }

    [$log, $failure] = pb_update_run($pdo, $plan['tasks']);
    $plan = pb_update_plan($pdo, $schemaSql, $names, $paths, pb_setup_base_url());
    $logHtml = '';
    foreach ($log as $entry) {
        $logHtml .= '<li class="list-group-item"><span class="text-success fw-bold me-2" aria-hidden="true">✓</span>' . e($entry) . '</li>';
    }
    if ($failure === null && $plan['error'] === null && $plan['tasks'] === []) {
        return pb_update_page_done($plan, $logHtml, $paths);
    }

    return pb_update_page_plan($heading, $plan, [e($failure ?? 'Nicht alle Schritte ließen sich ausführen.')
        . ' Bereits erledigte Schritte bleiben erhalten; Sie können update.php nach dem Beheben erneut aufrufen.'], true, $logHtml);
}

/**
 * Führt die Aufgaben nacheinander aus; beim ersten Fehler ist Schluss.
 *
 * @param list<array{label: string, run: Closure(PDO): void}> $tasks
 *
 * @return array{0: list<string>, 1: string|null} erledigte Aufgaben, Fehlermeldung (Klartext)
 */
function pb_update_run(PDO $pdo, array $tasks): array
{
    $log = [];
    foreach ($tasks as $task) {
        try {
            ($task['run'])($pdo);
            $log[] = $task['label'];
        } catch (Throwable $e) {
            pb_db_log('update.php: ' . $task['label'] . ': ' . $e->getMessage());

            return [$log, 'Bei „' . $task['label'] . '“ ist ein Fehler aufgetreten: ' . $e->getMessage()];
        }
    }

    return [$log, null];
}

/**
 * Prüft die Anmeldung: Konto mit dem Recht, die Konfiguration zu ändern
 * (Y, in PowerBook 1.x PERMITTED); Passwort als Hash oder Base64 (1.x).
 */
function pb_update_verify_admin(PDO $pdo, string $table, string $login, string $password): bool
{
    if ($login === '' || $password === '') {
        return false;
    }
    $stmt = $pdo->prepare('SELECT password, config FROM `' . $table . '` WHERE name = ? OR email = ?');
    $stmt->execute([$login, $login]);
    $ok = false;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stored = (string) $row['password'];
        $matches = str_starts_with($stored, '$')
            ? password_verify($password, $stored)
            : $stored !== '' && hash_equals($stored, base64_encode($password));
        if ($matches && in_array(strtoupper((string) $row['config']), ['Y', 'PERMITTED'], true)) {
            $ok = true;
        }
    }

    return $ok;
}

// =============================================================================
// Seiten
// =============================================================================

/**
 * @param array{error: string|null, tasks: list<array{label: string, run: Closure(PDO): void}>, notes: list<string>} $plan
 * @param list<string> $errors Meldungen (HTML-sicher)
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_update_page_plan(string $heading, array $plan, array $errors, bool $authenticated, string $logHtml): array
{
    $planHtml = '';
    foreach ($plan['tasks'] as $task) {
        $planHtml .= '<li class="list-group-item">' . e($task['label']) . '</li>';
    }
    $login = $authenticated
        ? '<p>Sie sind als Administrator bestätigt.</p>'
        : '<h2 class="h6 text-uppercase text-body-secondary mt-4 mb-2">Als Administrator bestätigen</h2>'
            . '<p>Melden Sie sich mit einem Konto an, das die Konfiguration ändern darf.</p>'
            . pb_setup_field('update_name', 'Name oder E-Mail-Adresse', 'text', pb_setup_post('update_name'), '', 'maxlength="250" required autocomplete="username"')
            . pb_setup_field('update_password', 'Passwort', 'password', '', '', 'maxlength="100" required autocomplete="current-password"');

    $content = pb_setup_errors($errors)
        . ($logHtml !== '' ? '<h2 class="h6 text-uppercase text-body-secondary mb-2">Bereits erledigt</h2><ul id="pbUpdateLog" class="list-group mb-4">' . $logHtml . '</ul>' : '')
        . '<p>update.php bringt die Datenbank einer bestehenden Installation (PowerBook 1.x, 2.0 oder 3.0) auf den Stand ' . e(pb_update_target()) . '. '
        . 'Ihre Einträge, Admins und Einstellungen bleiben erhalten: Es werden nur fehlende Tabellen, Spalten und Einstellungen ergänzt und veraltete angepasst.</p>'
        . '<h2 class="h6 text-uppercase text-body-secondary mt-4 mb-2">Das wird erledigt</h2>'
        . '<ol id="pbUpdatePlan" class="list-group list-group-numbered mb-3">' . $planHtml . '</ol>'
        . pb_update_notes($plan['notes'])
        . '<div class="alert alert-info" role="alert">Legen Sie vorher eine Sicherung der Datenbank an, zum Beispiel in phpMyAdmin über „Exportieren“.</div>'
        . '<form action="' . PB_UPDATE_SELF . '" method="post" novalidate>'
        . pb_setup_csrf_field(PB_UPDATE_CSRF)
        . '<input type="hidden" name="action" value="start">'
        . $login
        . '<button id="pbUpdateStart" type="submit" class="btn btn-primary">Aktualisierung starten</button>'
        . '</form>';

    return pb_update_render($heading, $content);
}

/**
 * @param array{error: string|null, tasks: list<array{label: string, run: Closure(PDO): void}>, notes: list<string>} $plan
 * @param array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} $paths
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_update_page_current(string $heading, array $plan, array $paths): array
{
    return pb_update_render($heading, '<div id="pbUpdateCurrent" class="alert alert-success" role="alert">Die Datenbank ist bereits auf dem Stand ' . e(pb_update_target()) . '.</div>'
        . pb_update_notes($plan['notes'])
        . pb_update_delete_box($paths)
        . pb_update_links());
}

/**
 * @param array{error: string|null, tasks: list<array{label: string, run: Closure(PDO): void}>, notes: list<string>} $plan
 * @param array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} $paths
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_update_page_done(array $plan, string $logHtml, array $paths): array
{
    return pb_update_render('Aktualisierung abgeschlossen', '<div id="pbUpdateDone" class="alert alert-success" role="alert">Die Datenbank ist jetzt auf dem Stand ' . e(pb_update_target()) . '.</div>'
        . '<h2 class="h6 text-uppercase text-body-secondary mt-4 mb-2">Erledigt</h2>'
        . '<ul id="pbUpdateLog" class="list-group mb-3">' . $logHtml . '</ul>'
        . pb_update_notes($plan['notes'])
        . pb_update_delete_box($paths)
        . pb_update_links());
}

/**
 * @param list<string> $notes Klartext
 */
function pb_update_notes(array $notes): string
{
    if ($notes === []) {
        return '';
    }
    $items = '';
    foreach ($notes as $note) {
        $items .= '<li>' . e($note) . '</li>';
    }

    return '<div id="pbUpdateNotes" class="form-text mb-3"><strong>Hinweise:</strong><ul class="mb-0 ps-3">' . $items . '</ul></div>';
}

/**
 * Kasten „update.php jetzt löschen“.
 *
 * @param array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} $paths
 */
function pb_update_delete_box(array $paths): string
{
    $installer = false;
    foreach ($paths['delete'] as $file) {
        $installer = $installer || basename($file) === 'install.php' && is_file($file);
    }

    return '<div class="alert alert-warning border-2 p-4 mt-4" role="alert">'
        . '<h2 class="h5 alert-heading">update.php jetzt löschen</h2>'
        . '<p>Die Datei <code>update.php</code> wird nicht mehr gebraucht. Löschen Sie sie, damit sie niemand später aufruft. '
        . 'Der Knopf versucht, sie für Sie zu löschen'
        . ($installer ? ' – zusammen mit dem Installer <code>install.php</code>, den <code>install.lock</code> ohnehin sperrt.' : '.')
        . '</p>'
        . '<form action="' . PB_UPDATE_SELF . '" method="post" class="mb-0">'
        . pb_setup_csrf_field(PB_UPDATE_CSRF)
        . '<input type="hidden" name="action" value="delete">'
        . '<button id="pbUpdateDelete" type="submit" class="btn btn-danger btn-lg">update.php jetzt löschen</button>'
        . '</form></div>';
}

/**
 * Löscht update.php, den Installer und die zugehörige Logik.
 *
 * @param list<string> $files erste Datei: update.php
 */
function pb_update_delete_files(array $files): string
{
    $left = pb_setup_delete_files($files);
    $main = $files[0] ?? '';
    if ($main !== '' && is_file($main)) {
        return '<div id="pbUpdateDeleteFailed" class="alert alert-danger" role="alert"><strong>update.php konnte nicht gelöscht werden</strong> – '
            . 'PowerBook hat dafür keine Schreibrechte. Löschen Sie die Datei bitte per FTP.</div>';
    }

    return '<div id="pbUpdateDeleted" class="alert alert-success" role="alert"><strong>update.php wurde gelöscht.</strong>'
        . ($left !== [] ? ' Diese Dateien ließen sich nicht löschen; entfernen Sie sie bitte per FTP: ' . e(implode(', ', $left)) . '.' : '')
        . '</div>';
}

/**
 * Links zum AdminCenter und zum Gästebuch.
 */
function pb_update_links(): string
{
    return '<div class="d-flex flex-wrap gap-2 mt-4">'
        . '<a id="pbUpdateAdmin" class="btn btn-primary" href="pb_inc/admincenter/">Zum AdminCenter &raquo;</a>'
        . '<a id="pbUpdateSite" class="btn btn-outline-secondary" href="pbook.php">Zum Gästebuch</a>'
        . '</div>';
}

// =============================================================================
// Plan
// =============================================================================

/**
 * Ermittelt, was für den Stand 3.1 fehlt.
 *
 * @param array<string, string> $names Tabellennamen (Standardname => tatsächlicher Name)
 * @param array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} $paths
 *
 * @return array{error: string|null, tasks: list<array{label: string, run: Closure(PDO): void}>, notes: list<string>}
 */
function pb_update_plan(PDO $pdo, string $schemaSql, array $names, array $paths, string $baseUrl): array
{
    $names += pb_setup_table_names();
    $tables = pb_update_table_status($pdo, $names);
    $error = pb_update_precondition($pdo, $names, $tables);
    if ($error !== null) {
        return ['error' => $error, 'tasks' => [], 'notes' => []];
    }

    $notes = [];
    $tasks = [
        ...pb_update_config_key_tasks($names['pb_config'], pb_update_columns($pdo, $names['pb_config'])),
        ...pb_update_table_tasks($tables),
        ...pb_update_schema_tasks($pdo, $schemaSql, $names, $tables, $baseUrl, $notes),
        ...pb_update_file_tasks($paths, $notes),
    ];

    return ['error' => null, 'tasks' => $tasks, 'notes' => [...$notes, ...pb_update_general_notes($pdo, $names, $tables)]];
}

/**
 * Vorhandene PowerBook-Tabellen mit Engine und Kollation.
 *
 * @param array<string, string> $names
 *
 * @return array<string, array{name: string, engine: string, collation: string}> nach Standardname
 */
function pb_update_table_status(PDO $pdo, array $names): array
{
    $placeholders = implode(', ', array_fill(0, count($names), '?'));
    $stmt = $pdo->prepare('SELECT table_name AS t_name, engine AS t_engine, table_collation AS t_collation FROM information_schema.tables'
        . ' WHERE table_schema = DATABASE() AND table_name IN (' . $placeholders . ')');
    $stmt->execute(array_values($names));
    $found = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $found[strtolower((string) $row['t_name'])] = [
            'engine' => strtolower((string) $row['t_engine']),
            'collation' => strtolower((string) $row['t_collation']),
        ];
    }
    $tables = [];
    foreach ($names as $std => $name) {
        if (isset($found[strtolower($name)])) {
            $tables[$std] = ['name' => $name] + $found[strtolower($name)];
        }
    }

    return $tables;
}

/**
 * Lässt sich überhaupt aktualisieren?
 *
 * @param array<string, string> $names
 * @param array<string, array{name: string, engine: string, collation: string}> $tables
 *
 * @return string|null Fehlermeldung (HTML) oder null
 */
function pb_update_precondition(PDO $pdo, array $names, array $tables): ?string
{
    if (!isset($tables['pb_admins']) && !isset($tables['pb_config'])) {
        return 'In dieser Datenbank gibt es keine PowerBook-Tabellen. Für eine neue Installation rufen Sie <a class="alert-link" href="install.php">install.php</a> auf.';
    }
    if (!isset($tables['pb_admins'], $tables['pb_config']) || pb_setup_count_rows($pdo, $names['pb_admins']) === 0) {
        return 'Die Installation in dieser Datenbank ist unvollständig: Es gibt keinen Administrator. '
            . 'Richten Sie PowerBook mit <a class="alert-link" href="install.php">install.php</a> neu ein '
            . '(der Installer bietet an, die vorhandenen Tabellen zu ersetzen).';
    }
    $columns = pb_update_columns($pdo, $names['pb_config']);
    $rows = pb_setup_count_rows($pdo, $names['pb_config']);
    if (!isset($columns['id']) && $rows > 1) {
        return 'Die Tabelle ' . e($names['pb_config']) . ' enthält ' . $rows . ' Zeilen, PowerBook nutzt aber nur eine. '
            . 'Löschen Sie die überzähligen Zeilen (zum Beispiel in phpMyAdmin) und rufen Sie update.php danach erneut auf.';
    }

    return null;
}

/**
 * Spalten einer Tabelle.
 *
 * @return array<string, array{type: string, null: bool, default: string|null, extra: string}>
 */
function pb_update_columns(PDO $pdo, string $table): array
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
    foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
        $columns[strtolower((string) $row['Field'])] = [
            'type' => strtolower((string) $row['Type']),
            'null' => strtoupper((string) $row['Null']) === 'YES',
            'default' => $row['Default'] === null ? null : (string) $row['Default'],
            'extra' => strtolower((string) ($row['Extra'] ?? '')),
        ];
    }

    return $columns;
}

/**
 * Namen der Indizes einer Tabelle (klein geschrieben).
 *
 * @return list<string>
 */
function pb_update_indexes(PDO $pdo, string $table): array
{
    $keys = [];
    $stmt = $pdo->query('SHOW INDEX FROM `' . $table . '`');
    foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
        $keys[] = strtolower((string) $row['Key_name']);
    }

    return array_values(array_unique($keys));
}

/**
 * Aufgabe aus festen SQL-Anweisungen.
 *
 * @param list<string> $statements
 *
 * @return array{label: string, run: Closure(PDO): void}
 */
function pb_update_sql_task(string $label, array $statements): array
{
    return [
        'label' => $label,
        'run' => static function (PDO $pdo) use ($statements): void {
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }
        },
    ];
}

/**
 * Aufgabe, die Werte in der Konfigurationszeile setzt.
 *
 * @param array<string, string> $values Spalte => Wert
 *
 * @return array{label: string, run: Closure(PDO): void}
 */
function pb_update_value_task(string $label, string $table, array $values): array
{
    return [
        'label' => $label,
        'run' => static function (PDO $pdo) use ($table, $values): void {
            $set = implode(', ', array_map(static fn (string $column): string => '`' . $column . '` = ?', array_keys($values)));
            $pdo->prepare('UPDATE `' . $table . '` SET ' . $set . ' WHERE id = 1')->execute(array_values($values));
        },
    ];
}

/**
 * Erklärung in Klammern für die Anzeige, z. B. „ (IPv6-Adressen)“.
 */
function pb_update_purpose(string $key): string
{
    $purpose = PB_UPDATE_PURPOSES[$key] ?? '';

    return $purpose !== '' ? ' (' . $purpose . ')' : '';
}

/**
 * pb_config bekommt die Spalte id als Primärschlüssel (U10). Muss vor allen
 * anderen Änderungen an der Tabelle laufen: Server mit sql_require_primary_key
 * lehnen jede Änderung an Tabellen ohne Primärschlüssel ab.
 *
 * @param array<string, array{type: string, null: bool, default: string|null, extra: string}> $columns
 *
 * @return list<array{label: string, run: Closure(PDO): void}>
 */
function pb_update_config_key_tasks(string $table, array $columns): array
{
    if (isset($columns['id'])) {
        return [];
    }

    return [pb_update_sql_task('Spalte ' . $table . '.id als Primärschlüssel ergänzen (verlangen manche MySQL-Server)', [
        'ALTER TABLE `' . $table . '` ADD COLUMN id TINYINT UNSIGNED NOT NULL DEFAULT 1 FIRST, ADD PRIMARY KEY (id)',
    ])];
}

/**
 * Engine (MyISAM → InnoDB) und Kollation (→ utf8mb4_unicode_ci) der Tabellen.
 *
 * @param array<string, array{name: string, engine: string, collation: string}> $tables
 *
 * @return list<array{label: string, run: Closure(PDO): void}>
 */
function pb_update_table_tasks(array $tables): array
{
    $tasks = [];
    foreach ($tables as $info) {
        if ($info['engine'] !== '' && $info['engine'] !== 'innodb') {
            $engine = ['myisam' => 'MyISAM', 'aria' => 'Aria', 'memory' => 'MEMORY'][$info['engine']] ?? $info['engine'];
            $tasks[] = pb_update_sql_task('Tabelle ' . $info['name'] . ' von ' . $engine . ' auf InnoDB umstellen (nötig für die Spam-Sperre)', [
                'ALTER TABLE `' . $info['name'] . '` ENGINE=InnoDB',
            ]);
        }
        if (str_starts_with($info['collation'], 'utf8mb4_') && $info['collation'] !== 'utf8mb4_unicode_ci') {
            $tasks[] = pb_update_sql_task('Tabelle ' . $info['name'] . ' auf die Kollation utf8mb4_unicode_ci umstellen (Sicherungen lassen sich dann auch in MariaDB einspielen)', [
                'ALTER TABLE `' . $info['name'] . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            ]);
        }
    }

    return $tasks;
}

/**
 * Fehlende Tabellen anlegen, vorhandene an powerbook.sql angleichen.
 *
 * @param array<string, string> $names
 * @param array<string, array{name: string, engine: string, collation: string}> $tables
 * @param list<string> $notes
 *
 * @return list<array{label: string, run: Closure(PDO): void}>
 */
function pb_update_schema_tasks(PDO $pdo, string $schemaSql, array $names, array $tables, string $baseUrl, array &$notes): array
{
    $tasks = [];
    foreach (pb_setup_schema_tables($schemaSql) as $std => $definition) {
        $table = $names[$std] ?? $std;
        if (!isset($tables[$std])) {
            $tasks[] = pb_update_sql_task('Tabelle ' . $table . ' anlegen' . pb_update_purpose($std), [pb_setup_rename_tables($definition['create'], $names)]);
            continue;
        }
        if ($std === 'pb_admins') {
            $tasks = [...$tasks, ...pb_update_rights_tasks($pdo, $table, $definition['columns'])];
        }
        $tasks = [...$tasks, ...pb_update_column_tasks($pdo, $std, $table, $definition, $notes)];
        if ($std === 'pb_config') {
            $tasks = [...$tasks, ...pb_update_config_tasks($pdo, $table, $schemaSql, $baseUrl, $notes)];
        }
    }

    return $tasks;
}

/**
 * Rechte aus PowerBook 1.x: PERMITTED/FORBIDDEN → Y/N (U1).
 *
 * @param array<string, string> $definitions Spaltendefinitionen aus powerbook.sql
 *
 * @return list<array{label: string, run: Closure(PDO): void}>
 */
function pb_update_rights_tasks(PDO $pdo, string $table, array $definitions): array
{
    $statements = [];
    foreach (pb_update_columns($pdo, $table) as $column => $info) {
        if (!str_contains($info['type'], "'permitted'") || !isset($definitions[$column])) {
            continue;
        }
        $statements[] = 'ALTER TABLE `' . $table . '` MODIFY `' . $column . "` ENUM('PERMITTED','FORBIDDEN','Y','N') NOT NULL DEFAULT 'N'";
        $statements[] = 'UPDATE `' . $table . '` SET `' . $column . '` = CASE `' . $column . "` WHEN 'PERMITTED' THEN 'Y' WHEN 'FORBIDDEN' THEN 'N' ELSE `" . $column . '` END';
        $statements[] = 'ALTER TABLE `' . $table . '` MODIFY ' . $definitions[$column];
    }
    if ($statements === []) {
        return [];
    }

    return [pb_update_sql_task('Rechte der Admins von PERMITTED/FORBIDDEN auf Y/N umstellen (sonst hat in PowerBook 3 niemand Rechte)', $statements)];
}

/**
 * Spalten, Typen, Vorgabewerte und Indizes einer vorhandenen Tabelle.
 *
 * @param array{create: string, columns: array<string, string>, keys: array<string, string>} $definition
 * @param list<string> $notes
 *
 * @return list<array{label: string, run: Closure(PDO): void}>
 */
function pb_update_column_tasks(PDO $pdo, string $std, string $table, array $definition, array &$notes): array
{
    $existing = pb_update_columns($pdo, $table);
    $tasks = [];
    $defaults = [];
    foreach ($definition['columns'] as $column => $sql) {
        if ($std === 'pb_config' && $column === 'id') {
            continue;
        }
        if (!isset($existing[$column])) {
            $tasks[] = pb_update_sql_task('Spalte ' . $table . '.' . $column . ' ergänzen' . pb_update_purpose($std . '.' . $column), ['ALTER TABLE `' . $table . '` ADD COLUMN ' . $sql]);
            continue;
        }
        $task = pb_update_type_task($pdo, $std . '.' . $column, $table, $sql, $existing[$column], $notes);
        if ($task !== null) {
            $tasks[] = $task;
        } elseif (pb_update_needs_default($existing[$column])) {
            $defaults[$column] = pb_update_schema_default($sql);
        }
    }
    $defaults += pb_update_legacy_defaults($existing, $definition['columns']);
    $defaultTask = pb_update_default_task($table, array_filter($defaults, static fn (?string $value): bool => $value !== null));

    return [...$tasks, ...($defaultTask !== null ? [$defaultTask] : []), ...pb_update_key_tasks($pdo, $table, $definition['keys'])];
}

/**
 * Vorgabewerte für alte Spalten, die es in powerbook.sql nicht mehr gibt (z. B. icq).
 *
 * @param array<string, array{type: string, null: bool, default: string|null, extra: string}> $existing
 * @param array<string, string> $schemaColumns
 *
 * @return array<string, string|null>
 */
function pb_update_legacy_defaults(array $existing, array $schemaColumns): array
{
    $defaults = [];
    foreach ($existing as $column => $info) {
        if (!isset($schemaColumns[$column]) && pb_update_needs_default($info)) {
            $defaults[$column] = pb_update_neutral_default($info['type']);
        }
    }

    return $defaults;
}

/**
 * Spalte ohne Vorgabewert, die beim Anlegen einer Zeile fehlen darf?
 *
 * @param array{type: string, null: bool, default: string|null, extra: string} $info
 */
function pb_update_needs_default(array $info): bool
{
    return !$info['null'] && $info['default'] === null
        && !str_contains($info['extra'], 'auto_increment') && !str_contains($info['type'], "'permitted'");
}

/**
 * Vorgabewerte in einem Rutsch nachtragen.
 *
 * @param array<string, string> $defaults Spalte => SQL-Literal
 *
 * @return array{label: string, run: Closure(PDO): void}|null
 */
function pb_update_default_task(string $table, array $defaults): ?array
{
    if ($defaults === []) {
        return null;
    }
    $statements = [];
    foreach ($defaults as $column => $value) {
        $statements[] = 'ALTER TABLE `' . $table . '` ALTER COLUMN `' . $column . '` SET DEFAULT ' . $value;
    }

    return pb_update_sql_task('Vorgabewerte ergänzen (' . $table . ': ' . implode(', ', array_keys($defaults)) . ')', $statements);
}

/**
 * Fehlende Indizes aus powerbook.sql.
 *
 * @param array<string, string> $keys Name => Definition
 *
 * @return list<array{label: string, run: Closure(PDO): void}>
 */
function pb_update_key_tasks(PDO $pdo, string $table, array $keys): array
{
    $indexes = pb_update_indexes($pdo, $table);
    $tasks = [];
    foreach ($keys as $key => $sql) {
        if (!in_array($key, $indexes, true)) {
            $tasks[] = pb_update_sql_task('Index ' . $key . ' für ' . $table . ' anlegen (' . PB_UPDATE_PURPOSES['key'] . ')', ['ALTER TABLE `' . $table . '` ADD ' . $sql]);
        }
    }

    return $tasks;
}

/**
 * Vorgabewert aus einer Spaltendefinition in powerbook.sql (SQL-Literal) oder null.
 */
function pb_update_schema_default(string $definition): ?string
{
    if (preg_match("/\\bDEFAULT\\s+('(?:[^'\\\\]|\\\\.)*'|-?\\d+)/i", $definition, $match) !== 1) {
        return null;
    }

    return $match[1];
}

/**
 * Neutraler Vorgabewert für alte Spalten, die PowerBook nicht mehr füllt (z. B. icq).
 */
function pb_update_neutral_default(string $type): ?string
{
    if (preg_match('/^(var)?char\b/', $type) === 1) {
        return "''";
    }
    if (preg_match('/^(tiny|small|medium|big)?int\b|^decimal\b|^float\b|^double\b/', $type) === 1) {
        return '0';
    }

    return null;
}

/**
 * Typänderung einer Spalte (Text → Zahl, INT → BIGINT, VARCHAR verlängern).
 *
 * @param string $key Standardname „tabelle.spalte“ für die Erklärung
 * @param array{type: string, null: bool, default: string|null, extra: string} $info
 * @param list<string> $notes
 *
 * @return array{label: string, run: Closure(PDO): void}|null
 */
function pb_update_type_task(PDO $pdo, string $key, string $table, string $sql, array $info, array &$notes): ?array
{
    $wanted = pb_update_schema_type($sql);
    $type = $info['type'];
    $label = 'Spalte ' . $table . '.' . substr($key, (int) strpos($key, '.') + 1);
    $modify = 'ALTER TABLE `' . $table . '` MODIFY ' . $sql;

    if (preg_match('/^(tiny|small|medium|big)?int\b/', $wanted) === 1 && preg_match('/^(var)?char\b/', $type) === 1) {
        return pb_update_number_task($pdo, $key, $table, $modify, $notes);
    }
    if (str_starts_with($wanted, 'bigint') && preg_match('/^(tiny|small|medium)?int\b/', $type) === 1) {
        return pb_update_sql_task($label . ' auf BIGINT erweitern' . pb_update_purpose($key . ':number'), [$modify]);
    }
    if (preg_match('/^varchar\((\d+)\)/', $wanted, $want) === 1 && preg_match('/^(?:var)?char\((\d+)\)/', $type, $have) === 1 && (int) $have[1] < (int) $want[1]) {
        return pb_update_sql_task($label . ' auf ' . $want[1] . ' Zeichen erweitern' . pb_update_purpose($key . ':widen'), [$modify]);
    }

    return null;
}

/**
 * Textspalte mit Zahlen (date, spam_check aus 1.x) auf eine Zahlenspalte umstellen –
 * nur, wenn wirklich alle Werte Ziffern sind.
 *
 * @param list<string> $notes
 *
 * @return array{label: string, run: Closure(PDO): void}|null
 */
function pb_update_number_task(PDO $pdo, string $key, string $table, string $modify, array &$notes): ?array
{
    $column = substr($key, (int) strpos($key, '.') + 1);
    $stmt = $pdo->query('SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . "` NOT REGEXP '^[0-9]*$'");
    $bad = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
    if ($bad > 0) {
        $notes[] = 'Die Spalte ' . $table . '.' . $column . ' enthält ' . $bad . ' Werte, die keine Zahlen sind. '
            . 'update.php stellt sie deshalb nicht auf Zahlen um; prüfen Sie diese Werte bitte von Hand.';

        return null;
    }

    return pb_update_sql_task('Spalte ' . $table . '.' . $column . ' von Text auf Zahl umstellen' . pb_update_purpose($key . ':number'), [
        'UPDATE `' . $table . '` SET `' . $column . "` = '0' WHERE `" . $column . "` = ''",
        $modify,
    ]);
}

/**
 * Typ aus einer Spaltendefinition in powerbook.sql, klein geschrieben (z. B. „varchar(45)“).
 */
function pb_update_schema_type(string $definition): string
{
    if (preg_match('/^`?\w+`?\s+(\w+(?:\([^)]*\))?)/', $definition, $match) !== 1) {
        return '';
    }

    return strtolower($match[1]);
}

/**
 * Inhalte von pb_config: Standardzeile, Adresse des AdminCenters, Dateiname,
 * Standarddesign und Danke-Mail früherer Versionen.
 *
 * Die Adresse des AdminCenters wird immer gefüllt: Ohne sie baut „Passwort
 * vergessen“ den Link aus dem Host-Kopf der Anfrage.
 *
 * @param list<string> $notes
 *
 * @return list<array{label: string, run: Closure(PDO): void}>
 */
function pb_update_config_tasks(PDO $pdo, string $table, string $schemaSql, string $baseUrl, array &$notes): array
{
    $standard = pb_update_standard_config($schemaSql, $table);
    $stmt = $pdo->query('SELECT * FROM `' . $table . '` LIMIT 1');
    $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    $tasks = [];
    if (!is_array($row)) {
        $tasks[] = pb_update_sql_task('Standardkonfiguration anlegen (die Tabelle ' . $table . ' war leer)', [$standard['insert']]);
        $row = ['guestbook_name' => 'pbook.php', 'design' => $standard['design'], 'thanks' => $standard['thanks'], 'thanks_title' => $standard['thanks_title']];
    }

    if (trim((string) ($row['admin_url'] ?? '')) === '' && $baseUrl !== '') {
        $tasks[] = pb_update_value_task('Adresse des AdminCenters eintragen: ' . $baseUrl . 'pb_inc/admincenter/', $table, ['admin_url' => $baseUrl . 'pb_inc/admincenter/']);
    }
    if (trim((string) ($row['guestbook_name'] ?? '')) === '') {
        $tasks[] = pb_update_value_task('Dateiname des Gästebuchs eintragen: pbook.php', $table, ['guestbook_name' => 'pbook.php']);
    }
    $design = pb_update_design_task($table, (string) ($row['design'] ?? ''), $standard['design'], $notes);
    $thanks = pb_update_thanks_task($table, $row, $standard);

    return [...$tasks, ...($design !== null ? [$design] : []), ...($thanks !== null ? [$thanks] : [])];
}

/**
 * Standardzeile von pb_config aus powerbook.sql: INSERT-Anweisung und Texte.
 *
 * @return array{insert: string, design: string, thanks_title: string, thanks: string}
 */
function pb_update_standard_config(string $schemaSql, string $table): array
{
    $insert = '';
    foreach (pb_setup_schema_statements($schemaSql, ['pb_config' => $table]) as $statement) {
        if (preg_match('/^INSERT\s+INTO\s+`?' . preg_quote($table, '/') . '`?\s/i', $statement) === 1) {
            $insert = $statement;
        }
    }
    [$design, $thanksTitle, $thanks] = array_pad(array_slice(pb_setup_sql_strings($insert), 0, 3), 3, '');

    return ['insert' => $insert, 'design' => $design, 'thanks_title' => $thanksTitle, 'thanks' => $thanks];
}

/**
 * Unverändertes Standarddesign einer früheren Version durch das neue ersetzen.
 *
 * @param list<string> $notes
 *
 * @return array{label: string, run: Closure(PDO): void}|null
 */
function pb_update_design_task(string $table, string $design, string $newDesign, array &$notes): ?array
{
    $kind = pb_update_old_design($design);
    if ($kind === null || $newDesign === '') {
        if (str_contains($design, '(#TIME#)h')) {
            $notes[] = 'Ihr eigenes Design der Einträge enthält „(#TIME#)h“. Ändern Sie es bei Bedarf im AdminCenter unter „Konfiguration“ in „(#TIME#) Uhr“.';
        }

        return null;
    }
    $label = $kind === 'time'
        ? 'Standarddesign der Einträge: „(#TIME#)h“ durch „(#TIME#) Uhr“ ersetzen'
        : 'Altes Standarddesign der Einträge (Tabelle aus PowerBook 1.x/2.0) durch das neue Standarddesign ersetzen';

    return pb_update_value_task($label, $table, ['design' => $newDesign]);
}

/**
 * Ist das ein unverändertes Standarddesign einer früheren Version? Liefert die Art oder null.
 */
function pb_update_old_design(string $design): ?string
{
    $compact = (string) preg_replace('/\s+/', '', $design);
    foreach (PB_UPDATE_OLD_DESIGNS as $kind => $old) {
        if ($compact === preg_replace('/\s+/', '', $old)) {
            return $kind;
        }
    }

    return null;
}

/**
 * Unveränderte Danke-Mail früherer Versionen (englisch, „Gruessen“, „Der Admin“) erneuern.
 *
 * @param array<string, mixed> $row
 * @param array{insert: string, design: string, thanks_title: string, thanks: string} $standard
 *
 * @return array{label: string, run: Closure(PDO): void}|null
 */
function pb_update_thanks_task(string $table, array $row, array $standard): ?array
{
    $values = [];
    if (in_array(str_replace("\r\n", "\n", trim((string) ($row['thanks'] ?? ''))), PB_UPDATE_OLD_THANKS, true)) {
        $values['thanks'] = $standard['thanks'];
    }
    if (in_array(trim((string) ($row['thanks_title'] ?? '')), PB_UPDATE_OLD_THANKS_TITLES, true)) {
        $values['thanks_title'] = $standard['thanks_title'];
    }
    if ($values === [] || $standard['thanks'] === '') {
        return null;
    }

    return pb_update_value_task('Standardtext der Danke-Mail erneuern', $table, $values);
}

/**
 * Sperrdatei anlegen (U15) und alten Installer entfernen (U16).
 *
 * @param array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} $paths
 * @param list<string> $notes
 *
 * @return list<array{label: string, run: Closure(PDO): void}>
 */
function pb_update_file_tasks(array $paths, array &$notes): array
{
    $tasks = [];
    $lock = $paths['lock'];
    if (!is_file($lock)) {
        $tasks[] = [
            'label' => 'Datei install.lock anlegen (sperrt den Installer)',
            'run' => static function (PDO $pdo) use ($lock): void {
                if (!pb_setup_write_file($lock, 'PowerBook aktualisiert auf ' . PB_VERSION . ' am ' . date('c') . "\n")) {
                    throw new RuntimeException('install.lock konnte nicht angelegt werden (keine Schreibrechte). Löschen Sie install.php per FTP.');
                }
            },
        ];
    }

    $old = $paths['installDeu'];
    if (!is_file($old) || !str_contains((string) file_get_contents($old), 'install=yes')) {
        return $tasks;
    }
    if (!is_writable(dirname($old))) {
        $notes[] = 'Auf dem Server liegt noch der alte Installer install_deu.php. Löschen Sie ihn bitte per FTP; bis dahin sperrt ihn die .htaccess.';

        return $tasks;
    }
    $tasks[] = [
        'label' => 'Alten Installer install_deu.php löschen (konnte per Aufruf alle Tabellen löschen)',
        'run' => static function (PDO $pdo) use ($old): void {
            if (!@unlink($old)) {
                throw new RuntimeException('install_deu.php konnte nicht gelöscht werden. Löschen Sie die Datei bitte per FTP.');
            }
        },
    ];

    return $tasks;
}

/**
 * Hinweise ohne eigene Aufgabe: Zeichensatz, alte Passwörter, Benachrichtigungsadresse.
 *
 * @param array<string, string> $names
 * @param array<string, array{name: string, engine: string, collation: string}> $tables
 *
 * @return list<string>
 */
function pb_update_general_notes(PDO $pdo, array $names, array $tables): array
{
    return array_values(array_filter([
        pb_update_charset_note($tables),
        pb_update_password_note($pdo, $names['pb_admins']),
        pb_update_email_note($pdo, $names['pb_config']),
    ], static fn (string $note): bool => $note !== ''));
}

/**
 * @param array<string, array{name: string, engine: string, collation: string}> $tables
 */
function pb_update_charset_note(array $tables): string
{
    $foreign = [];
    foreach ($tables as $info) {
        if ($info['collation'] !== '' && !str_starts_with($info['collation'], 'utf8mb4')) {
            $foreign[] = $info['name'];
        }
    }
    if ($foreign === []) {
        return '';
    }

    return 'Die Tabellen ' . implode(', ', $foreign) . ' nutzen noch einen älteren Zeichensatz. update.php stellt ihn nicht um, '
        . 'weil das bei falsch gespeicherten Umlauten Daten verändern kann. Neue Tabellen legt es mit utf8mb4 an.';
}

function pb_update_password_note(PDO $pdo, string $table): string
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM `' . $table . "` WHERE password NOT LIKE '$%'");
    $old = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
    if ($old === 0) {
        return '';
    }

    return ($old === 1 ? 'Ein Konto hat' : $old . ' Konten haben') . ' noch ein Passwort im alten Format von PowerBook 1.x. '
        . 'Es wird bei der nächsten Anmeldung im AdminCenter sicher gespeichert.';
}

function pb_update_email_note(PDO $pdo, string $table): string
{
    if (!isset(pb_update_columns($pdo, $table)['email'])) {
        return '';
    }
    $stmt = $pdo->query('SELECT email FROM `' . $table . '` LIMIT 1');
    $email = $stmt !== false ? $stmt->fetchColumn() : false;
    if ($email === false || trim((string) $email) !== '') {
        return '';
    }

    return 'Für Benachrichtigungen ist noch keine E-Mail-Adresse eingetragen. Tragen Sie im AdminCenter unter „Konfiguration“ eine ein.';
}
