<?php

/**
 * PowerBook - PHP Guestbook System
 * Hilfsfunktionen für Admin-Konten: Rechte, Mails, Passwort-Links, Drossel
 *
 * Wird von admins.inc.php, account.inc.php und password.inc.php per
 * require_once geladen. Die Funktionen stehen im Root-Scope, damit
 * mehrfaches Einbinden der Seiten (z. B. in Tests) nichts doppelt deklariert.
 *
 * Alle Mails laufen über pb_mail() (pb_inc/mail.inc.php) als Klartext mit
 * echten Umlauten. Die Texte entstehen in pb_admin_mail_message() und lassen
 * sich so ohne Mailversand prüfen.
 *
 * @license MIT
 * @copyright PowerScripts.org
 */

declare(strict_types=1);

if (!defined('PB_TOKEN_LIFETIME_RESET')) {
    /** Gültigkeit des Links aus „Passwort vergessen?“ in Sekunden (30 Minuten). */
    define('PB_TOKEN_LIFETIME_RESET', 1800);
}
if (!defined('PB_TOKEN_LIFETIME_LINK')) {
    /** Gültigkeit des Links „Passwort festlegen“ für neue Admins und von Admins verschickte Links (48 Stunden). */
    define('PB_TOKEN_LIFETIME_LINK', 172800);
}

// ---------------------------------------------------------------------------
// Rechte
// ---------------------------------------------------------------------------

/**
 * Die vier Rechte in der Reihenfolge, in der sie angezeigt werden.
 *
 * @return array<string, array{label: string, help: string}>
 */
function pb_admin_rights(): array
{
    return [
        'release' => [
            'label' => 'Einträge freischalten',
            'help' => 'Wartende Einträge prüfen, freischalten oder als Spam löschen.',
        ],
        'entries' => [
            'label' => 'Einträge bearbeiten und löschen',
            'help' => 'Einträge ändern, beantworten und löschen.',
        ],
        'config' => [
            'label' => 'Konfiguration ändern',
            'help' => 'Einstellungen, E-Mails und Design des Gästebuchs ändern.',
        ],
        'admins' => [
            'label' => 'Admins verwalten',
            'help' => 'Weitere Admins anlegen, ändern und löschen. Dieses Recht vergibt nur der Superadmin.',
        ],
    ];
}

/**
 * Superadmin ist fest das Konto mit der ID 1 (aus der Installation).
 *
 * @param array<string, mixed> $admin
 */
function pb_admin_is_superadmin(array $admin): bool
{
    return (int) ($admin['id'] ?? 0) === 1;
}

/**
 * Darf $actor das Recht $right vergeben oder entziehen?
 *
 * Niemand vergibt Rechte, die er selbst nicht hat. „Admins verwalten“
 * vergibt nur der Superadmin.
 *
 * @param array<string, mixed> $actor
 */
function pb_admin_can_grant(array $actor, string $right): bool
{
    if (!array_key_exists($right, pb_admin_rights())) {
        return false;
    }
    if (pb_admin_is_superadmin($actor)) {
        return true;
    }
    if ($right === 'admins') {
        return false;
    }

    return ($actor[$right] ?? 'N') === 'Y';
}

/**
 * Darf $actor das Konto $target auf der Admins-Seite ändern oder löschen?
 *
 * - Den Superadmin ändert nur er selbst, und zwar unter „Mein Konto“.
 * - Das eigene Konto ändert jeder unter „Mein Konto“.
 * - Konten mit dem Recht „Admins verwalten“ ändert nur der Superadmin.
 * - Andere Admins ändern nur Konten, deren Rechte sie selbst alle haben.
 *
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $target
 */
