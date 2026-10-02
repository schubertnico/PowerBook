<?php

/**
 * PowerBook - PHP Guestbook System
 * Gemeinsame Helfer für install.php und update.php (seit 3.1)
 *
 * Seitenrahmen im Bootstrap-Layout von PowerBook, Formularbausteine,
 * Datenbankverbindung mit verständlichen Fehlertexten, Schema aus
 * powerbook.sql, Adressvorschlag und das Schreiben von pb_inc/mysql.inc.php.
 * Außerdem die Seiten „noch nicht eingerichtet“ und „Datenbank nicht
 * erreichbar“, die mysql-connect.inc.php zeigt.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/version.inc.php';
require_once __DIR__ . '/database.inc.php';

if (!defined('PB_SETUP_BOOTSTRAP_CSS')) {
    define('PB_SETUP_BOOTSTRAP_CSS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    define('PB_SETUP_BOOTSTRAP_SRI', 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH');
    /** Standardnamen der Tabellen in powerbook.sql. */
    define('PB_SETUP_TABLES', ['pb_admins', 'pb_config', 'pb_entries', 'pb_login_attempts']);
}

// =============================================================================
// Antworten (Status, Kopfzeilen, Inhalt) – testbar ohne echte Ausgabe
// =============================================================================

/**
 * @param array<string, string> $headers
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_setup_response(string $body, int $status = 200, array $headers = []): array
{
    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

/**
 * Weiterleitung nach einem Formular (Post/Redirect/Get).
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_setup_redirect(string $location): array
{
    return pb_setup_response('', 303, ['Location' => $location]);
}

/**
 * Gibt eine Antwort aus.
 *
 * @param array{status: int, headers: array<string, string>, body: string} $response
 */
function pb_setup_send(array $response): void
{
    if (!headers_sent()) {
        http_response_code($response['status']);
        header('Content-Type: text/html; charset=UTF-8');
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . str_replace(["\r", "\n"], '', $value));
        }
    }
    echo $response['body'];
}

/**
 * Startet die Sitzung für Installer und Aktualisierung (HttpOnly, SameSite=Lax,
 * Secure bei HTTPS).
 */
function pb_setup_start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => pb_setup_is_https(),
    ]);
    session_start();
}

/**
 * Neue Sitzungs-ID nach Anmeldung oder Installation (nur wenn möglich).
 */
function pb_setup_regenerate_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }
}

/**
 * Kam die Anfrage per POST?
 */
function pb_setup_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Ohne date.timezone in der php.ini rechnet PHP in UTC – Zeitangaben in
 * mysql.inc.php und install.lock lägen dann daneben.
 */
function pb_setup_timezone(): void
{
    $zone = ini_get('date.timezone');
    if ($zone === false || $zone === '') {
        date_default_timezone_set('Europe/Berlin');
    }
}

