<?php

/**
 * PowerBook - PHP Guestbook System
 * Database Connection (PDO)
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

/**
 * Get PDO database connection (singleton pattern)
 *
 * Scheitert die Verbindung, steht die Meldung des Servers (mit Benutzername
 * und IP-Adresse) nur in logs/error.log; die Ausnahme selbst nennt keine
 * Details und darf angezeigt werden.
 *
 * @throws RuntimeException wenn keine Verbindung zustande kommt
 */
function getDatabase(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        global $config_sql_server, $config_sql_port, $config_sql_user, $config_sql_password, $config_sql_database;

        // Include MySQL configuration if not already loaded. Die Datei legt
        // erst install.php an; im Repository gibt es sie nicht.
        $configFile = __DIR__ . '/mysql.inc.php';
        if (!isset($config_sql_server) && is_file($configFile)) {
            /** @psalm-suppress MissingFile – entsteht erst bei der Installation */
            require_once $configFile;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) ($config_sql_server ?? ''),
            (int) ($config_sql_port ?? 3306),
            (string) ($config_sql_database ?? '')
        );

        try {
            $pdo = new PDO($dsn, (string) ($config_sql_user ?? ''), (string) ($config_sql_password ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $e) {
            pb_db_log('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());

            throw new RuntimeException('Die Datenbank ist gerade nicht erreichbar.', 0, $e);
        }
    }

    return $pdo;
}

/**
 * Schreibt eine Zeile nach logs/error.log (ersatzweise ins PHP-Fehlerprotokoll).
 */
function pb_db_log(string $message): void
{
    $line = sprintf("[%s] PowerBook DB Error: %s\n", date('Y-m-d H:i:s'), str_replace(["\r", "\n"], ' ', $message));
    $logFile = dirname(__DIR__) . '/logs/error.log';
    $logDir = dirname($logFile);
    if (is_dir($logDir) && (is_file($logFile) ? is_writable($logFile) : is_writable($logDir))) {
        error_log($line, 3, $logFile);

        return;
    }
    error_log(rtrim($line));
}

/**
 * Verify and migrate password from Base64 to password_hash
 *
 * PowerBook 1.x speicherte Passwörter Base64-kodiert. Passt ein solches
 * Passwort, wird es sofort als sicherer Hash gespeichert – in der Tabelle,
 * die pb_inc/mysql.inc.php als $pb_admin nennt.
 */
function verifyAndMigratePassword(string $input, string $stored, int $adminId): bool
{
    if ($input === '' || $stored === '') {
        return false;
    }

    // Check if new format (password_hash)
    if (password_verify($input, $stored)) {
        return true;
    }

    // Fallback: Check old Base64 format
    $decoded = base64_decode($stored, true);
    if ($decoded !== false && hash_equals($decoded, $input)) {
        // Migration: Save as password_hash
        $pdo = ($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : getDatabase();
        $table = (string) ($GLOBALS['pb_admin'] ?? 'pb_admins');
        if (preg_match('/^\w+$/', $table) !== 1) {
            $table = 'pb_admins';
        }
        $newHash = password_hash($input, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE {$table} SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $adminId]);

        return true;
    }

    return false;
}

/**
 * Sanitize email header to prevent header injection
 */
function sanitizeEmailHeader(string $value): string
{
    return preg_replace('/[\r\n]+/', '', $value) ?? '';
}

/**
 * Escape output for HTML display
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