function pb_admin_can_manage(array $actor, array $target): bool
{
    $actorId = (int) ($actor['id'] ?? 0);
    $targetId = (int) ($target['id'] ?? 0);

    if ($actorId <= 0 || $targetId <= 0 || $actorId === $targetId) {
        return false;
    }
    if (($actor['admins'] ?? 'N') !== 'Y' && !pb_admin_is_superadmin($actor)) {
        return false;
    }
    if (pb_admin_is_superadmin($target)) {
        return false;
    }
    if (pb_admin_is_superadmin($actor)) {
        return true;
    }
    if (($target['admins'] ?? 'N') === 'Y') {
        return false;
    }

    // Sonst könnte jemand über Adresse und Passwort-Link ein Konto mit
    // mehr Rechten übernehmen, als er selbst hat.
    return pb_admin_has_rights_of($actor, $target);
}

/**
 * Hat $actor jedes Recht, das $target hat?
 *
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $target
 */
function pb_admin_has_rights_of(array $actor, array $target): bool
{
    foreach (array_keys(pb_admin_rights()) as $right) {
        if (($target[$right] ?? 'N') === 'Y' && ($actor[$right] ?? 'N') !== 'Y') {
            return false;
        }
    }

    return true;
}

/**
 * Lädt ein Admin-Konto.
 *
 * @return array<string, mixed>|null
 */
function pb_admin_load(PDO $pdo, string $table, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * Ist Name oder E-Mail-Adresse schon an ein anderes Konto vergeben?
 *
 * @param string $column name oder email
 */
function pb_admin_value_taken(PDO $pdo, string $table, string $column, string $value, int $exceptId = 0): bool
{
    if (!in_array($column, ['name', 'email'], true)) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE LOWER({$column}) = LOWER(?) AND id <> ?");
    $stmt->execute([$value, $exceptId]);

    return (int) $stmt->fetchColumn() > 0;
}

// ---------------------------------------------------------------------------
// Prüfungen
// ---------------------------------------------------------------------------

/**
 * Prüft einen Admin-Namen. Gibt eine Fehlermeldung oder null zurück.
 */
function pb_admin_validate_name(string $name): ?string
{
    if ($name === '') {
        return 'Bitte geben Sie einen Namen ein.';
    }
    if (mb_strlen($name, 'UTF-8') > 100) {
        return 'Der Name darf höchstens 100 Zeichen lang sein.';
    }
    if (str_contains($name, '@')) {
        return 'Der Name darf kein @ enthalten. Angemeldet wird mit dem Namen oder der E-Mail-Adresse, beides muss eindeutig bleiben.';
    }
    if (preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
        return 'Der Name enthält unzulässige Zeichen.';
    }

    return null;
}

/**
 * Prüft eine E-Mail-Adresse. Gibt eine Fehlermeldung oder null zurück.
 */
function pb_admin_validate_email(string $email): ?string
{
    if ($email === '') {
        return 'Bitte geben Sie eine E-Mail-Adresse ein.';
    }
    if (mb_strlen($email, 'UTF-8') > 250 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    }

    return null;
}

/**
 * Prüft ein neues Passwort samt Wiederholung (nicht getrimmt).
 * Gibt eine Fehlermeldung oder null zurück.
 */
function pb_admin_validate_new_password(string $password, string $repeat): ?string
{
    if (mb_strlen($password, 'UTF-8') < 8) {
        return 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
    }
    if (trim($password) === '') {
        return 'Das neue Passwort darf nicht nur aus Leerzeichen bestehen.';
    }
    if (mb_strlen($password, 'UTF-8') > 200) {
        return 'Das neue Passwort darf höchstens 200 Zeichen lang sein.';
    }
    if ($password !== $repeat) {
        return 'Die beiden Passwörter stimmen nicht überein.';
    }

    return null;
}

// ---------------------------------------------------------------------------
// Adressen und Passwort-Links
// ---------------------------------------------------------------------------

/**
 * Bringt eine Admin-URL in die Form „https://host/pfad/“ (ohne index.php,
 * ohne Parameter, mit Schrägstrich am Ende). Leere Eingabe bleibt leer.
 */
function pb_admin_normalize_admin_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $url = (string) preg_replace('/[?#].*$/s', '', $url);
    $url = (string) preg_replace('~/index\.php$~i', '/', $url);

    return rtrim($url, '/') . '/';
}

