<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Center Main Entry Point
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/../error-handler.inc.php';
require_once __DIR__ . '/../validation.inc.php';
require_once __DIR__ . '/layout.inc.php';
// BUG-010: Falls die DB noch keine reset_token-Spalten hat, nachziehen.
require_once __DIR__ . '/password_migrate.php';

// Allowed pages whitelist (LFI protection)
// BUG-004: Die Legacy-/Helper-/Platzhalter-Seiten (Email-Notifications, Paginierungs-Helper,
// Empty-Placeholder) sind keine eigenstaendigen Admin-Views und wurden deshalb aus der
// Whitelist entfernt — Direktaufruf faellt auf 'home' zurück.
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
        $login_message = 'CSRF-Token ungültig. Bitte die Seite neu laden.';
        logCsrfFailure('admin_login');
    } elseif (empty($name) || empty($password)) {
        $login_message = 'Bitte <b>Name <i>und</i> Passwort</b> angeben!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM {$pb_admin} WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            $login_message = 'Admin <b>' . e($name) . '</b> nicht in der Datenbank!';
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
                $login_message = 'Sie gaben ein <b>falsches Passwort</b> ein!';
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
    $head_message = 'Nicht eingeloggt. <a href="?page=login" class="alert-link">Hier einloggen</a>.';
} else {
    $head_message = 'Hallo, <b>' . e($welcome_admin) . '</b>! &middot; <a href="?page=logout" class="alert-link">Logout</a>';
}

// Login-/Eingeloggt-Status entscheidet, ob Subnav und Counts gezeigt werden.
$loggedIn = !empty($welcome_admin);

pb_admin_layout_header('PowerBook AdminCenter', [
    'headMessage' => $head_message,
    'publicCount' => $head_count_entries,
    'hiddenCount' => $head_count_unreleased,
    'showCounts' => $loggedIn,
    'showSubNav' => $loggedIn,
    'guestbookUrl' => '../../' . ((string) ($config_guestbook_name ?? 'pbook.php')),
]);

if ($installation_required) {
    ?>
    <section class="card border-warning shadow-sm mb-4">
        <header class="card-header bg-warning text-dark">
            <h2 class="h5 mb-0">Installation erforderlich</h2>
        </header>
        <div class="card-body text-center">
            <p class="lead text-danger fw-semibold">
                Die PowerBook-Datenbanktabellen wurden nicht gefunden!
            </p>
            <p class="mb-4">
                Bitte fuehren Sie zuerst die Installation aus, um die erforderlichen
                Datenbanktabellen zu erstellen.
            </p>
            <p class="mb-4">
                <a href="../../install_deu.php" class="btn btn-primary">
                    &raquo; Zur Installation &laquo;
                </a>
            </p>
            <p class="text-body-secondary mb-0"><small>
                Falls Sie die Installation bereits durchgefuehrt haben, pruefen Sie bitte
                die Datenbank-Konfiguration in <code>pb_inc/mysql.inc.php</code>.
            </small></p>
        </div>
    </section>
    <?php
} else {

    // Validate and include page (LFI protection)
    if (!in_array($page, $allowedPages, true)) {
        $page = 'home';
    }

    $pageFile = __DIR__ . '/' . $page . '.inc.php';

    if (!file_exists($pageFile)) {
        echo '<div class="alert alert-warning text-center" role="alert">Die Seite <b>' . e($page) . '</b> wurde nicht gefunden.</div>';
    } else {
        include $pageFile;
    }

}

pb_admin_layout_footer();