function pb_setup_is_https(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

    return ($https !== '' && $https !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

// =============================================================================
// Seitenrahmen
// =============================================================================

/**
 * Vollständige Seite im Bootstrap-Layout von PowerBook.
 *
 * @param string $title Seitentitel (Tab), wird escaped
 * @param string $main  fertiges HTML des Hauptbereichs
 * @param array{brand?: string, version?: bool, root?: string, aside?: string} $opts
 */
function pb_setup_page(string $title, string $main, array $opts = []): string
{
    $root = (string) ($opts['root'] ?? '');
    $brand = (string) ($opts['brand'] ?? 'PowerBook');
    $badge = ($opts['version'] ?? false) === true
        ? '        <span class="badge text-bg-light">Version ' . e(PB_VERSION) . '</span>' . "\n"
        : '';
    $cssFile = dirname(__DIR__) . '/assets/powerbook.css';
    $css = is_file($cssFile)
        ? '    <link href="' . e($root) . 'assets/powerbook.css?v=' . (int) filemtime($cssFile) . '" rel="stylesheet">' . "\n"
        : '';
    $aside = (string) ($opts['aside'] ?? '');
    $content = $aside !== ''
        ? '    <div class="row g-4">' . "\n"
            . '        <aside class="col-12 col-md-3">' . $aside . '</aside>' . "\n"
            . '        <section class="col-12 col-md-9">' . "\n" . $main . "\n" . '        </section>' . "\n"
            . '    </div>' . "\n"
        : '    <div class="mx-auto" style="max-width: 820px">' . "\n" . $main . "\n" . '    </div>' . "\n";

    return '<!DOCTYPE html>' . "\n"
        . '<html lang="de">' . "\n"
        . '<head>' . "\n"
        . '    <meta charset="UTF-8">' . "\n"
        . '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n"
        . '    <meta name="robots" content="noindex">' . "\n"
        . '    <title>' . e($title) . '</title>' . "\n"
        . '    <link href="' . PB_SETUP_BOOTSTRAP_CSS . '" rel="stylesheet" integrity="' . PB_SETUP_BOOTSTRAP_SRI . '" crossorigin="anonymous">' . "\n"
        . $css
        . '</head>' . "\n"
        . '<body class="pb-body bg-body-tertiary d-flex flex-column min-vh-100">' . "\n"
        . '<nav class="navbar bg-primary navbar-dark shadow-sm mb-4" aria-label="' . e($brand) . '">' . "\n"
        . '    <div class="container">' . "\n"
        . '        <span class="navbar-brand">' . e($brand) . '</span>' . "\n"
        . $badge
        . '    </div>' . "\n"
        . '</nav>' . "\n"
        . '<main class="container flex-grow-1 pb-5">' . "\n"
        . $content
        . '</main>' . "\n"
        . '<footer class="container py-4 mt-4 text-center text-body-secondary border-top">' . "\n"
        . '    <small><a href="https://www.powerscripts.org" target="_blank" rel="noopener noreferrer">PowerBook</a> &middot; powered by powerscripts.org</small>' . "\n"
        . '</footer>' . "\n"
        . '</body>' . "\n"
        . '</html>' . "\n";
}

/**
 * Karte mit blauem Kopf, wie im übrigen PowerBook.
 *
 * @param string $heading Überschrift (wird escaped)
 * @param string $content fertiges HTML
 */
function pb_setup_card(string $heading, string $content): string
{
    return '<div class="card shadow-sm">'
        . '<div class="card-header bg-primary text-white"><h1 class="h4 mb-0">' . e($heading) . '</h1></div>'
        . '<div class="card-body">' . $content . '</div>'
        . '</div>';
}

/**
 * Liste der Schritte (erledigt mit Haken, aktueller Schritt fett).
 *
 * @param array<int, string> $steps
 */
function pb_setup_steps(string $id, array $steps, int $current): string
{
    $items = '';
    foreach ($steps as $number => $label) {
        $done = $number < $current;
        $active = $number === $current;
        $badge = $done ? 'text-bg-success' : ($active ? 'text-bg-primary' : 'text-bg-light border');
        $items .= '<li class="d-flex align-items-center gap-2 mb-2' . ($active ? ' fw-bold' : '') . '"'
            . ($active ? ' aria-current="step"' : '') . '>'
            . '<span class="badge rounded-pill ' . $badge . '">' . ($done ? '✓' : $number) . '</span>'
            . e($label) . '</li>';
    }

    return '<div class="card shadow-sm"><div class="card-body">'
        . '<h2 class="h6 text-uppercase text-body-secondary small mb-3">Schritte</h2>'
        . '<ol id="' . e($id) . '" class="list-unstyled mb-0">' . $items . '</ol>'
        . '</div></div>';
}

/**
 * Fehlerliste als Bootstrap-Alert.
 *
 * @param list<string> $errors Meldungen (bereits HTML-sicher)
 */
function pb_setup_errors(array $errors): string
{
    if ($errors === []) {
        return '';
    }
    $items = '';
    foreach ($errors as $error) {
        $items .= '<li>' . $error . '</li>';
    }

    return '<div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3">' . $items . '</ul></div>';
}

/**
 * Ein Eingabefeld (Beschriftung links, Feld rechts).
 *
 * @param string $help  Hilfetext (HTML-sicher)
 * @param string $extra weitere Attribute (HTML-sicher)
 */
function pb_setup_field(string $id, string $label, string $type, string $value, string $help = '', string $extra = ''): string
{
    $describedBy = $help !== '' ? ' aria-describedby="' . $id . 'Help"' : '';

    return '<div class="row mb-3">'
        . '<label for="' . $id . '" class="col-sm-4 col-form-label">' . e($label) . '</label>'
        . '<div class="col-sm-8">'
        . '<input id="' . $id . '" name="' . $id . '" type="' . $type . '" class="form-control" value="' . e($value) . '"' . $describedBy . ($extra !== '' ? ' ' . $extra : '') . '>'
        . ($help !== '' ? '<div id="' . $id . 'Help" class="form-text">' . $help . '</div>' : '')
        . '</div></div>';
}

/**
 * Ein Kontrollkästchen mit Beschriftung.
 */
function pb_setup_checkbox(string $id, string $label, bool $checked): string
{
    return '<div class="form-check mb-3">'
        . '<input class="form-check-input" type="checkbox" id="' . $id . '" name="' . $id . '" value="1"' . ($checked ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="' . $id . '">' . e($label) . '</label>'
        . '</div>';
}

/**
 * Wert aus $_POST als getrimmter String.
 */
function pb_setup_post(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

/**
 * Wert aus $_POST unverändert (Passwörter werden nicht getrimmt).
 */
function pb_setup_post_raw(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? $value : '';
}

// =============================================================================
// CSRF (eigenes Token je Werkzeug, unabhängig vom AdminCenter)
// =============================================================================

function pb_setup_csrf_token(string $key): string
{
    if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || $_SESSION[$key] === '') {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$key];
}

function pb_setup_csrf_field(string $key): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(pb_setup_csrf_token($key)) . '">';
}

function pb_setup_csrf_ok(string $key): bool
{
    $token = $_POST['csrf_token'] ?? '';

    return is_string($token) && $token !== '' && hash_equals(pb_setup_csrf_token($key), $token);
}

// =============================================================================
// Datenbank
// =============================================================================

/**
 * Baut eine Verbindung mit kurzer Wartezeit auf.
 *
 * @param array{host: string, port: int, database: string, user: string, password: string} $db
 *
 * @throws PDOException
 */
function pb_setup_connect(array $db): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']);

    return new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

/**
 * Fehlernummer des Servers (1045, 2002 …) aus einer PDO-Ausnahme.
 */
function pb_setup_error_code(PDOException $e): int
{
    $info = $e->errorInfo;
    if (is_array($info) && isset($info[1]) && is_numeric($info[1])) {
        return (int) $info[1];
    }
    if (preg_match('/\[(\d{4})\]/', $e->getMessage(), $match) === 1) {
        return (int) $match[1];
    }

    return is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
}

/**
 * Verständliche Meldung zu einem Verbindungsfehler – ohne die Rohmeldung des
 * Servers (sie nennt Benutzername und IP-Adresse des Webservers).
 *
 * @return string Klartext (noch nicht escaped)
 */
function pb_setup_connect_error(PDOException $e, string $host, string $database): string
{
    return match (pb_setup_error_code($e)) {
        1045 => 'Die Datenbank hat die Anmeldung abgelehnt: Benutzername oder Passwort stimmen nicht.',
        1044 => 'Der Benutzer darf nicht auf die Datenbank „' . $database . '“ zugreifen.',
        1049 => 'Die Datenbank „' . $database . '“ gibt es auf dem Server nicht. Legen Sie sie beim Hoster an oder prüfen Sie den Namen.',
        2002, 2003, 2005, 2006 => 'Der Datenbankserver „' . $host . '“ ist nicht erreichbar. Prüfen Sie Servername und Port.',
        default => 'Die Verbindung zur Datenbank ist fehlgeschlagen (Fehler ' . pb_setup_error_code($e) . '). Prüfen Sie die Angaben.',
    };
}

/**
 * Prüft die Version des Datenbankservers: MySQL 8.0 oder MariaDB 10.6 oder neuer.
 *
 * @return string|null Klartext-Meldung oder null, wenn alles passt
 */
function pb_setup_server_problem(PDO $pdo): ?string
{
    $stmt = $pdo->query('SELECT VERSION()');
    $version = $stmt !== false ? (string) $stmt->fetchColumn() : '';

    return pb_setup_version_problem($version);
}

/**
 * @return string|null Klartext-Meldung oder null, wenn die Version passt
 */
function pb_setup_version_problem(string $version): ?string
{
    $isMaria = stripos($version, 'mariadb') !== false;
    $clean = (string) preg_replace('/^5\.5\.5-/', '', $version);
    if (preg_match('/^(\d+)\.(\d+)/', $clean, $match) !== 1) {
        return null;
    }
    $number = $match[1] . '.' . $match[2];
    $minimum = $isMaria ? '10.6' : '8.0';
    if (version_compare($number, $minimum, '>=')) {
        return null;
    }

    return 'Der Datenbankserver meldet ' . ($isMaria ? 'MariaDB ' : 'MySQL ') . $number . '. '
        . 'PowerBook braucht MySQL 8.0 oder MariaDB 10.6 oder neuer. Beim Hoster lässt sich die Version oft im Kundenmenü wählen.';
}

/**
 * Welche der übergebenen Tabellen gibt es in der Datenbank?
 *
 * @param list<string> $tables
 *
 * @return list<string>
 */
function pb_setup_existing_tables(PDO $pdo, array $tables): array
{
    if ($tables === []) {
        return [];
    }
    $placeholders = implode(', ', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (' . $placeholders . ')');
    $stmt->execute($tables);
    $found = array_map('strtolower', array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    $existing = [];
    foreach ($tables as $table) {
        if (in_array(strtolower($table), $found, true)) {
            $existing[] = $table;
        }
    }

    return $existing;
}

/**
 * Anzahl der Zeilen einer Tabelle (Name vorher geprüft).
 */
function pb_setup_count_rows(PDO $pdo, string $table): int
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`');

    return $stmt !== false ? (int) $stmt->fetchColumn() : 0;
}

// =============================================================================
// Schema aus powerbook.sql
// =============================================================================

/**
 * Tabellennamen aus pb_inc/mysql.inc.php (Standard: pb_admins, pb_config, pb_entries).
 *
 * @param array<string, mixed> $vars Variablen aus mysql.inc.php
 *
 * @return array<string, string> Standardname => tatsächlicher Name
 */
function pb_setup_table_names(array $vars = []): array
{
    $names = [];
    foreach (PB_SETUP_TABLES as $table) {
        $names[$table] = $table;
    }
    foreach (['pb_admins' => 'pb_admin', 'pb_config' => 'pb_config', 'pb_entries' => 'pb_entries'] as $table => $variable) {
        $value = $vars[$variable] ?? null;
        if (is_string($value) && preg_match('/^\w{1,64}$/', $value) === 1) {
            $names[$table] = $value;
        }
    }

    return $names;
}

/**
 * Ersetzt die Standardnamen der Tabellen durch die konfigurierten.
 *
 * @param array<string, string> $names
 */
function pb_setup_rename_tables(string $sql, array $names): string
{
    $map = [];
    foreach ($names as $default => $actual) {
        if ($default !== $actual) {
            $map['/\b' . preg_quote($default, '/') . '\b/'] = $actual;
        }
    }
    if ($map === []) {
        return $sql;
    }

    return (string) preg_replace(array_keys($map), array_values($map), $sql);
}

/**
 * Anweisungen aus powerbook.sql ohne Kommentare, mit den konfigurierten Tabellennamen.
 *
 * @param array<string, string> $names
 *
 * @return list<string>
 */
function pb_setup_schema_statements(string $sql, array $names = []): array
{
    $lines = [];
    foreach (preg_split('/\R/', $sql) ?: [] as $line) {
        if (!str_starts_with(ltrim($line), '--')) {
            $lines[] = $line;
        }
    }
    $statements = [];
    foreach (preg_split('/;\s*(?:\R|$)/', implode("\n", $lines)) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $statements[] = pb_setup_rename_tables($statement, $names);
        }
    }

    return $statements;
}

/**
 * Tabellen, Spalten und Schlüssel aus powerbook.sql (Standardnamen).
 *
 * @return array<string, array{create: string, columns: array<string, string>, keys: array<string, string>}>
 */
function pb_setup_schema_tables(string $sql): array
{
    $schema = [];
    $statements = pb_setup_schema_statements($sql);
    foreach ($statements as $statement) {
        if (preg_match('/^CREATE TABLE\s+`?(\w+)`?\s*\((.*)\)\s*ENGINE/is', $statement, $match) !== 1) {
            continue;
        }
        $columns = [];
        $keys = [];
        foreach (preg_split('/\R/', $match[2]) ?: [] as $line) {
            $line = rtrim(trim($line), ',');
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:UNIQUE\s+)?KEY\s+`?(\w+)`?/i', $line, $key) === 1) {
                $keys[strtolower($key[1])] = $line;
                continue;
            }
            if (preg_match('/^(PRIMARY|INDEX|CONSTRAINT|FOREIGN|FULLTEXT)\b/i', $line) === 1) {
                continue;
            }
            $columns[strtolower(trim((string) strtok($line, " \t"), '`'))] = $line;
        }
        $schema[strtolower($match[1])] = ['create' => $statement, 'columns' => $columns, 'keys' => $keys];
    }

    return $schema;
}

/**
 * Text der Zeichenketten in einer SQL-Anweisung (MySQL-Schreibweise mit \n, \' und '').
 *
 * @return list<string>
 */
function pb_setup_sql_strings(string $statement): array
{
    preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/s", $statement, $matches);
    $strings = [];
    foreach ($matches[1] as $raw) {
        $raw = str_replace("''", "'", $raw);
        $strings[] = (string) preg_replace_callback('/\\\\(.)/s', static fn (array $escape): string => match ($escape[1]) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '0' => "\0",
            default => $escape[1],
        }, $raw);
    }

    return $strings;
}

