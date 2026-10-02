<?php

/**
 * PowerBook - PHP Guestbook System
 * CSRF Protection Functions
 *
 * Das Token gilt für die ganze Sitzung. Es wird nur beim Anmelden neu
 * erzeugt (regenerateCsrfToken()), nicht nach jedem abgeschickten Formular.
 * So funktionieren auch mehrere geöffnete Tabs derselben Sitzung.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

/**
 * Startet die Sitzung, falls noch keine läuft.
 *
 * Das Sitzungs-Cookie ist immer HttpOnly und bei HTTPS automatisch Secure.
 * SameSite kommt aus der PHP-Konfiguration (.htaccess), sonst „Lax“.
 */
function pb_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    if (!headers_sent()) {
        $params = session_get_cookie_params();
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $sameSite = (string) $params['samesite'];

        session_set_cookie_params([
            'lifetime' => (int) $params['lifetime'],
            'path' => $params['path'],
            'domain' => (string) $params['domain'],
            'secure' => $https || (bool) $params['secure'],
            'httponly' => true,
            'samesite' => $sameSite !== '' ? $sameSite : 'Lax',
        ]);
    }

    session_start();
}

/**
 * Generate a CSRF token and store it in the session
 */
function generateCsrfToken(): string
{
    pb_session_start();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token against the session token
 */
function validateCsrfToken(?string $token): bool
{
    pb_session_start();

    if ($token === null || $token === '' || empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate a hidden input field with the CSRF token
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Regenerate the CSRF token.
 *
 * Nur beim Wechsel der Identität aufrufen (Anmelden), nicht nach jedem
 * erfolgreichen Formular: Sonst speichert ein zweiter Tab derselben Sitzung
 * nichts mehr.
 */
function regenerateCsrfToken(): string
{
    pb_session_start();

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    return $_SESSION['csrf_token'];
}

/**
 * Einheitliche Meldung, wenn ein Formular mit ungültigem oder fehlendem Token
 * ankommt (abgelaufene Sitzung, veraltete Seite). Klartext ohne HTML, vor der
 * Ausgabe wie jeden anderen Text escapen.
 */
function pb_csrf_failed_message(): string
{
    return 'Das Formular war abgelaufen. Bitte senden Sie es erneut ab.';
}
