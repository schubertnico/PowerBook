<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Center Main Entry Point
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/../error-handler.inc.php';
require_once __DIR__ . '/../validation.inc.php';

// Allowed pages whitelist (LFI protection)
// BUG-004: Die Legacy-/Helper-/Platzhalter-Seiten (Email-Notifications, Paginierungs-Helper,
// Empty-Placeholder) sind keine eigenstaendigen Admin-Views und wurden deshalb aus der
// Whitelist entfernt — Direktaufruf faellt auf 'home' zurueck.
$allowedPages = [
    'home', 'login', 'logout', 'license', 'admins',
    'entries', 'configuration', 'password', 'release',
    'entry', 'edit', 'statement',
];

// Get request parameters safely
$login = $_POST['login'] ?? '';
$logout = $_GET['logout'] ?? $_POST['logout'] ?? '';
$name = trim($_POST['name'] ?? '');
$password = $_POST['password'] ?? '';
$page = $_GET['page'] ?? 'home';

// Initialize admin variables
$admin_id = 0;
$admin_name = '';
$admin_password = '';
$admin_email = '';
$admin_config = 'N';
$admin_release = 'N';
$admin_entries = 'N';
$admin_admins = 'N';
$welcome_admin = '';
$login_message = '';

// Admin session array for included files
$admin_session = [
    'id' => 0,
    'name' => '',
    'email' => '',
    'config' => 'N',
    'release' => 'N',
    'entries' => 'N',
    'admins' => 'N',
];

// Process login
if ($login === 'yes') {
    // CSRF validation
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $login_message = 'CSRF-Token ungültig. Bitte die Seite neu laden.<br><br>';
        logCsrfFailure('admin_login');
    } elseif (empty($name) || empty($password)) {
        $login_message = 'Bitte <b>Name <i>und</i> Passwort</b> angeben!<br><br>';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM {$pb_admin} WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            $login_message = 'Admin <b>' . e($name) . '</b> nicht in der Datenbank!<br><br>';
            logFailedLogin($name);
        } else {
            // Verify password with migration support
            if (verifyAndMigratePassword($password, $admin['password'], (int) $admin['id'])) {
                // Login successful - store in session
                // BUG-014: Session-ID regenerieren, bevor Admin-Daten persistiert werden,
                // um Session-Fixation-Angriffe zu verhindern.
                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_logged_in'] = true;

                $admin_id = (int) $admin['id'];
                $admin_name = $admin['name'];
                $admin_email = $admin['email'];
                $admin_config = $admin['config'] ?? 'N';
                $admin_release = $admin['release'] ?? 'N';
                $admin_entries = $admin['entries'] ?? 'N';
                $admin_admins = $admin['admins'] ?? 'N';
                $welcome_admin = $admin_name;

                // Populate admin_session array for included files
                $admin_session = [
                    'id' => $admin_id,
                    'name' => $admin_name,
                    'email' => $admin_email,
                    'config' => $admin_config,
                    'release' => $admin_release,
                    'entries' => $admin_entries,
                    'admins' => $admin_admins,
                ];

                $login_message = 'Login erfolgreich, <b>' . e($name) . '</b>!';
                logSuccessfulLogin($name);
                regenerateCsrfToken();
            } else {
                $login_message = 'Sie gaben ein <b>falsches Passwort</b> ein!<br><br>';
                logFailedLogin($name);
            }
        }
    }
}

// Check existing session
if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM {$pb_admin} WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        $admin_id = (int) $admin['id'];
        $admin_name = $admin['name'];
        $admin_email = $admin['email'];
        $admin_config = $admin['config'] ?? 'N';
        $admin_release = $admin['release'] ?? 'N';
        $admin_entries = $admin['entries'] ?? 'N';
        $admin_admins = $admin['admins'] ?? 'N';
        $welcome_admin = $admin_name;

        // Populate admin_session array for included files
        $admin_session = [
            'id' => $admin_id,
            'name' => $admin_name,
            'email' => $admin_email,
            'config' => $admin_config,
            'release' => $admin_release,
            'entries' => $admin_entries,
            'admins' => $admin_admins,
        ];
    } else {
        // Invalid session - clear it
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_logged_in']);
    }
}

// Process logout
if ($logout === 'yes' || $page === 'logout') {
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_logged_in']);
    $welcome_admin = '';
    $admin_id = 0;
    $page = 'login';
}

// Get entry counts for header
$head_count_entries = 0;
$head_count_unreleased = 0;
$installation_required = false;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM {$pb_entries} WHERE status = 'R'");
    $head_count_entries = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM {$pb_entries} WHERE status = 'U'");
    $head_count_unreleased = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    // Check if tables don't exist
    if (str_contains($e->getMessage(), 'doesn\'t exist') || str_contains($e->getMessage(), 'Base table or view not found')) {
        $installation_required = true;
    } else {
        throw $e; // Re-throw other database errors
    }
}