// =============================================================================
// pb_inc/mysql.inc.php
// =============================================================================

/**
 * Darf PowerBook die Datei anlegen bzw. überschreiben?
 */
function pb_setup_writable(string $file): bool
{
    if (file_exists($file)) {
        return is_file($file) && is_writable($file);
    }

    return is_dir(dirname($file)) && is_writable(dirname($file));
}

/**
 * Inhalt von pb_inc/mysql.inc.php.
 *
 * @param array{host: string, port: int, database: string, user: string, password: string} $db
 * @param array<string, string> $names Tabellennamen
 */
function pb_setup_config_content(array $db, array $names = [], ?int $time = null): string
{
    $time ??= time();
    $names += pb_setup_table_names();

    return "<?php\n\ndeclare(strict_types=1);\n\n"
        . "/**\n"
        . ' * PowerBook – Zugang zur Datenbank, angelegt von install.php am ' . date('d.m.Y', $time) . ' um ' . date('H:i', $time) . " Uhr.\n"
        . " * Alle übrigen Einstellungen stehen in der Datenbank (AdminCenter → Konfiguration).\n"
        . " */\n\n"
        . '$config_sql_server = ' . var_export($db['host'], true) . ";\n"
        . '$config_sql_port = ' . (int) $db['port'] . ";\n"
        . '$config_sql_user = ' . var_export($db['user'], true) . ";\n"
        . '$config_sql_password = ' . var_export($db['password'], true) . ";\n"
        . '$config_sql_database = ' . var_export($db['database'], true) . ";\n\n"
        . '$pb_config = ' . var_export($names['pb_config'], true) . ";\n"
        . '$pb_admin = ' . var_export($names['pb_admins'], true) . ";\n"
        . '$pb_entries = ' . var_export($names['pb_entries'], true) . ";\n";
}

