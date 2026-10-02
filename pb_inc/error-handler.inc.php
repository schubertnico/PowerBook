<?php

/**
 * PowerBook - PHP Guestbook System
 * Protokolle (Logs) und Fehlerbehandlung
 *
 * Alle Protokolle liegen in logs/ (error.log, security.log) und werden ab
 * 5 MB rotiert (höchstens fünf ältere Dateien). Meldungen für Besucher und
 * Admins bleiben allgemein, Einzelheiten stehen nur im Protokoll.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

/**
 * Verzeichnis der Protokolle.
 */
function pb_log_dir(): string
{
    return dirname(__DIR__) . '/logs';
}

/**
 * Schreibt eine Zeile in ein Protokoll (rotiert vorher bei Bedarf).
 *
 * Zeilenumbrüche in der Meldung werden ersetzt, damit niemand über Eingaben
 * falsche Protokollzeilen erzeugen kann. Fehler beim Schreiben sind still.
 *
 * @param string $file Dateiname in logs/, z. B. "error.log"
 */
function pb_log_write(string $file, string $line): void
{
    $dir = pb_log_dir();
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0o750, true) && !is_dir($dir)) {
            return;
        }
        @file_put_contents(
            $dir . '/.htaccess',
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
        );
    }

    $path = $dir . '/' . basename($file);
    rotateLogIfNeeded($path);

    $line = str_replace(["\r\n", "\r", "\n"], ' ', $line);
    @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Kürzt einen eingegebenen Namen fürs Protokoll.
 *
 * Wer sein Passwort versehentlich ins Namensfeld tippt, soll es nicht im
 * Protokoll wiederfinden. Die ersten drei Zeichen plus ein kurzer Prüfwert
 * reichen, um wiederholte Versuche desselben Namens zu erkennen.
 */
function pb_log_shorten_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    $hash = substr(hash('sha256', mb_strtolower($name, 'UTF-8')), 0, 8);
    $short = mb_strlen($name, 'UTF-8') > 3 ? mb_substr($name, 0, 3, 'UTF-8') . '…' : $name;

    return $short . ' #' . $hash;
}

/**
 * Log a database error to the error log
 */
function logDbError(string $message): void
{
    pb_log_write('error.log', sprintf('[%s] PowerBook DB Error: %s', date('Y-m-d H:i:s'), $message));
}

/**
 * Log a security-related event to security.log.
 *
 * @param array<string, mixed> $context
 */
function logSecurityEvent(string $event, array $context = []): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

    pb_log_write('security.log', sprintf(
        '[%s] %s | IP: %s | UA: %s | Context: %s',
        date('Y-m-d H:i:s'),
        $event,
        is_string($ip) ? $ip : 'unknown',
        substr($userAgent, 0, 100),
        (string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
    ));
}

/**
 * Log a CSRF validation failure
 */
function logCsrfFailure(string $formName): void
{
    logSecurityEvent('CSRF_FAILURE', [
        'form' => $formName,
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
    ]);
}

/**
 * Log a failed login attempt (Name nur gekürzt, siehe pb_log_shorten_name()).
 */
function logFailedLogin(string $username): void
{
    logSecurityEvent('LOGIN_FAILED', [
        'username' => pb_log_shorten_name($username),
    ]);
}

/**
 * Log a successful login
 */
function logSuccessfulLogin(string $username): void
{
    logSecurityEvent('LOGIN_SUCCESS', [
        'username' => $username,
    ]);
}

/**
 * Log email sending errors (genutzt von pb_mail()).
 */
function logEmailError(string $message, string $context = ''): void
{
    pb_log_write('error.log', sprintf(
        '[%s] Email Error%s: %s',
        date('Y-m-d H:i:s'),
        $context !== '' ? " ({$context})" : '',
        $message
    ));
}

/**
 * Rotate log file if it exceeds size limit
 *
 * @param string $logFile Path to log file
 * @param int $maxSize Maximum size in bytes (default: 5MB)
 * @param int $keepFiles Number of rotated files to keep (default: 5)
 */
function rotateLogIfNeeded(string $logFile, int $maxSize = 5242880, int $keepFiles = 5): void
{
    if (!file_exists($logFile)) {
        return;
    }

    $size = @filesize($logFile);
    if ($size === false || $size < $maxSize) {
        return;
    }

    // Die älteste Datei entfällt, die übrigen rücken eine Nummer weiter.
    if (file_exists("{$logFile}.{$keepFiles}")) {
        @unlink("{$logFile}.{$keepFiles}");
    }
    for ($i = $keepFiles - 1; $i >= 1; $i--) {
        $oldFile = "{$logFile}.{$i}";
        if (file_exists($oldFile)) {
            @rename($oldFile, "{$logFile}." . ($i + 1));
        }
    }

    // Rotate current log
    @rename($logFile, "{$logFile}.1");
}