/**
 * Adresse des AdminCenters mit Schrägstrich am Ende: die Admin-URL aus der
 * Konfiguration, ersatzweise die Adresse der aktuellen Anfrage.
 */
function pb_admin_base_url(): string
{
    $configured = pb_admin_normalize_admin_url((string) ($GLOBALS['config_admin_url'] ?? ''));
    if ($configured !== '') {
        return $configured;
    }

    return pb_admin_detected_url();
}

/**
 * Adresse des AdminCenters, abgeleitet aus der aktuellen Anfrage.
 */
function pb_admin_detected_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = (string) preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);
    if ($host === '') {
        $host = 'localhost';
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $dir = str_ends_with($script, '/admincenter/index.php')
        ? dirname($script)
        : '/pb_inc/admincenter';
    $dir = rtrim(str_replace('\\', '/', $dir), '/');

    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/';
}

/**
 * Link zum Festlegen eines Passworts.
 *
 * @param bool $welcome Willkommenstext für neue Admins anzeigen
 */
function pb_admin_password_link(string $token, bool $welcome = false): string
{
    return pb_admin_base_url() . 'index.php?page=password&token=' . rawurlencode($token)
        . ($welcome ? '&welcome=1' : '');
}

/**
 * Erzeugt einen neuen Einmal-Link-Schlüssel für ein Konto.
 *
 * In der Datenbank steht nur der SHA-256-Wert; ein älterer Link des Kontos
 * wird damit ungültig.
 *
 * @return string der Schlüssel für den Link (64 Hex-Zeichen)
 */
function pb_admin_issue_password_token(PDO $pdo, string $table, int $adminId, int $lifetime): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("UPDATE {$table} SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
    $stmt->execute([hash('sha256', $token), time() + $lifetime, $adminId]);

    return $token;
}

/**
 * Sucht das Konto zu einem gültigen, nicht abgelaufenen Link-Schlüssel.
 *
 * @return array<string, mixed>|null
 */
function pb_admin_find_by_token(PDO $pdo, string $table, string $token): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id, name, email, reset_token_expires FROM {$table} WHERE reset_token = ? LIMIT 1");
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row) || (int) ($row['reset_token_expires'] ?? 0) < time()) {
        return null;
    }

    return $row;
}

// ---------------------------------------------------------------------------
// Drossel (Tabelle pb_login_attempts)
// ---------------------------------------------------------------------------

/**
 * Name der Tabelle für Anmeldeversuche.
 */
function pb_attempts_table(): string
{
    $table = $GLOBALS['pb_login_attempts'] ?? 'pb_login_attempts';

    return is_string($table) && $table !== '' ? $table : 'pb_login_attempts';
}

/**
 * IP-Adresse der Anfrage (höchstens 45 Zeichen).
 */
function pb_admin_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * Zählt Zeilen der letzten $window Sekunden.
 *
 * @param string|null $ip      nur diese IP-Adresse (null = alle)
 * @param string      $nameSql Bedingung für die Spalte name: 'LIKE ?', 'NOT LIKE ?' oder '= ?'
 */
function pb_attempts_count(PDO $pdo, ?string $ip, string $nameSql, string $nameValue, int $window): int
{
    if (!in_array($nameSql, ['LIKE ?', 'NOT LIKE ?', '= ?'], true)) {
        return 0;
    }

    $sql = 'SELECT COUNT(*) FROM ' . pb_attempts_table() . " WHERE name {$nameSql} AND time > ?";
    $params = [$nameValue, time() - $window];
    if ($ip !== null) {
        $sql .= ' AND ip = ?';
        $params[] = $ip;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        logDbError('Login attempts count: ' . $e->getMessage());

        return 0;
    }
}

/**
 * Speichert einen Versuch.
 */