/**
 * Schreibt eine Datei vollständig und meldet sie beim OPcache ab, damit der
 * Server nicht noch einen alten Stand ausführt.
 */
function pb_setup_write_file(string $file, string $content): bool
{
    if (@file_put_contents($file, $content, LOCK_EX) !== strlen($content)) {
        return false;
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }

    return true;
}

/**
 * Liest pb_inc/mysql.inc.php in einem eigenen Gültigkeitsbereich.
 *
 * @return array{db: array{host: string, port: int, database: string, user: string, password: string}, names: array<string, string>}
 */
function pb_setup_load_config(string $file): array
{
    $vars = (static function (string $pbConfigFile): array {
        require $pbConfigFile;

        return get_defined_vars();
    })($file);

    return [
        'db' => [
            'host' => (string) ($vars['config_sql_server'] ?? ''),
            'port' => (int) ($vars['config_sql_port'] ?? 3306),
            'database' => (string) ($vars['config_sql_database'] ?? ''),
            'user' => (string) ($vars['config_sql_user'] ?? ''),
            'password' => (string) ($vars['config_sql_password'] ?? ''),
        ],
        'names' => pb_setup_table_names($vars),
    ];
}

/**
 * Löscht Dateien, soweit vorhanden (Installer und Aktualisierung nach Gebrauch).
 *
 * @param list<string> $files
 *
 * @return list<string> Dateien, die sich nicht löschen ließen (z. B. „pb_inc/install.inc.php“)
 */
