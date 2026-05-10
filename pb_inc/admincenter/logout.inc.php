<?php
/**
 * PowerBook - PHP Guestbook System
 * Logout Handler
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

// Variables from parent scope
/** @var array<string, string> $admin_session */
$logout = $_GET['logout'] ?? '';
$message = '';
$messageType = 'info';

if (!isset($admin_session) || empty($admin_session)) {
    $message = 'Sie sind nicht eingeloggt!';
    $messageType = 'warning';
} elseif ($logout === 'yes') {
    // Destroy session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $sessionName = session_name();
        setcookie(
            $sessionName !== false ? $sessionName : 'PHPSESSID',
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();

    $message = 'Logout erfolgreich! <a class="alert-link" href="index.php">Zur Login-Seite</a>';
    $messageType = 'success';
} else {
    $adminName = e($admin_session['name'] ?? 'Admin');
    $message = "Sind Sie sicher, dass Sie sich ausloggen möchten, <b>{$adminName}</b>?";
    $messageType = 'warning';
}

pb_admin_card_open('Logout');

echo pb_admin_alert($message, $messageType);

if (!empty($admin_session) && $logout !== 'yes') {
    ?>
<div class="d-flex flex-wrap gap-2">
    <a href="?page=logout&amp;logout=yes" class="btn btn-danger">Ja, ausloggen</a>
    <a href="?page=home" class="btn btn-outline-secondary">Abbrechen</a>
</div>
<?php }

pb_admin_card_close();
