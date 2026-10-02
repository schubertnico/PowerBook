<?php
/**
 * PowerBook - PHP Guestbook System
 * AdminCenter: Einstieg, Anmeldung, Sitzung und Seitenaufbau
 *
 * Ablauf: Sitzung prüfen, An- und Abmelden verarbeiten, dann die Seite in
 * einen Puffer ausgeben. Erst danach entstehen Gruß, Zähler und Menü – so
 * zeigen sie nach einer Aktion schon den neuen Stand. Seiten können über
 * pb_admin_redirect() weiterleiten (Post/Redirect/Get).
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/../csrf.inc.php';
pb_session_start();

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/../error-handler.inc.php';
require_once __DIR__ . '/../validation.inc.php';
require_once __DIR__ . '/../functions.inc.php';
require_once __DIR__ . '/layout.inc.php';
require_once __DIR__ . '/auth.inc.php';

// Erlaubte Seiten (Schutz vor dem Einbinden beliebiger Dateien). Hilfsdateien
// wie entry.inc.php, pages.inc.php, layout.inc.php oder auth.inc.php sind
// keine eigenen Seiten.
$allowedPages = [
    'home', 'login', 'logout', 'license', 'admins',
    'entries', 'configuration', 'password', 'release',
    'edit', 'statement', 'account',
];

// Get request parameters
$publicPages = ['login', 'password', 'license'];
$pageTitles = [
    'home' => 'Start',
    'login' => 'Anmelden',
    'logout' => 'Abmelden',
    'license' => 'Lizenz',
    'admins' => 'Admins',
    'entries' => 'Einträge',
    'configuration' => 'Konfiguration',
    'password' => 'Passwort vergessen',
    'release' => 'Freischalten',
    'edit' => 'Eintrag bearbeiten',
    'statement' => 'Antwort',
    'account' => 'Mein Konto',
];

$requestedPage = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : '';
$page = in_array($requestedPage, $allowedPages, true) ? $requestedPage : 'home';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || ($_POST !== [] && !isset($_SERVER['REQUEST_METHOD']));
$now = time();

$pb_admin ??= 'pb_admins';
$pb_entries ??= 'pb_entries';
$pb_config ??= 'pb_config';

$loggedIn = false;
$loginName = '';
$admin_session = pb_admin_session_array([]);
$pbObLevel = ob_get_level();
$pbPageContent = '';
$pbState = 'ok';

// --- Datenbank vorhanden? --------------------------------------------------
try {
    $pdo->query("SELECT COUNT(*) FROM {$pb_entries}");
    $pdo->query("SELECT id FROM {$pb_admin} LIMIT 1");
} catch (PDOException $e) {
    if (pb_admin_is_missing_table($e)) {
        $pbState = 'install';
    } else {
        logDbError('AdminCenter start: ' . $e->getMessage());
        $pbState = 'dberror';
    }
}

if ($pbState !== 'ok') {
    $guestbookName = (string) ($config_guestbook_name ?? 'pbook.php');
    pb_admin_layout_header('PowerBook AdminCenter', [
        'loggedIn' => false,
        'guestbookUrl' => '../../' . ltrim($guestbookName !== '' ? $guestbookName : 'pbook.php', '/'),
    ]);
    if ($pbState === 'install') {
        ?>
    <section id="pbInstallRequired" class="card border-warning shadow-sm mb-4">
        <header class="card-header bg-warning text-dark">
            <h2 class="h5 mb-0">Installation erforderlich</h2>
        </header>
        <div class="card-body text-center">
            <p class="lead fw-semibold">Die Tabellen von PowerBook wurden in der Datenbank nicht gefunden.</p>
            <p class="mb-4">Bitte führen Sie zuerst die Installation aus. Sie legt die Tabellen und Ihr Administratorkonto an.</p>
            <p class="mb-4"><a href="../../install.php" class="btn btn-primary">Zur Installation</a></p>
            <p class="text-body-secondary mb-0"><small>
                Falls PowerBook bereits installiert ist, prüfen Sie bitte die Zugangsdaten in
                <code>pb_inc/mysql.inc.php</code>.
            </small></p>
        </div>
    </section>
        <?php
    } else {
        echo pb_admin_alert('Die Datenbank meldet einen Fehler. Bitte versuchen Sie es später noch einmal. Einzelheiten stehen im Fehlerprotokoll (<code>logs/error.log</code>).', 'danger', 'pbDatabaseError');
    }
    pb_admin_layout_footer();

    return;
}

try {
    // --- Bestehende Anmeldung prüfen --------------------------------------
    if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
        $account = pb_admin_load_account($pdo, $pb_admin, (int) $_SESSION['admin_id']);
        $problem = pb_admin_session_problem($account, $_SESSION, $now);
        if ($problem !== null || $account === null) {
            pb_admin_end_session();
            pb_admin_flash($problem[0] ?? 'info', $problem[1] ?? 'Bitte melden Sie sich an.');
            pb_admin_redirect('?page=login');
        }
        $_SESSION['pb_last_activity'] = $now;
        $loggedIn = true;
        $admin_session = pb_admin_session_array($account);
    }

    // --- Anmelden -----------------------------------------------------------
    if ($page === 'login' && $isPost && ($_POST['login'] ?? '') === 'yes') {
        if ($loggedIn) {
            pb_admin_redirect('?page=home');
        }
        $loginName = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $loginPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $loginIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        if (!validateCsrfToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
            logCsrfFailure('admin_login');
            pb_admin_flash('danger', pb_admin_csrf_message());
        } elseif ($loginName === '' || $loginPassword === '') {
            pb_admin_flash('danger', 'Bitte geben Sie Ihren Namen und Ihr Passwort ein.');
        } else {
            pb_admin_login_cleanup($pdo, $now);
            if (pb_admin_login_blocked($pdo, $loginIp, $now)) {
                logSecurityEvent('LOGIN_BLOCKED', ['ip' => $loginIp]);
                pb_admin_flash('danger', 'Zu viele Fehlversuche. Bitte warten Sie 15 Minuten.');
            } else {
                $account = pb_admin_verify_login($pdo, $pb_admin, $loginName, $loginPassword);
                if ($account !== null) {
                    pb_admin_start_session($account, $now);
                    pb_admin_login_succeeded($pdo, $loginIp);
                    logSuccessfulLogin((string) $account['name']);
                    pb_admin_flash('success', 'Hallo ' . $account['name'] . ', Sie sind jetzt angemeldet.');
                    pb_admin_redirect('?page=home');
                }
                pb_admin_login_failed($pdo, $loginIp, $loginName, $now);
                logFailedLogin($loginName);
                pb_admin_flash('danger', 'Anmeldung fehlgeschlagen: Name oder Passwort stimmt nicht.');
            }
        }
    }

    // --- Abmelden (nur per POST mit Token) ----------------------------------
    if ($page === 'logout') {
        if (!$loggedIn) {
            pb_admin_redirect('?page=login');
        }
        if ($isPost) {
            if (validateCsrfToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
                pb_admin_end_session();
                pb_admin_flash('success', 'Sie sind abgemeldet.');
                pb_admin_redirect('?page=login');
            }
            logCsrfFailure('admin_logout');
            pb_admin_flash('danger', pb_admin_csrf_message());
        }
    }

    // --- Zugriff ------------------------------------------------------------
    if (!$loggedIn && !in_array($page, $publicPages, true)) {
        if ($requestedPage !== '' && $requestedPage !== 'home') {
            pb_admin_flash('info', 'Bitte melden Sie sich an.');
        }
        pb_admin_redirect('?page=login');
    }
    if ($loggedIn && $page === 'login') {
        pb_admin_redirect('?page=home');
    }

    // --- Seite in den Puffer ausgeben --------------------------------------
    $pageFile = __DIR__ . '/' . $page . '.inc.php';
    ob_start();
    if (is_file($pageFile)) {
        include $pageFile;
    } else {
        pb_admin_card_open($pageTitles[$page]);
        echo pb_admin_alert('Diese Seite ist in dieser Installation nicht vorhanden.', 'warning');
        pb_admin_card_close();
    }
    $pbPageContent = (string) ob_get_clean();
} catch (PbAdminRedirect $redirect) {
    while (ob_get_level() > $pbObLevel) {
        ob_end_clean();
    }
    if (defined('POWERBOOK_TEST_MODE')) {
        throw $redirect;
    }
    header('Location: ' . $redirect->location, true, 303);

    exit;
}

// --- Erst jetzt: Gruß, Rechte und Zähler mit dem Stand nach der Aktion ------
if ($loggedIn) {
    $account = pb_admin_load_account($pdo, $pb_admin, (int) $admin_session['id']);
    if ($account !== null) {
        $admin_session = pb_admin_session_array($account);
    }
    if (!isset($_SESSION['pb_db_outdated'])) {
        $_SESSION['pb_db_outdated'] = pb_admin_db_outdated($pdo, $pb_config, $pb_admin);
    }
}

$pbCounts = ['public' => 0, 'pending' => 0];
if ($loggedIn) {
    try {
        $pbCounts = pb_admin_count_entries($pdo, $pb_entries);
    } catch (PDOException $e) {
        logDbError('AdminCenter counts: ' . $e->getMessage());
    }
}

$guestbookName = trim((string) ($config_guestbook_name ?? 'pbook.php'));
$guestbookUrl = preg_match('~^https?://~i', $guestbookName) === 1
    ? $guestbookName
    : '../../' . ltrim($guestbookName !== '' ? $guestbookName : 'pbook.php', '/');

$activePage = match ($page) {
    'edit', 'statement' => ($_GET['return'] ?? '') === 'release' ? 'release' : 'entries',
    default => $page,
};

pb_admin_layout_header($pageTitles[$page] . ' · PowerBook AdminCenter', [
    'loggedIn' => $loggedIn,
    'admin' => $admin_session,
    'activePage' => $activePage,
    'publicCount' => $pbCounts['public'],
    'pendingCount' => $pbCounts['pending'],
    'guestbookUrl' => $guestbookUrl,
    'dbOutdated' => $loggedIn && !empty($_SESSION['pb_db_outdated']),
]);

echo $pbPageContent;

pb_admin_layout_footer();