function pb_setup_delete_files(array $files): array
{
    $left = [];
    foreach ($files as $file) {
        if (is_file($file) && !@unlink($file)) {
            $left[] = basename(dirname($file)) === 'pb_inc' ? 'pb_inc/' . basename($file) : basename($file);
        }
    }

    return $left;
}

// =============================================================================
// Adressen
// =============================================================================

/**
 * Adresse des PowerBook-Ordners aus der aufgerufenen Adresse (mit / am Ende).
 */
function pb_setup_base_url(): string
{
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '';
    if ($host === '') {
        return '';
    }
    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'))), '/');

    return (pb_setup_is_https() ? 'https' : 'http') . '://' . $host . $dir . '/';
}

/**
 * Ist das eine vollständige http(s)-Adresse?
 */
function pb_setup_valid_url(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && preg_match('~^https?://[^/\s]+~i', $url) === 1;
}

/**
 * Weg vom aufgerufenen Skript zum PowerBook-Ordner ('' oder z. B. '../../').
 */
function pb_setup_relative_root(): string
{
    $root = realpath(dirname(__DIR__));
    $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptDir = $script !== '' ? realpath(dirname($script)) : false;
    if ($root === false || $scriptDir === false) {
        return '';
    }
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
    if ($scriptDir === $root || !str_starts_with($scriptDir . '/', $root . '/')) {
        return '';
    }
    $depth = substr_count(trim(substr($scriptDir, strlen($root)), '/'), '/') + 1;

    return str_repeat('../', $depth);
}