function pb_attempts_add(PDO $pdo, string $ip, string $name): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO ' . pb_attempts_table() . ' (ip, name, time) VALUES (?, ?, ?)');
        $stmt->execute([$ip, mb_substr($name, 0, 100, 'UTF-8'), time()]);
    } catch (PDOException $e) {
        logDbError('Login attempts add: ' . $e->getMessage());
    }
}

/**
 * Löscht Versuche, die älter als 15 Minuten sind.
 */
function pb_attempts_cleanup(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM ' . pb_attempts_table() . ' WHERE time < ?');
        $stmt->execute([time() - 900]);
    } catch (PDOException $e) {
        logDbError('Login attempts cleanup: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Meldungen und Weiterleitung
// ---------------------------------------------------------------------------

/**
 * Meldung des letzten Vorgangs direkt auf der Seite (div#pbMessage).
 *
 * @param string $type   success, danger, warning oder info
 * @param bool   $isHtml $text ist bereits sicheres HTML
 */
function pb_admin_inline_message(string $text, string $type = 'danger', bool $isHtml = false): string
{
    if (!$isHtml && function_exists('pb_admin_message_html')) {
        return (string) call_user_func('pb_admin_message_html', $type, $text);
    }
    if (!in_array($type, ['success', 'danger', 'warning', 'info'], true)) {
        $type = 'info';
    }
    $role = ($type === 'danger' || $type === 'warning') ? 'alert' : 'status';

    return '<div id="pbMessage" class="alert alert-' . $type . '" role="' . $role . '" data-type="' . $type . '">'
        . ($isHtml ? $text : htmlspecialchars($text, ENT_QUOTES, 'UTF-8'))
        . '</div>';
}

/**
 * Nach erfolgreichem Speichern: Meldung für die nächste Seite merken und
 * dorthin weiterleiten (Post/Redirect/Get) über pb_admin_flash() und
 * pb_admin_redirect() aus layout.inc.php. pb_admin_redirect() wirft eine
 * PbAdminRedirect-Ausnahme, die index.php in die Weiterleitung umsetzt.
 *
 * Gibt false zurück, wenn das nicht geht (ältere layout.inc.php oder keine
 * Sitzung); dann zeigt die Seite die Meldung selbst an.
 */
function pb_admin_finish_post(string $text, string $target, string $type = 'success'): bool
{
    if (function_exists('pb_admin_flash') && function_exists('pb_admin_redirect')
        && session_status() === PHP_SESSION_ACTIVE) {
        pb_admin_flash($type, $text);
        // Wirft PbAdminRedirect und kehrt nicht zurück.
        pb_admin_redirect($target);
    }

    return false;
}

// ---------------------------------------------------------------------------
// Mails
// ---------------------------------------------------------------------------

/**
 * Ja/Nein für ein Recht.
 */
function formatPermission(string $value): string
{
    return $value === 'Y' ? 'Ja' : 'Nein';
}

/**
 * Rechte als Liste für Mailtexte.
 *
 * @param array<string, mixed> $data
 */
function formatAdminPermissions(array $data): string
{
    $lines = '';
    foreach (pb_admin_rights() as $key => $right) {
        $lines .= '- ' . $right['label'] . ': ' . formatPermission((string) ($data[$key] ?? 'N')) . "\n";
    }

    return $lines;
}

/**
 * Fußzeile jeder Admin-Mail.
 */
function getEmailFooter(): string
{
    return "\n-- \nPowerBook – Gästebuch · https://www.powerscripts.org\n";
}

/**
 * Titel des Gästebuchs für Mailtexte.
 */
function pb_admin_guestbook_title(): string
{
    $title = trim((string) ($GLOBALS['config_title'] ?? ''));

    return $title !== '' ? $title : 'Gästebuch';
}

/**
 * Text für neue Admins: Zugang angelegt, Link „Passwort festlegen“.
 *
 * @param array<string, mixed> $data name, email, by, link, admin_url, Rechte
 */
function buildAddedEmailBody(array $data): string
{
    $title = (string) ($data['title'] ?? pb_admin_guestbook_title());

    return 'Hallo ' . $data['name'] . ",\n\n"
        . $data['by'] . ' hat für Sie einen Zugang zum AdminCenter von „' . $title . "“ angelegt.\n\n"
        . 'Sie melden sich mit Ihrem Namen „' . $data['name'] . '“ oder Ihrer E-Mail-Adresse ' . $data['email'] . " an.\n\n"
        . "Ihre Rechte:\n"
        . formatAdminPermissions($data) . "\n"
        . "Legen Sie zuerst Ihr Passwort fest. Der Link ist 48 Stunden gültig und funktioniert nur einmal:\n"
        . $data['link'] . "\n\n"
        . "Danach melden Sie sich hier an:\n"
        . $data['admin_url'] . "\n\n"
        . "Ist der Link abgelaufen, fordern Sie auf der Anmeldeseite über „Passwort vergessen?“ einen neuen an.\n";
}

/**
 * Text bei geänderter E-Mail-Adresse oder geänderten Rechten (an die aktuelle Adresse).
 *
 * @param array<string, mixed> $data name, email, by, admin_url, Rechte
 */
function buildEditedEmailBody(array $data): string
{
    $title = (string) ($data['title'] ?? pb_admin_guestbook_title());

    return 'Hallo ' . $data['name'] . ",\n\n"
        . $data['by'] . ' hat Ihren Zugang zum AdminCenter von „' . $title . "“ geändert.\n\n"
        . 'Name: ' . $data['name'] . "\n"
        . 'E-Mail-Adresse: ' . $data['email'] . "\n\n"
        . "Ihre Rechte:\n"
        . formatAdminPermissions($data) . "\n"
        . "Ihr Passwort bleibt unverändert. Anmelden:\n"
        . $data['admin_url'] . "\n";
}

/**
 * Hinweis an die bisherige Adresse, wenn die E-Mail-Adresse geändert wurde.
 *
 * @param array<string, mixed> $data name, by, old_email, new_email
 */
function buildEmailChangedEmailBody(array $data): string
{
    $title = (string) ($data['title'] ?? pb_admin_guestbook_title());
    $who = ($data['by'] ?? '') === ($data['name'] ?? '') ? 'Sie haben' : $data['by'] . ' hat';

    return 'Hallo ' . $data['name'] . ",\n\n"
        . $who . ' die E-Mail-Adresse Ihres Zugangs zum AdminCenter von „' . $title . "“ geändert:\n\n"
        . 'bisher: ' . $data['old_email'] . "\n"
        . 'neu: ' . $data['new_email'] . "\n\n"
        . "Mails zu Ihrem Zugang gehen ab jetzt an die neue Adresse.\n"
        . "Falls Sie davon nichts wissen, wenden Sie sich bitte an den Betreiber des Gästebuchs.\n";
}

/**
 * Text, wenn ein Admin einem anderen einen Link zum Festlegen eines neuen Passworts schickt.
 *
 * @param array<string, mixed> $data name, by, link
 */
function buildPasswordLinkEmailBody(array $data): string
{
    $title = (string) ($data['title'] ?? pb_admin_guestbook_title());

    return 'Hallo ' . $data['name'] . ",\n\n"
        . $data['by'] . ' hat Ihnen einen Link geschickt, mit dem Sie ein neues Passwort für das AdminCenter von „' . $title . "“ festlegen.\n"
        . "Der Link ist 48 Stunden gültig und funktioniert nur einmal:\n\n"
        . $data['link'] . "\n\n"
        . "Ihr bisheriges Passwort gilt weiter, bis Sie ein neues festlegen.\n";
}

/**
 * Text für „Passwort vergessen?“.
 *
 * @param array<string, mixed> $data name, link
 */
function buildPasswordResetEmailBody(array $data): string
{
    $title = (string) ($data['title'] ?? pb_admin_guestbook_title());

    return 'Hallo ' . $data['name'] . ",\n\n"
        . 'für Ihren Zugang zum AdminCenter von „' . $title . "“ wurde ein neues Passwort angefordert.\n"
        . "Über diesen Link legen Sie es fest. Er ist 30 Minuten gültig und funktioniert nur einmal:\n\n"
        . $data['link'] . "\n\n"
        . "Ihr bisheriges Passwort gilt weiter, bis Sie ein neues festlegen.\n"
        . "Haben Sie nichts angefordert, ignorieren Sie diese E-Mail einfach.\n";
}

/**
 * Hinweis nach einer Passwortänderung unter „Mein Konto“.
 *
 * @param array<string, mixed> $data name
 */
function buildPasswordChangedEmailBody(array $data): string
{
    $title = (string) ($data['title'] ?? pb_admin_guestbook_title());

    return 'Hallo ' . $data['name'] . ",\n\n"
        . 'das Passwort Ihres Zugangs zum AdminCenter von „' . $title . "“ wurde soeben geändert.\n"
        . "Alle anderen Anmeldungen mit diesem Zugang wurden dabei beendet.\n\n"
        . "Falls Sie das nicht selbst waren, fordern Sie auf der Anmeldeseite über „Passwort vergessen?“ sofort ein neues Passwort an\n"
        . "und wenden Sie sich an den Betreiber des Gästebuchs.\n";
}

/**
 * Text, wenn ein Zugang gelöscht wurde.
 *
 * @param array<string, mixed> $data name, by
 */
function buildDeletedEmailBody(array $data): string
{
    $title = (string) ($data['title'] ?? pb_admin_guestbook_title());

    return 'Hallo ' . ($data['name'] ?? '') . ",\n\n"
        . $data['by'] . ' hat Ihren Zugang zum AdminCenter von „' . $title . "“ entfernt.\n"
        . "Sie können sich dort nicht mehr anmelden.\n";
}

/**
 * Betreff und Text einer Admin-Mail, ohne sie zu verschicken.
 *
 * Typen: added, edited, email_changed, password_link, reset,
 * password_changed, deleted.
 *
 * @param array<string, mixed> $data
 *
 * @return array{subject: string, body: string}|null
 */
function pb_admin_mail_message(string $type, array $data): ?array
{
    $data['admin_url'] = (string) ($data['admin_url'] ?? '') !== '' ? $data['admin_url'] : pb_admin_base_url();

    $message = match ($type) {
        'added' => ['Ihr Zugang zum AdminCenter: bitte Passwort festlegen', buildAddedEmailBody($data)],
        'edited' => ['Ihr Zugang zum AdminCenter wurde geändert', buildEditedEmailBody($data)],
        'email_changed' => ['Ihre E-Mail-Adresse im AdminCenter wurde geändert', buildEmailChangedEmailBody($data)],
        'password_link' => ['Neues Passwort für das AdminCenter festlegen', buildPasswordLinkEmailBody($data)],
        'reset' => ['Passwort für das AdminCenter zurücksetzen', buildPasswordResetEmailBody($data)],
        'password_changed' => ['Ihr Passwort für das AdminCenter wurde geändert', buildPasswordChangedEmailBody($data)],
        'deleted' => ['Ihr Zugang zum AdminCenter wurde entfernt', buildDeletedEmailBody($data)],
        default => null,
    };

    if ($message === null) {
        return null;
    }

    return ['subject' => $message[0], 'body' => $message[1] . getEmailFooter()];
}

/**
 * Verschickt eine Admin-Mail über pb_mail().
 *
 * @param array<string, mixed> $data muss 'to' enthalten, sonst siehe pb_admin_mail_message()
 *
 * @return bool true bei erfolgreichem Versand, false bei Fehler oder ungültigen Daten
 */
function sendAdminEmail(string $type, array $data): bool
{
    $to = trim((string) ($data['to'] ?? ''));
    if ($to === '') {
        return false;
    }

    $message = pb_admin_mail_message($type, $data);
    if ($message === null) {
        return false;
    }

    return pb_mail($to, $message['subject'], $message['body']);
}
