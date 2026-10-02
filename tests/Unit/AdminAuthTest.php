<?php

/**
 * PowerBook - PHPUnit Tests
 * AdminCenter: Anmeldung, Anmeldedrossel, Sitzung (auth.inc.php) und die
 * Helfer des Rahmens (layout.inc.php).
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdminAuthTest extends TestCase
{
    private PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/admincenter/layout.inc.php';
        require_once POWERBOOK_ROOT . '/pb_inc/admincenter/auth.inc.php';
    }

    // ========================================================================
    // Konto finden und Passwort prüfen
    // ========================================================================

    #[Test]
    public function findsAccountByNameOrEmail(): void
    {
        $this->assertSame(1, (int) (pb_admin_find_login($this->pdo, 'pb_admins', 'Anke')['id'] ?? 0));
        $this->assertSame(2, (int) (pb_admin_find_login($this->pdo, 'pb_admins', ' jannik@example.org ')['id'] ?? 0));
        $this->assertNull(pb_admin_find_login($this->pdo, 'pb_admins', 'anke@example.org@x'));
        $this->assertNull(pb_admin_find_login($this->pdo, 'pb_admins', ''));
    }

    #[Test]
    public function verifiesPassword(): void
    {
        $this->assertNotNull(pb_admin_verify_login($this->pdo, 'pb_admins', 'Anke', 'Moewenblick2026'));
        $this->assertNotNull(pb_admin_verify_login($this->pdo, 'pb_admins', 'anke@example.org', 'Moewenblick2026'));
        $this->assertNull(pb_admin_verify_login($this->pdo, 'pb_admins', 'Anke', 'falsch'));
        $this->assertNull(pb_admin_verify_login($this->pdo, 'pb_admins', 'Gibtsnicht', 'Moewenblick2026'));
    }

    #[Test]
    public function sessionArrayContainsOnlyKnownRights(): void
    {
        $session = pb_admin_session_array(['id' => '2', 'name' => 'Jannik', 'email' => 'j@example.org', 'release' => 'Y', 'entries' => 'Y', 'config' => 'x', 'password' => 'geheim']);

        $this->assertSame(['id' => 2, 'name' => 'Jannik', 'email' => 'j@example.org', 'config' => 'N', 'release' => 'Y', 'entries' => 'Y', 'admins' => 'N'], $session);
    }

    // ========================================================================
    // Anmeldedrossel
    // ========================================================================

    #[Test]
    public function blocksAfterTenFailuresPerIp(): void
    {
        $now = 1_800_000_000;
        for ($i = 0; $i < 9; $i++) {
            pb_admin_login_failed($this->pdo, '198.51.100.7', 'Anke', $now);
        }
        $this->assertFalse(pb_admin_login_blocked($this->pdo, '198.51.100.7', $now));

        pb_admin_login_failed($this->pdo, '198.51.100.7', 'Anke', $now);
        $this->assertTrue(pb_admin_login_blocked($this->pdo, '198.51.100.7', $now));
        $this->assertFalse(pb_admin_login_blocked($this->pdo, '198.51.100.8', $now));
        $this->assertFalse(pb_admin_login_blocked($this->pdo, '198.51.100.7', $now + PB_LOGIN_WINDOW + 1));
    }

    #[Test]
    public function resetRowsDoNotCountButAccountRowsDo(): void
    {
        $now = 1_800_000_000;
        for ($i = 0; $i < 10; $i++) {
            $this->pdo->prepare('INSERT INTO pb_login_attempts (ip, name, time) VALUES (?, ?, ?)')->execute(['203.0.113.5', 'reset:1', $now]);
        }
        $this->assertSame(0, pb_admin_login_failures($this->pdo, '203.0.113.5', $now));

        $this->pdo->prepare('INSERT INTO pb_login_attempts (ip, name, time) VALUES (?, ?, ?)')->execute(['203.0.113.5', 'account:2', $now]);
        $this->assertSame(1, pb_admin_login_failures($this->pdo, '203.0.113.5', $now));
    }

    #[Test]
    public function cleanupRemovesOldRowsAndSuccessResetsIp(): void
    {
        $now = 1_800_000_000;
        pb_admin_login_failed($this->pdo, '192.0.2.1', 'alt', $now - PB_LOGIN_WINDOW - 5);
        pb_admin_login_failed($this->pdo, '192.0.2.1', 'neu', $now);
        pb_admin_login_failed($this->pdo, '192.0.2.2', 'anderer', $now);
        $this->pdo->prepare('INSERT INTO pb_login_attempts (ip, name, time) VALUES (?, ?, ?)')->execute(['192.0.2.1', 'reset:1', $now]);

        pb_admin_login_cleanup($this->pdo, $now);
        $this->assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM pb_login_attempts')->fetchColumn());

        pb_admin_login_succeeded($this->pdo, '192.0.2.1');
        $names = $this->pdo->query('SELECT name FROM pb_login_attempts ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['anderer', 'reset:1'], $names);
    }

    #[Test]
    public function storesShortenedName(): void
    {
        pb_admin_login_failed($this->pdo, '192.0.2.9', str_repeat('ä', 200), 1_800_000_000);

        $this->assertSame(50, mb_strlen((string) $this->pdo->query('SELECT name FROM pb_login_attempts')->fetchColumn()));
    }

    #[Test]
    public function throttleSurvivesMissingTable(): void
    {
        $this->pdo->exec('DROP TABLE pb_login_attempts');

        pb_admin_login_cleanup($this->pdo, 1_800_000_000);
        pb_admin_login_failed($this->pdo, '192.0.2.1', 'Anke', 1_800_000_000);
        $this->assertFalse(pb_admin_login_blocked($this->pdo, '192.0.2.1', 1_800_000_000));
        $this->assertTrue(pb_admin_db_outdated($this->pdo, 'pb_config', 'pb_admins'));
    }

    // ========================================================================
    // Sitzung
    // ========================================================================

    #[Test]
    public function sessionProblems(): void
    {
        $now = 1_800_000_000;
        $account = ['id' => 1, 'pw_changed' => 0];
        $session = ['pb_login_time' => $now - 100, 'pb_last_activity' => $now - 60];

        $this->assertNull(pb_admin_session_problem($account, $session, $now));
        $this->assertSame('Sie wurden abgemeldet, weil es Ihr Konto nicht mehr gibt.', pb_admin_session_problem(null, $session, $now)[1] ?? '');
        $this->assertSame('Ihr Passwort wurde geändert. Bitte melden Sie sich neu an.', pb_admin_session_problem(['pw_changed' => $now - 50], $session, $now)[1] ?? '');
        $this->assertNull(pb_admin_session_problem(['pw_changed' => $now - 100], $session, $now), 'Gleiche Zeit bleibt angemeldet.');
        $this->assertSame(
            'Sie wurden nach 60 Minuten ohne Aktivität abgemeldet. Bitte melden Sie sich neu an.',
            pb_admin_session_problem($account, ['pb_login_time' => $now - 9000, 'pb_last_activity' => $now - PB_SESSION_IDLE - 1], $now)[1] ?? ''
        );
        $this->assertNull(pb_admin_session_problem(['id' => 1], [], $now), 'Alte Sitzung ohne Zeitstempel und Spalte bleibt gültig.');
    }

    #[Test]
    public function startAndEndSession(): void
    {
        $_SESSION['csrf_token'] = 'alt';
        pb_admin_start_session(['id' => 2, 'name' => 'Jannik'], 1_800_000_000);

        $this->assertSame(2, $_SESSION['admin_id']);
        $this->assertTrue($_SESSION['admin_logged_in']);
        $this->assertSame(1_800_000_000, $_SESSION['pb_login_time']);
        $this->assertSame(1_800_000_000, $_SESSION['pb_last_activity']);
        $this->assertNotSame('alt', $_SESSION['csrf_token']);

        pb_admin_end_session();
        $this->assertArrayNotHasKey('admin_id', $_SESSION);
        $this->assertArrayNotHasKey('admin_logged_in', $_SESSION);
    }

    #[Test]
    public function detectsMissingTables(): void
    {
        $this->assertTrue(pb_admin_is_missing_table(new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'x.pb_entries' doesn't exist")));
        $this->assertTrue(pb_admin_is_missing_table(new \PDOException('SQLSTATE[HY000]: General error: 1 no such table: pb_entries')));
        $this->assertFalse(pb_admin_is_missing_table(new \PDOException('SQLSTATE[HY000] [2002] Connection refused')));
    }

    #[Test]
    public function currentDatabaseIsNotOutdated(): void
    {
        $this->assertFalse(pb_admin_db_outdated($this->pdo, 'pb_config', 'pb_admins'));
    }

    // ========================================================================
    // Rahmen: Meldungen, Weiterleitung, Menü
    // ========================================================================

    #[Test]
    public function flashIsShownOnceAndEscaped(): void
    {
        pb_admin_flash('error', 'Name <b>Anke</b> & Co.');

        $this->assertSame(['type' => 'danger', 'text' => 'Name <b>Anke</b> & Co.'], pb_admin_flash_take());
        $this->assertNull(pb_admin_flash_take());
        $this->assertSame(
            '<div id="pbMessage" class="alert alert-danger" role="alert" data-type="danger">Name &lt;b&gt;Anke&lt;/b&gt; &amp; Co.</div>',
            pb_admin_message_html('danger', 'Name <b>Anke</b> & Co.')
        );
        $this->assertStringContainsString('role="status"', pb_admin_message_html('success', 'Gespeichert.'));
    }

    #[Test]
    public function redirectThrowsWithLocation(): void
    {
        try {
            pb_admin_redirect('?page=release');
            $this->fail('Keine Weiterleitung');
        } catch (\PbAdminRedirect $redirect) {
            $this->assertSame('?page=release', $redirect->location);
        }
    }

    #[Test]
    public function alertAcceptsOptionalId(): void
    {
        $this->assertSame('<div id="pbX" class="alert alert-danger" role="alert">Fehler</div>', pb_admin_alert('Fehler', 'error', 'pbX'));
        $this->assertSame('<div class="alert alert-info" role="status">Hinweis</div>', pb_admin_alert('Hinweis', 'unbekannt'));
    }

    #[Test]
    public function timeGetsUhrOnlyForPlainTimes(): void
    {
        $ts = mktime(20, 42, 0, 10, 2, 2026);

        $this->assertSame('20:42 Uhr', pb_admin_format_time('H:i', $ts));
        $this->assertSame('20.42 Uhr', pb_admin_format_time('H.i', $ts));
        $this->assertSame('20:42 Uhr', pb_admin_format_time('H:i \U\h\r', $ts));
        $this->assertSame('Freitag, 2. Oktober 2026', pb_admin_format_date('l, j. F Y', $ts));
        $this->assertSame('', pb_admin_format_date('d.m.Y', 0));
    }

    #[Test]
    public function headerShowsMenuByRightsWithActiveItem(): void
    {
        ob_start();
        pb_admin_layout_header('Einträge · PowerBook AdminCenter', [
            'loggedIn' => true,
            'admin' => ['name' => 'Jannik', 'release' => 'Y', 'entries' => 'Y', 'config' => 'N', 'admins' => 'N'],
            'activePage' => 'entries',
            'publicCount' => 8,
            'pendingCount' => 2,
            'guestbookUrl' => '../../pbook.php',
        ]);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('<title>Einträge · PowerBook AdminCenter</title>', $html);
        foreach (['pbMenuHome', 'pbMenuEntries', 'pbMenuRelease', 'pbMenuAccount', 'pbMenuLicense', 'pbMenuGuestbook', 'pbLogout', 'pbStatusLine'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, $id);
        }
        $this->assertStringNotContainsString('id="pbMenuAdmins"', $html);
        $this->assertStringNotContainsString('id="pbMenuConfig"', $html);
        $this->assertStringContainsString('id="pbMenuEntries" class="nav-link active" aria-current="page"', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('~id="pbCountPublic"[^>]*>8<~', $html);
        $this->assertMatchesRegularExpression('~id="pbCountPending"[^>]*>2<~', $html);
        $this->assertStringContainsString('Angemeldet als <b id="pbStatusName">Jannik</b>', $html);
        $this->assertMatchesRegularExpression('~<form action="\?page=logout" method="post"~', $html);
        $this->assertStringContainsString('integrity="sha384-', $html);
        $this->assertStringNotContainsString('pbUpdateHint', $html);
        $this->assertStringNotContainsString('Login', $html);
    }

    #[Test]
    public function headerLoggedOutHasNoMenuAndShowsFlash(): void
    {
        pb_admin_flash('success', 'Sie sind abgemeldet.');
        ob_start();
        pb_admin_layout_header('Anmelden · PowerBook AdminCenter', ['loggedIn' => false]);
        $html = (string) ob_get_clean();

        $this->assertStringNotContainsString('pbMenuHome', $html);
        $this->assertStringNotContainsString('pbStatusLine', $html);
        $this->assertStringNotContainsString('pbCountPublic', $html);
        $this->assertStringContainsString('id="pbMenuGuestbook"', $html);
        $this->assertStringContainsString('<div id="pbMessage" class="alert alert-success" role="status" data-type="success">Sie sind abgemeldet.</div>', $html);
    }

    #[Test]
    public function headerShowsUpdateHint(): void
    {
        ob_start();
        pb_admin_layout_header('Start · PowerBook AdminCenter', ['loggedIn' => true, 'admin' => ['name' => 'Anke'], 'dbOutdated' => true]);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('<div id="pbUpdateHint" class="alert alert-warning">Die Datenbank ist noch auf einem älteren Stand. Rufen Sie <a href="../../update.php">update.php</a> auf.</div>', $html);
    }

    #[Test]
    public function footerHasNoEnglish(): void
    {
        ob_start();
        pb_admin_layout_footer();
        $html = (string) ob_get_clean();

        $this->assertStringNotContainsString('powered by', $html);
        $this->assertStringContainsString('integrity="sha384-', $html);
    }

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('CREATE TABLE pb_config (id INTEGER PRIMARY KEY, title TEXT DEFAULT "", mail_from TEXT DEFAULT "")');
        $this->pdo->exec('CREATE TABLE pb_admins (
            id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT,
            config TEXT, admins TEXT, entries TEXT, "release" TEXT, pw_changed INTEGER DEFAULT 0
        )');
        $this->pdo->exec('CREATE TABLE pb_login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT, name TEXT, time INTEGER)');
        $hash = password_hash('Moewenblick2026', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO pb_admins (id, name, email, password, config, admins, entries, "release") VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([1, 'Anke', 'anke@example.org', $hash, 'Y', 'Y', 'Y', 'Y']);
        $stmt->execute([2, 'Jannik', 'jannik@example.org', $hash, 'N', 'N', 'Y', 'Y']);
        unset($_SESSION['pb_flash']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['pb_flash'], $_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_logged_in'], $_SESSION['pb_login_time'], $_SESSION['pb_last_activity']);
    }
}