// Build header message
if (empty($welcome_admin)) {
    $head_message = 'Nicht eingeloggt. <a href="?page=login">Hier einloggen</a>.';
} else {
    $head_message = 'Hallo, ' . e($welcome_admin) . '! [ <a href="?page=logout">Logout</a> ]';
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerBook - AdminCenter</title>
    <link rel="stylesheet" href="powerbook.css" type="text/css">
</head>
<body bgcolor="#002040" topmargin="10" bottommargin="10" leftmargin="10" rightmargin="10" text="#ffffff" link="#B5C3D9" vlink="#B5C3D9" alink="#B5C3D9" marginwidth="0" marginheight="0">

<table border="0" width="100%" cellpadding="4" cellspacing="1">
    <tr>
        <td width="500">
            <a href="?page=home" class="logo"><img src="powerbook.gif" width="461" height="179" border="0" alt="PowerBook"></a>
        </td>
        <td width="*" align="center" valign="center">
            <b>PowerBook AdminCenter</b><br>
            <small>
                <a href="?page=license">Lizenz</a> |
                <a href="../../<?= e($config_guestbook_name) ?>">Externe Seite</a> |
                <a href="https://github.com/schubertnico/PowerBook.git" target="_blank">GitHub</a>
            </small>
        </td>
    </tr>
</table>

<br><br><br>

<div align="right">
    <small>&raquo; <a href="?page=configuration">PowerBook Konfiguration</a> &laquo;</small>
</div>

<table border="0" cellpadding="4" cellspacing="1" width="100%" bgcolor="#6078A0">
    <tr bgcolor="#001329">
        <td valign="bottom" align="left">
            <b class="small">&raquo; <?= $head_message ?></b>
        </td>
        <td width="300" align="right">
            <b class="small">
                Öffentliche Einträge: <a href="?page=entries"><?= $head_count_entries ?></a><br>
                Versteckte Einträge: <a href="?page=release"><?= $head_count_unreleased ?></a>
            </b>
        </td>
    </tr>
    <tr bgcolor="#001329">
        <td colspan="2" align="center">
            <b>Administration</b><br>
            <small>
                <a href="?page=entries">Einträge</a> |
                <a href="?page=admins">Admins</a>
            </small>
        </td>
    </tr>
</table>
<br><br>

<table cellpadding="4" cellspacing="1" width="100%" bgcolor="#6078A0">
<?php

if ($installation_required) { ?>
<tr><td bgcolor="#3F5070" align="center">
    <b class="headline">I N S T A L L A T I O N &nbsp; &nbsp; E R F O R D E R L I C H</b>
</td></tr>
<tr><td bgcolor="#001F3F" valign="top">
    <div style="padding: 20px; text-align: center;">
        <p style="color: #FF6666; font-size: 14px;">
            <b>Die PowerBook-Datenbanktabellen wurden nicht gefunden!</b>
        </p>
        <p>
            Bitte führen Sie zuerst die Installation aus, um die erforderlichen<br>
            Datenbanktabellen zu erstellen.
        </p>
        <p style="margin-top: 20px;">
            <a href="../../install_deu.php" style="background: #3F5070; color: #FFFFFF; padding: 10px 20px; text-decoration: none; border: 1px solid #6078A0;">
                &raquo; Zur Installation &laquo;
            </a>
        </p>
        <p style="margin-top: 20px; font-size: 11px; color: #888888;">
            Falls Sie die Installation bereits durchgeführt haben, prüfen Sie bitte<br>
            die Datenbank-Konfiguration in <code>pb_inc/mysql.inc.php</code>.
        </p>
    </div>
</td></tr>
<?php } else {

    // Validate and include page (LFI protection)
    if (!in_array($page, $allowedPages, true)) {
        $page = 'home';
    }

    $pageFile = __DIR__ . '/' . $page . '.inc.php';

    if (!file_exists($pageFile)) {
        echo '<tr bgcolor="#001329"><td>';
        echo '<div align="center">Die Seite <b>' . e($page) . '</b> wurde nicht gefunden.</div>';
        echo '</td></tr>';
    } else {
        include $pageFile;
    }

}
?>
</table>

<br>
<center>
    <small>
        <a href="https://github.com/schubertnico/PowerBook.git" target="_blank">PowerBook</a>
        &copy; 2002 by <a href="mailto:expandable@powerscripts.org">Axel Habermaier</a>
        | PHP 8.4 Update: 2025
    </small>
</center>

</body>
</html>
