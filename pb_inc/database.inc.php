<?php

/**
 * PowerBook - PHP Guestbook System
 * Database Connection (PDO)
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

/**
 * Get PDO database connection (singleton pattern)
 */
function getDatabase(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        global $config_sql_server, $config_sql_user, $config_sql_password, $config_sql_database;

        // Include MySQL configuration if not already loaded
        if (!isset($config_sql_server)) {
            require_once __DIR__ . '/mysql.inc.php';
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config_sql_server,
            $config_sql_database
        );

        try {
            $pdo = new PDO($dsn, $config_sql_user, $config_sql_password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                '<b>Fehler:</b> Datenbankverbindung konnte nicht hergestellt werden: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            );
        }
    }

    return $pdo;
}

/**
 * Verify and migrate password from Base64 to password_hash
 */
function verifyAndMigratePassword(string $input, string $stored, int $adminId): bool
{
    // Check if new format (password_hash)
    if (password_verify($input, $stored)) {
        return true;
    }

    // Fallback: Check old Base64 format
    $decoded = base64_decode($stored, true);
    if ($decoded !== false && $decoded === $input) {
        // Migration: Save as password_hash
        $pdo = getDatabase();
        $newHash = password_hash($input, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE pb_admins SET password = ? WHERE id = ?');
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
