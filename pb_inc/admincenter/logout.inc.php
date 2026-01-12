<?php
/**
 * PowerBook - PHP Guestbook System
 * Logout Handler
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Variables from parent scope
/** @var array<string, string> $admin_session */
$logout = $_GET['logout'] ?? '';
$message = '';

if (!isset($admin_session) || empty($admin_session)) {
    $message = 'Sie sind nicht eingeloggt!';
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

    $message = 'Logout erfolgreich! <a href="index.php">Zur Login-Seite</a>';
} else {
    $adminName = e($admin_session['name'] ?? 'Admin');
    $message = "Sind Sie sicher, dass Sie sich ausloggen möchten, <b>{$adminName}</b>?<br><br>";
    $message .= '&raquo; <a href="?page=logout&logout=yes">Ja, ausloggen</a> &laquo;';
}
?>

<tr><td bgcolor="#3F5070" align="center">
    <b class="headline">L O G O U T</b>
</td></tr>

<tr><td bgcolor="#001F3F" valign="top">

<div style="padding: 20px; text-align: center;">
    <?= $message ?>
</div>

</td></tr>