// =============================================================================
// Laufzeit: noch nicht eingerichtet / Datenbank nicht erreichbar
// =============================================================================

/**
 * Seite für das Gästebuch und das AdminCenter, solange pb_inc/mysql.inc.php fehlt.
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_setup_not_installed_response(): array
{
    $root = pb_setup_relative_root();
    $body = pb_setup_card('PowerBook ist noch nicht eingerichtet', '<p>Für dieses Gästebuch gibt es noch keine Verbindung zur Datenbank. '
        . 'Der Installer richtet PowerBook in drei Schritten ein: Datenbank, Gästebuch und Administrator.</p>'
        . '<a id="pbSetupInstall" class="btn btn-primary" href="' . e($root) . 'install.php">Zum Installer &raquo;</a>');

    return pb_setup_response(pb_setup_page('PowerBook ist noch nicht eingerichtet', $body, ['root' => $root]), 503, [
        'Cache-Control' => 'no-store',
        'Retry-After' => '3600',
    ]);
}

/**
 * Seite, wenn die Datenbank nicht antwortet (Details stehen nur im Log).
 *
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function pb_setup_unavailable_response(): array
{
    $root = pb_setup_relative_root();
    $body = pb_setup_card('Die Datenbank ist gerade nicht erreichbar', '<p>Das Gästebuch kann im Moment keine Einträge laden. '
        . 'Bitte versuchen Sie es in einigen Minuten noch einmal.</p>'
        . '<p class="form-text mb-0">Betreiben Sie dieses Gästebuch? Die Ursache steht in der Datei <code>logs/error.log</code>.</p>');

    return pb_setup_response(pb_setup_page('Die Datenbank ist gerade nicht erreichbar', $body, ['root' => $root]), 503, [
        'Cache-Control' => 'no-store',
        'Retry-After' => '300',
    ]);
}
