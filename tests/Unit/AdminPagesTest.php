<?php

/**
 * PowerBook - PHPUnit Tests
 * Admin Center Pages Integration Tests
 *
 * Tests ALL admin center pages by including them with proper global state
 * and an SQLite test database.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdminPagesTest extends TestCase
{
    private static PDO $pdo;

    private static string $pbAdmin;

    private static string $pbEntries;

    private static string $pbConfig;

    public static function setUpBeforeClass(): void
    {
        // Create a fresh SQLite in-memory database for this test class
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE pb_config (
            id INTEGER PRIMARY KEY,
            "release" TEXT DEFAULT "R",
            send_email TEXT DEFAULT "N",
            email TEXT DEFAULT "admin@test.com",
            date TEXT DEFAULT "d.m.Y",
            time TEXT DEFAULT "H:i",
            spam_check INTEGER DEFAULT 60,
            color TEXT DEFAULT "#FF0000",
            show_entries INTEGER DEFAULT 10,
            guestbook_name TEXT DEFAULT "pbook.php",
            admin_url TEXT DEFAULT "",
            text_format TEXT DEFAULT "Y",
            icons TEXT DEFAULT "Y",
            smilies TEXT DEFAULT "Y",
            icq TEXT DEFAULT "N",
            pages TEXT DEFAULT "D",
            use_thanks TEXT DEFAULT "N",
            language TEXT DEFAULT "D",
            design TEXT DEFAULT "(#ICON#)(#DATE#)(#TIME#)(#EMAIL_NAME#)(#TEXT#)(#URL#)(#ICQ#)",
            thanks_title TEXT DEFAULT "",
            thanks TEXT DEFAULT "",
            statements TEXT DEFAULT "Y"
        )');

        $pdo->exec('CREATE TABLE pb_admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            password TEXT NOT NULL,
            config TEXT DEFAULT "N",
            admins TEXT DEFAULT "N",
            entries TEXT DEFAULT "N",
            "release" TEXT DEFAULT "N",
            reset_token TEXT DEFAULT NULL,
            reset_token_expires INTEGER DEFAULT NULL
        )');

        $pdo->exec('CREATE TABLE pb_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT DEFAULT "",
            text TEXT NOT NULL,
            date INTEGER DEFAULT 0,
            homepage TEXT DEFAULT "",
            icq TEXT DEFAULT "",
            ip TEXT DEFAULT "",
            status TEXT DEFAULT "R",
            icon TEXT DEFAULT "",
            smilies TEXT DEFAULT "N",
            statement TEXT DEFAULT "",
            statement_by TEXT DEFAULT ""
        )');

        // Insert default config
        $pdo->exec('INSERT INTO pb_config (id) VALUES (1)');

        // Insert test admin
        $pdo->exec("INSERT INTO pb_admins (id, name, email, password, config, admins, entries, \"release\")
            VALUES (1, 'SuperAdmin', 'admin@test.com', '" . password_hash('test123', PASSWORD_DEFAULT) . "', 'Y', 'Y', 'Y', 'Y')");

        self::$pdo = $pdo;
        self::$pbConfig = 'pb_config';
        self::$pbAdmin = 'pb_admins';
        self::$pbEntries = 'pb_entries';

        // Make accessible via $GLOBALS for included files
        $GLOBALS['pdo'] = $pdo;
        $GLOBALS['pb_config'] = 'pb_config';
        $GLOBALS['pb_admin'] = 'pb_admins';
        $GLOBALS['pb_entries'] = 'pb_entries';
    }

    protected function setUp(): void
    {
        // Ensure GLOBALS are set for each test (PHPUnit may backup/restore)
        $GLOBALS['pdo'] = self::$pdo;
        $GLOBALS['pb_config'] = self::$pbConfig;
        $GLOBALS['pb_admin'] = self::$pbAdmin;
        $GLOBALS['pb_entries'] = self::$pbEntries;

        // Reset superglobals before each test
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
    }

    /**
     * Render an admin page by including it with the given variables.
     *
     * @param array<string, mixed> $vars Variables to extract into scope
     */
    /**
     * Render an admin page by including it with the given variables.
     * Source files with function definitions use function_exists() guards,
     * so they can be safely re-included.
     *
     * @param array<string, mixed> $vars Variables to extract into scope
     */
    private function renderAdminPage(string $file, array $vars = []): string
    {
        $pdo = self::$pdo;
        $pb_admin = self::$pbAdmin;
        $pb_entries = self::$pbEntries;
        $pb_config = self::$pbConfig;

        extract($vars);

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/' . $file;

        return (string) ob_get_clean();
    }

    /**
     * Generate a valid CSRF token for form submissions.
     */
    private function getCsrfToken(): string
    {
        return generateCsrfToken();
    }

    /**
     * Get the PDO instance for direct database access in tests.
     */
    private function db(): PDO
    {
        return self::$pdo;
    }

    // ========================================================================
    // 1. home.inc.php
    // ========================================================================

    #[Test]
    public function homePageOutputsWelcomeHeadline(): void
    {
        $output = $this->renderAdminPage('home.inc.php', [
            'head_count_entries' => 42,
            'head_count_unreleased' => 5,
        ]);

        $this->assertStringContainsString('W I L L K O M M E N', $output);
        $this->assertStringContainsString('Willkommen im AdminCenter', $output);
    }

    #[Test]
    public function homePageShowsPhpVersion(): void
    {
        $output = $this->renderAdminPage('home.inc.php', [
            'head_count_entries' => 10,
            'head_count_unreleased' => 0,
        ]);

        $this->assertStringContainsString(PHP_VERSION, $output);
        $this->assertStringContainsString('PowerBook Version', $output);
    }

    #[Test]
    public function homePageShowsEntryCounts(): void
    {
        $output = $this->renderAdminPage('home.inc.php', [
            'head_count_entries' => 99,
            'head_count_unreleased' => 7,
        ]);

        $this->assertStringContainsString('99', $output);
        $this->assertStringContainsString('7', $output);
    }

    // ========================================================================
    // 2. login.inc.php
    // ========================================================================

    #[Test]
    public function loginPageShowsFormWhenNotLoggedIn(): void
    {
        $output = $this->renderAdminPage('login.inc.php', [
            'welcome_admin' => '',
            'login_message' => '',
            'name' => '',
        ]);

        $this->assertStringContainsString('L O G I N', $output);
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('name="password"', $output);
        $this->assertStringContainsString('Ein Login ist erforderlich', $output);
    }

    #[Test]
    public function loginPageShowsAlreadyLoggedIn(): void
    {
        $output = $this->renderAdminPage('login.inc.php', [
            'welcome_admin' => 'SuperAdmin',
            'login_message' => '',
            'name' => '',
        ]);

        $this->assertStringContainsString('bereits eingeloggt', $output);
        $this->assertStringContainsString('SuperAdmin', $output);
        $this->assertStringNotContainsString('<form', $output);
    }

    #[Test]
    public function loginPageShowsLoginMessage(): void
    {
        $output = $this->renderAdminPage('login.inc.php', [
            'welcome_admin' => '',
            'login_message' => 'Falsches Passwort!',
            'name' => 'TestUser',
        ]);

        $this->assertStringContainsString('Falsches Passwort!', $output);
        $this->assertStringContainsString('TestUser', $output);
    }

    // ========================================================================
    // 3. logout.inc.php
    // ========================================================================

    #[Test]
    public function logoutPageShowsNotLoggedInMessage(): void
    {
        $output = $this->renderAdminPage('logout.inc.php', [
            'admin_session' => [],
        ]);

        $this->assertStringContainsString('L O G O U T', $output);
        $this->assertStringContainsString('nicht eingeloggt', $output);
    }

    #[Test]
    public function logoutPageShowsConfirmation(): void
    {
        $output = $this->renderAdminPage('logout.inc.php', [
            'admin_session' => ['name' => 'TestAdmin'],
        ]);

        $this->assertStringContainsString('Sind Sie sicher', $output);
        $this->assertStringContainsString('TestAdmin', $output);
        $this->assertStringContainsString('logout=yes', $output);
    }

    #[Test]
    public function logoutPagePerformsLogout(): void
    {
        // Ensure session is active for the logout test
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_GET['logout'] = 'yes';
        $output = $this->renderAdminPage('logout.inc.php', [
            'admin_session' => ['name' => 'TestAdmin'],
        ]);

        $this->assertStringContainsString('Logout erfolgreich', $output);

        // Restart session for subsequent tests
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // ========================================================================
    // 4. license.inc.php
    // ========================================================================

    #[Test]
    public function licensePageShowsMitLicense(): void
    {
        $output = $this->renderAdminPage('license.inc.php');

        $this->assertStringContainsString('M I T', $output);
        $this->assertStringContainsString('MIT License', $output);
        $this->assertStringContainsString('Permission is hereby granted', $output);
    }

    #[Test]
    public function licensePageShowsCopyrightHolders(): void
    {
        $output = $this->renderAdminPage('license.inc.php');

        $this->assertStringContainsString('Axel', $output);
        $this->assertStringContainsString('Nico Schubert', $output);
    }

    #[Test]
    public function licensePageContainsRepositoryLink(): void
    {
        $output = $this->renderAdminPage('license.inc.php');

        $this->assertStringContainsString('github.com/schubertnico/PowerBook', $output);
    }

    // ========================================================================
    // 5. empty.inc.php
    // ========================================================================

    #[Test]
    public function emptyPageOutputsTableRows(): void
    {
        $output = $this->renderAdminPage('empty.inc.php');

        $this->assertStringContainsString('<tr>', $output);
        $this->assertStringContainsString('&nbsp;', $output);
    }

    #[Test]
    public function emptyPageHasMinimalContent(): void
    {
        $output = $this->renderAdminPage('empty.inc.php');

        $this->assertStringNotContainsString('Willkommen', $output);
        $this->assertStringNotContainsString('Login', $output);
    }

    // ========================================================================
    // 6. date-help.php
    // ========================================================================

    #[Test]
    public function dateHelpShowsDateFormats(): void
    {
        $_GET['section'] = 'date';

        $output = $this->renderAdminPage('date-help.php');

        $this->assertStringContainsString('Datumsformate', $output);
        $this->assertStringContainsString('Tag des Monats', $output);
        $this->assertStringContainsString('Monat', $output);
        $this->assertStringContainsString('Jahr', $output);
    }

    #[Test]
    public function dateHelpShowsTimeFormats(): void
    {
        $_GET['section'] = 'time';

        $output = $this->renderAdminPage('date-help.php');

        $this->assertStringContainsString('Zeitformate', $output);
        $this->assertStringContainsString('Stunden', $output);
        $this->assertStringContainsString('Minuten', $output);
        $this->assertStringContainsString('Sekunden', $output);
    }

    #[Test]
    public function dateHelpShowsNoSectionMessage(): void
    {
        $_GET['section'] = '';

        $output = $this->renderAdminPage('date-help.php');

        $this->assertStringContainsString('Kein Abschnitt', $output);
    }

    // ========================================================================
    // 7. entries.inc.php
    // ========================================================================

    #[Test]
    public function entriesPageDeniesWithoutPermission(): void
    {
        $output = $this->renderAdminPage('entries.inc.php', [
            'admin_session' => ['entries' => 'N'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ]);

        $this->assertStringContainsString('keine Berechtigung', $output);
    }

    #[Test]
    public function entriesPageShowsNoEntriesMessage(): void
    {
        $this->db()->exec('DELETE FROM pb_entries');

        $output = $this->renderAdminPage('entries.inc.php', [
            'admin_session' => ['entries' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ]);

        $this->assertStringContainsString('Keine Eintr', $output);
    }

    #[Test]
    public function entriesPageListsEntries(): void
    {
        $this->db()->exec('DELETE FROM pb_entries');
        $this->db()->exec("INSERT INTO pb_entries (name, text, date, email, ip, status) VALUES ('TestUser', 'Hello World', " . time() . ", 'test@test.com', '127.0.0.1', 'R')");
        $this->db()->exec("INSERT INTO pb_entries (name, text, date, email, ip, status) VALUES ('AnotherUser', 'Second entry', " . time() . ", 'other@test.com', '192.168.1.1', 'R')");

        $output = $this->renderAdminPage('entries.inc.php', [
            'admin_session' => ['entries' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ]);

        $this->assertStringContainsString('Hello World', $output);
        $this->assertStringContainsString('Second entry', $output);
        $this->assertStringContainsString('Bearbeiten/', $output);
        $this->assertStringContainsString('Statement', $output);

        $this->db()->exec('DELETE FROM pb_entries');
    }

    // ========================================================================
    // 8. entry.inc.php
    // ========================================================================

    #[Test]
    public function entryPageProcessesBasicEntry(): void
    {
        $entry = [
            'id' => 1,
            'name' => 'TestUser',
            'email' => 'test@test.com',
            'text' => 'Hello entry text',
            'date' => 1700000000,
            'homepage' => 'https://example.com',
            'icq' => '',
            'ip' => '10.0.0.1',
            'status' => 'R',
            'icon' => 'no',
            'smilies' => 'N',
            'statement' => '',
            'statement_by' => '',
        ];

        $config_icons = 'N';
        $config_text_format = 'N';
        $config_smilies = 'N';
        $config_icq = 'N';
        $db_statement = 'N';

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';
        ob_get_clean();

        $this->assertSame('10.0.0.1', $ip);
        $this->assertSame('', $show_icon);
        $this->assertStringContainsString('Homepage', $url);
        $this->assertStringContainsString('test@test.com', $email_name);
        $this->assertNotEmpty($date);
        $this->assertNotEmpty($time);
    }

    #[Test]
    public function entryPageShowsIconWhenEnabled(): void
    {
        $entry = [
            'id' => 2,
            'name' => 'IconUser',
            'email' => '',
            'text' => 'Icon test',
            'date' => 1700000000,
            'homepage' => '',
            'icq' => '',
            'ip' => '',
            'status' => 'R',
            'icon' => 'happy1',
            'smilies' => 'N',
            'statement' => '',
            'statement_by' => '',
        ];

        $config_icons = 'Y';
        $config_text_format = 'N';
        $config_smilies = 'N';
        $config_icq = 'N';
        $db_statement = 'N';

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';
        ob_get_clean();

        $this->assertStringContainsString('happy1.gif', $show_icon);
        $this->assertSame('unknown', $ip);
        $this->assertStringContainsString('Keine Homepage', $url);
    }

    #[Test]
    public function entryPageProcessesBBCodeAndSmilies(): void
    {
        $entry = [
            'id' => 3,
            'name' => 'BBUser',
            'email' => '',
            'text' => '[b]Bold text[/b] and :)',
            'date' => 1700000000,
            'homepage' => '',
            'icq' => '12345678',
            'ip' => '1.2.3.4',
            'status' => 'R',
            'icon' => 'no',
            'smilies' => 'Y',
            'statement' => '',
            'statement_by' => '',
        ];

        $config_icons = 'N';
        $config_text_format = 'Y';
        $config_smilies = 'Y';
        $config_icq = 'Y';
        $db_statement = 'N';

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';
        ob_get_clean();

        $this->assertStringContainsString('<b>Bold text</b>', $entryText);
        $this->assertStringContainsString('happy1.gif', $entryText);
        $this->assertStringContainsString('12345678', $show_icq);
    }

    #[Test]
    public function entryPageShowsStatement(): void
    {
        $entry = [
            'id' => 4,
            'name' => 'StatUser',
            'email' => '',
            'text' => 'Original text',
            'date' => 1700000000,
            'homepage' => '',
            'icq' => '',
            'ip' => '',
            'status' => 'R',
            'icon' => 'no',
            'smilies' => 'N',
            'statement' => 'Admin response here',
            'statement_by' => 'SuperAdmin',
        ];

        $config_icons = 'N';
        $config_text_format = 'N';
        $config_smilies = 'N';
        $config_icq = 'N';
        $db_statement = 'Y';

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';
        ob_get_clean();

        $this->assertStringContainsString('Admin response here', $entry['text']);
        $this->assertStringContainsString('SuperAdmin', $entry['text']);
        $this->assertStringContainsString('Statement', $entry['text']);
    }

    // ========================================================================
    // 9. configuration.inc.php
    // ========================================================================

    #[Test]
    public function configurationPageDeniesWithoutPermission(): void
    {
        $output = $this->renderAdminPage('configuration.inc.php', [
            'admin_session' => ['config' => 'N'],
        ]);

        $this->assertStringContainsString('keine Berechtigung', $output);
    }

    #[Test]
    public function configurationPageShowsForm(): void
    {
        $output = $this->renderAdminPage('configuration.inc.php', [
            'admin_session' => ['config' => 'Y'],
        ]);

        $this->assertStringContainsString('K O N F I G U R A T I O N', $output);
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('change_email', $output);
        $this->assertStringContainsString('change_date', $output);
        $this->assertStringContainsString('Konfiguration speichern', $output);
    }

    #[Test]
    public function configurationPageUpdatesConfig(): void
    {
        $token = $this->getCsrfToken();

        $_POST = [
            'action' => 'update',
            'csrf_token' => $token,
            'change_release' => 'R',
            'change_send_email' => 'N',
            'change_email' => 'updated@test.com',
            'change_date' => 'd.m.Y',
            'change_time' => 'H:i',
            'change_spam_check' => '120',
            'change_color' => '#00FF00',
            'change_show_entries' => '20',
            'change_guestbook_name' => 'guestbook.php',
            'change_admin_url' => '',
            'change_text_format' => 'Y',
            'change_icons' => 'Y',
            'change_smilies' => 'Y',
            'change_icq' => 'N',
            'change_pages' => 'D',
            'change_use_thanks' => 'N',
            'change_language' => 'eng',
            'change_design' => '(#ICON#)(#DATE#)(#TIME#)(#TEXT#)',
            'change_thanks_title' => '',
            'change_thanks' => '',
            'change_statements' => 'Y',
        ];

        $output = $this->renderAdminPage('configuration.inc.php', [
            'admin_session' => ['config' => 'Y'],
        ]);

        $this->assertStringContainsString('erfolgreich aktualisiert', $output);

        // Verify the database was updated
        $stmt = $this->db()->query('SELECT email, show_entries FROM pb_config LIMIT 1');
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('updated@test.com', $config['email']);
        $this->assertEquals(20, $config['show_entries']);

        // Restore defaults
        $this->db()->exec("UPDATE pb_config SET email = 'admin@test.com', show_entries = 10");
    }

    #[Test]
    public function configurationPageShowsValidationErrors(): void
    {
        $token = $this->getCsrfToken();

        $_POST = [
            'action' => 'update',
            'csrf_token' => $token,
            'change_release' => 'R',
            'change_send_email' => 'N',
            'change_email' => '',
            'change_date' => '',
            'change_time' => 'H:i',
            'change_spam_check' => '30',
            'change_color' => '#FF0000',
            'change_show_entries' => '10',
            'change_guestbook_name' => 'pbook.php',
            'change_admin_url' => '',
            'change_text_format' => 'Y',
            'change_icons' => 'Y',
            'change_smilies' => 'Y',
            'change_icq' => 'N',
            'change_pages' => 'D',
            'change_use_thanks' => 'N',
            'change_design' => '(#TEXT#)',
            'change_thanks_title' => '',
            'change_thanks' => '',
            'change_statements' => 'Y',
        ];

        $output = $this->renderAdminPage('configuration.inc.php', [
            'admin_session' => ['config' => 'Y'],
        ]);

        $this->assertStringContainsString('E-Mail-Adresse', $output);
        $this->assertStringContainsString('Datumsformat', $output);
    }

    // ========================================================================
    // 10. edit.inc.php
    // ========================================================================

    #[Test]
    public function editPageDeniesWithoutPermission(): void
    {
        $output = $this->renderAdminPage('edit.inc.php', [
            'admin_session' => ['entries' => 'N'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('keine Berechtigung', $output);
    }

    #[Test]
    public function editPageShowsErrorForNoId(): void
    {
        $output = $this->renderAdminPage('edit.inc.php', [
            'admin_session' => ['entries' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('ID unbekannt', $output);
    }

    #[Test]
    public function editPageShowsEditForm(): void
    {
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, email, ip, status) VALUES (100, 'EditTestUser', 'Edit me', " . time() . ", 'edit@test.com', '10.0.0.1', 'R')");

        $_GET['edit_id'] = '100';

        $output = $this->renderAdminPage('edit.inc.php', [
            'admin_session' => ['entries' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('B E A R B E I T E N', $output);
        $this->assertStringContainsString('EditTestUser', $output);
        $this->assertStringContainsString('Edit me', $output);
        $this->assertStringContainsString('Speichern', $output);

        $this->db()->exec('DELETE FROM pb_entries WHERE id = 100');
    }

    #[Test]
    public function editPageUpdatesEntry(): void
    {
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, email, ip, status) VALUES (101, 'OldName', 'Old text', " . time() . ", 'old@test.com', '10.0.0.1', 'R')");

        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'update',
            'csrf_token' => $token,
            'edit_id' => '101',
            'edit_name' => 'NewName',
            'edit_email' => 'new@test.com',
            'edit_text' => 'Updated text',
            'edit_homepage' => '',
            'edit_icq' => '',
            'edit_icon' => 'no',
            'edit_status' => 'R',
            'edit_smilies' => 'N',
        ];

        $output = $this->renderAdminPage('edit.inc.php', [
            'admin_session' => ['entries' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('erfolgreich bearbeitet', $output);

        // Verify DB update
        $stmt = $this->db()->prepare('SELECT name, text FROM pb_entries WHERE id = 101');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('NewName', $row['name']);
        $this->assertSame('Updated text', $row['text']);

        $this->db()->exec('DELETE FROM pb_entries WHERE id = 101');
    }

    #[Test]
    public function editPageConfirmsDelete(): void
    {
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date) VALUES (102, 'DeleteMe', 'To be deleted', " . time() . ')');

        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'confirm_delete',
            'csrf_token' => $token,
            'edit_id' => '102',
        ];

        $output = $this->renderAdminPage('edit.inc.php', [
            'admin_session' => ['entries' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('Sind Sie sicher', $output);
        $this->assertStringContainsString('value="delete"', $output);

        $this->db()->exec('DELETE FROM pb_entries WHERE id = 102');
    }

    #[Test]
    public function editPageDeletesEntry(): void
    {
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date) VALUES (103, 'WillBeDeleted', 'Bye', " . time() . ')');

        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'delete',
            'csrf_token' => $token,
            'edit_id' => '103',
        ];

        $output = $this->renderAdminPage('edit.inc.php', [
            'admin_session' => ['entries' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('erfolgreich gel', $output);

        // Verify deletion
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM pb_entries WHERE id = 103');
        $stmt->execute();
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    // ========================================================================
    // 11. release.inc.php
    // ========================================================================

    #[Test]
    public function releasePageDeniesWithoutPermission(): void
    {
        $output = $this->renderAdminPage('release.inc.php', [
            'admin_session' => ['release' => 'N'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ]);

        $this->assertStringContainsString('keine Berechtigung', $output);
    }

    #[Test]
    public function releasePageShowsNoUnreleasedEntries(): void
    {
        $this->db()->exec('DELETE FROM pb_entries');

        $output = $this->renderAdminPage('release.inc.php', [
            'admin_session' => ['release' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ]);

        $this->assertStringContainsString('Keine Eintr', $output);
        $this->assertStringContainsString('F R E I S C H A L T E N', $output);
    }

    #[Test]
    public function releasePageShowsUnreleasedEntries(): void
    {
        $this->db()->exec('DELETE FROM pb_entries');
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, ip, status) VALUES (200, 'Unreleased1', 'Pending text', " . time() . ", '127.0.0.1', 'U')");
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, ip, status) VALUES (201, 'Unreleased2', 'Also pending', " . time() . ", '127.0.0.2', 'U')");

        $output = $this->renderAdminPage('release.inc.php', [
            'admin_session' => ['release' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ]);

        $this->assertStringContainsString('2', $output);
        $this->assertStringContainsString('nicht freigegebene', $output);
        $this->assertStringContainsString('Pending text', $output);
        $this->assertStringContainsString('Also pending', $output);
        $this->assertStringContainsString('Alle freischalten', $output);

        $this->db()->exec('DELETE FROM pb_entries');
    }

    #[Test]
    public function releasePageReleasesSingleEntry(): void
    {
        $this->db()->exec('DELETE FROM pb_entries');
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, ip, status) VALUES (202, 'ToRelease', 'Release me', " . time() . ", '10.0.0.1', 'U')");

        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'release_one',
            'csrf_token' => $token,
            'entry_id' => '202',
        ];

        $output = $this->renderAdminPage('release.inc.php', [
            'admin_session' => ['release' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ]);

        $this->assertStringContainsString('erfolgreich freigeschaltet', $output);

        // Verify status changed
        $stmt = $this->db()->prepare('SELECT status FROM pb_entries WHERE id = 202');
        $stmt->execute();
        $this->assertSame('R', $stmt->fetchColumn());

        $this->db()->exec('DELETE FROM pb_entries');
    }

    #[Test]
    public function releasePageReleasesAllEntries(): void
    {
        $this->db()->exec('DELETE FROM pb_entries');
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, ip, status) VALUES (203, 'All1', 'First', " . time() . ", '10.0.0.1', 'U')");
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, ip, status) VALUES (204, 'All2', 'Second', " . time() . ", '10.0.0.2', 'U')");

        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'release_all',
            'csrf_token' => $token,
        ];

        $output = $this->renderAdminPage('release.inc.php', [
            'admin_session' => ['release' => 'Y'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ]);

        $this->assertStringContainsString('erfolgreich freigeschaltet', $output);

        // Verify all released
        $stmt = $this->db()->query("SELECT COUNT(*) FROM pb_entries WHERE status = 'U'");
        $this->assertEquals(0, $stmt->fetchColumn());

        $this->db()->exec('DELETE FROM pb_entries');
    }

    // ========================================================================
    // 12. password.inc.php
    // ========================================================================

    #[Test]
    public function passwordPageShowsRecoveryForm(): void
    {
        $output = $this->renderAdminPage('password.inc.php');

        $this->assertStringContainsString('P A S S W O R T', $output);
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('name="name"', $output);
        $this->assertStringContainsString('name="email_known"', $output);
        $this->assertStringContainsString('Passwort vergessen', $output);
    }

    #[Test]
    public function passwordPageRecoversByName(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'recover',
            'csrf_token' => $token,
            'name' => 'SuperAdmin',
            'email_known' => '',
        ];

        $output = $this->renderAdminPage('password.inc.php');

        // BUG-009/010: Recovery-Response ist jetzt generisch (kein Leak der Mail-Adresse).
        $this->assertStringContainsString('Falls ein Konto', $output);

        // Token wurde in DB persistiert.
        $row = $this->db()->query("SELECT reset_token, reset_token_expires FROM pb_admins WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($row['reset_token']);
        $this->assertGreaterThan(time(), (int) $row['reset_token_expires']);
    }

    #[Test]
    public function passwordPageRecoversByEmail(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'recover',
            'csrf_token' => $token,
            'name' => '',
            'email_known' => 'admin@test.com',
        ];

        $output = $this->renderAdminPage('password.inc.php');

        // Generische Response (BUG-009).
        $this->assertStringContainsString('Falls ein Konto', $output);

        // Token muss gesetzt sein; Passwort bleibt unveraendert (BUG-010).
        $row = $this->db()->query("SELECT reset_token, password FROM pb_admins WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($row['reset_token']);

        // Reset token for subsequent tests.
        $this->db()->exec('UPDATE pb_admins SET reset_token = NULL, reset_token_expires = NULL WHERE id = 1');
    }

    #[Test]
    public function passwordPageShowsNotFoundError(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'recover',
            'csrf_token' => $token,
            'name' => 'NonExistentAdmin',
            'email_known' => '',
        ];

        $output = $this->renderAdminPage('password.inc.php');

        // BUG-009: Keine spezifische "nicht gefunden" mehr — gleiche Response wie bei existent.
        $this->assertStringContainsString('Falls ein Konto', $output);
        $this->assertStringNotContainsString('nicht gefunden', $output);
    }

    #[Test]
    public function passwordPageShowsErrorWhenBothEmpty(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'recover',
            'csrf_token' => $token,
            'name' => '',
            'email_known' => '',
        ];

        $output = $this->renderAdminPage('password.inc.php');

        $this->assertStringContainsString('geben Sie Ihren Namen oder', $output);
    }

    // ========================================================================
    // 13. statement.inc.php
    // ========================================================================

    #[Test]
    public function statementPageDeniesWithoutPermission(): void
    {
        $output = $this->renderAdminPage('statement.inc.php', [
            'admin_session' => ['entries' => 'N'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('keine Berechtigung', $output);
    }

    #[Test]
    public function statementPageShowsErrorForNoId(): void
    {
        $output = $this->renderAdminPage('statement.inc.php', [
            'admin_session' => ['entries' => 'Y', 'name' => 'Admin'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('ID unbekannt', $output);
    }

    #[Test]
    public function statementPageShowsFormForEntry(): void
    {
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, ip, status) VALUES (300, 'StatementTest', 'Some text', " . time() . ", '127.0.0.1', 'R')");

        $_GET['id'] = '300';

        $output = $this->renderAdminPage('statement.inc.php', [
            'admin_session' => ['entries' => 'Y', 'name' => 'TestAdmin'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ]);

        $this->assertStringContainsString('S T A T E M E N T S', $output);
        $this->assertStringContainsString('edit_statement', $output);
        $this->assertStringContainsString('Statement speichern', $output);
        $this->assertStringContainsString('Some text', $output);

        $this->db()->exec('DELETE FROM pb_entries WHERE id = 300');
    }

    #[Test]
    public function statementPageUpdatesStatement(): void
    {
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, ip, status) VALUES (301, 'StatUpdate', 'Entry text', " . time() . ", '127.0.0.1', 'R')");

        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'update',
            'csrf_token' => $token,
            'id' => '301',
            'edit_statement' => 'This is the admin statement',
        ];

        $output = $this->renderAdminPage('statement.inc.php', [
            'admin_session' => ['entries' => 'Y', 'name' => 'TestAdmin'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('erfolgreich aktualisiert', $output);

        // Verify DB
        $stmt = $this->db()->prepare('SELECT statement, statement_by FROM pb_entries WHERE id = 301');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('This is the admin statement', $row['statement']);
        $this->assertSame('TestAdmin', $row['statement_by']);

        $this->db()->exec('DELETE FROM pb_entries WHERE id = 301');
    }

    #[Test]
    public function statementPageDeletesStatement(): void
    {
        $this->db()->exec("INSERT INTO pb_entries (id, name, text, date, ip, status, statement, statement_by) VALUES (302, 'StatDel', 'Entry', " . time() . ", '127.0.0.1', 'R', 'Old statement', 'Admin')");

        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'update',
            'csrf_token' => $token,
            'id' => '302',
            'edit_statement' => '',
        ];

        $output = $this->renderAdminPage('statement.inc.php', [
            'admin_session' => ['entries' => 'Y', 'name' => 'TestAdmin'],
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
        ]);

        $this->assertStringContainsString('gel', $output);

        $this->db()->exec('DELETE FROM pb_entries WHERE id = 302');
    }

    // ========================================================================
    // 14. emails.inc.php
    // ========================================================================

    #[Test]
    public function emailsPageHandlesAdminAddedEmail(): void
    {
        $email = 'admin_added';
        $add_email = 'newadmin@test.com';
        $add_name = 'NewAdmin';
        $admin_name = 'SuperAdmin';
        $config_admin_url = 'https://example.com/admin';
        $add_config = 'Y';
        $add_admins = 'N';
        $add_entries = 'Y';
        $add_release = 'N';
        $time = 'temppass123';
        $edit_email = '';
        $edit_name = '';

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/emails.inc.php';
        $output = ob_get_clean();

        // The file sends an email via sendEmail() - just verify no errors/output
        $this->assertEmpty($output);
    }

    #[Test]
    public function emailsPageHandlesAdminEditedEmail(): void
    {
        $email = 'admin_edited';
        $edit_email = 'edited@test.com';
        $edit_name = 'EditedAdmin';
        $admin_name = 'SuperAdmin';
        $config_admin_url = '';
        $edit_config = 'Y';
        $edit_admins = 'Y';
        $edit_entries = 'Y';
        $edit_release = 'Y';
        $add_email = '';
        $add_name = '';

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/emails.inc.php';
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    #[Test]
    public function emailsPageHandlesAdminDeletedEmail(): void
    {
        $email = 'admin_deleted';
        $edit_email = 'deleted@test.com';
        $edit_name = 'DeletedAdmin';
        $admin_name = 'SuperAdmin';
        $config_admin_url = '';
        $add_email = '';
        $add_name = '';

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/emails.inc.php';
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    #[Test]
    public function emailsPageDoesNothingForUnknownAction(): void
    {
        $email = 'unknown_action';
        $add_email = '';
        $add_name = '';
        $edit_email = '';
        $edit_name = '';
        $admin_name = '';
        $config_admin_url = '';

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/emails.inc.php';
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    // ========================================================================
    // 15. pages.inc.php
    // ========================================================================

    #[Test]
    public function pagesPageShowsNothingForSinglePage(): void
    {
        $tmp_pages = 1;
        $tmp_start = 0;
        $count_pages = 10;

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/pages.inc.php';
        $output = ob_get_clean();

        // Single page = no pagination shown
        $this->assertStringNotContainsString('Beginn', $output);
    }

    #[Test]
    public function pagesPageShowsNavigationForMultiplePages(): void
    {
        $tmp_pages = 3;
        $tmp_start = 0;
        $count_pages = 45;

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/pages.inc.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Beginn', $output);
        $this->assertStringContainsString('Ende', $output);
    }

    #[Test]
    public function pagesPageShowsPreviousLinkOnLaterPage(): void
    {
        $tmp_pages = 3;
        $tmp_start = 15;
        $count_pages = 45;

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/pages.inc.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Vorherige Seite', $output);
        $this->assertMatchesRegularExpression('/Vorherige Seite<\/a>/', $output);
    }

    #[Test]
    public function pagesPageShowsNextLinkOnFirstPage(): void
    {
        $tmp_pages = 3;
        $tmp_start = 0;
        $count_pages = 45;

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/pages.inc.php';
        $output = ob_get_clean();

        $this->assertMatchesRegularExpression('/chste Seite[^<]*<\/a>/', $output);
    }

    #[Test]
    public function pagesPageDisablesNextOnLastPage(): void
    {
        $tmp_pages = 3;
        $tmp_start = 30;
        $count_pages = 45;

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/pages.inc.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('Ende', $output);
        $this->assertStringNotContainsString('href="?page=entries&tmp_start=30"', $output);
    }

    // ========================================================================
    // 16. admins.inc.php
    // ========================================================================

    #[Test]
    public function adminsPageDeniesWithoutPermission(): void
    {
        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => ['admins' => 'N'],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('keine Berechtigung', $output);
    }

    #[Test]
    public function adminsPageShowsAdminList(): void
    {
        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => [
                'admins' => 'Y',
                'id' => 1,
                'name' => 'SuperAdmin',
            ],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('A D M I N S', $output);
        $this->assertStringContainsString('ADMINS BEARBEITEN', $output);
        $this->assertStringContainsString('ADMIN HINZUF', $output);
        $this->assertStringContainsString('SuperAdmin', $output);
    }

    #[Test]
    public function adminsPageAddsNewAdmin(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'add',
            'csrf_token' => $token,
            'add_name' => 'NewTestAdmin',
            'add_email' => 'newtestadmin@test.com',
            'add_config' => 'N',
            'add_admins' => 'N',
            'add_entries' => 'Y',
            'add_release' => 'N',
        ];

        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => [
                'admins' => 'Y',
                'id' => 1,
                'name' => 'SuperAdmin',
            ],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('erfolgreich hinzugef', $output);

        // Verify in DB
        $stmt = $this->db()->prepare('SELECT name, email FROM pb_admins WHERE name = ?');
        $stmt->execute(['NewTestAdmin']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('NewTestAdmin', $row['name']);
        $this->assertSame('newtestadmin@test.com', $row['email']);

        // Cleanup
        $this->db()->exec("DELETE FROM pb_admins WHERE name = 'NewTestAdmin'");
    }

    #[Test]
    public function adminsPageRejectsAddWithoutPermissions(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'add',
            'csrf_token' => $token,
            'add_name' => 'NoPermsAdmin',
            'add_email' => 'noperms@test.com',
            'add_config' => 'N',
            'add_admins' => 'N',
            'add_entries' => 'N',
            'add_release' => 'N',
        ];

        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => [
                'admins' => 'Y',
                'id' => 1,
                'name' => 'SuperAdmin',
            ],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('mindestens eine Berechtigung', $output);
    }

    #[Test]
    public function adminsPageRejectsDuplicateName(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'add',
            'csrf_token' => $token,
            'add_name' => 'SuperAdmin',
            'add_email' => 'duplicate@test.com',
            'add_config' => 'Y',
            'add_admins' => 'N',
            'add_entries' => 'N',
            'add_release' => 'N',
        ];

        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => [
                'admins' => 'Y',
                'id' => 1,
                'name' => 'SuperAdmin',
            ],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('bereits einen Admin', $output);
    }

    #[Test]
    public function adminsPagePreventsDeleteSuperAdmin(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'edit',
            'csrf_token' => $token,
            'edit_id' => '1',
            'edit_name' => 'SuperAdmin',
            'edit_email' => 'admin@test.com',
            'edit_password1' => '',
            'edit_password2' => '',
            'delete' => 'yes',
        ];

        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => [
                'admins' => 'Y',
                'id' => 999,
                'name' => 'OtherAdmin',
            ],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('SuperAdmin kann nicht gel', $output);
    }

    #[Test]
    public function adminsPagePreventsSelfDelete(): void
    {
        $this->db()->exec("INSERT INTO pb_admins (id, name, email, password, config, admins, entries, \"release\") VALUES (50, 'SelfAdmin', 'self@test.com', 'hash', 'Y', 'Y', 'Y', 'Y')");

        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'edit',
            'csrf_token' => $token,
            'edit_id' => '50',
            'edit_name' => 'SelfAdmin',
            'edit_email' => 'self@test.com',
            'edit_password1' => '',
            'edit_password2' => '',
            'delete' => 'yes',
        ];

        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => [
                'admins' => 'Y',
                'id' => 50,
                'name' => 'SelfAdmin',
            ],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('selbst nicht l', $output);

        $this->db()->exec('DELETE FROM pb_admins WHERE id = 50');
    }

    #[Test]
    public function adminsPageDeletesOtherAdmin(): void
    {
        $this->db()->exec("INSERT INTO pb_admins (id, name, email, password, config, admins, entries, \"release\") VALUES (60, 'ToDelete', 'todelete@test.com', 'hash', 'N', 'N', 'Y', 'N')");

        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'edit',
            'csrf_token' => $token,
            'edit_id' => '60',
            'edit_name' => 'ToDelete',
            'edit_email' => 'todelete@test.com',
            'edit_password1' => '',
            'edit_password2' => '',
            'delete' => 'yes',
        ];

        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => [
                'admins' => 'Y',
                'id' => 1,
                'name' => 'SuperAdmin',
            ],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('erfolgreich gel', $output);

        // Verify deletion
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM pb_admins WHERE id = 60');
        $stmt->execute();
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    #[Test]
    public function adminsPageRejectsAddWithEmptyFields(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'add',
            'csrf_token' => $token,
            'add_name' => '',
            'add_email' => '',
            'add_config' => 'N',
            'add_admins' => 'N',
            'add_entries' => 'N',
            'add_release' => 'N',
        ];

        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => [
                'admins' => 'Y',
                'id' => 1,
                'name' => 'SuperAdmin',
            ],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('Namen und eine E-Mail', $output);
    }

    #[Test]
    public function adminsPageRejectsInvalidEmail(): void
    {
        $token = $this->getCsrfToken();
        $_POST = [
            'action' => 'add',
            'csrf_token' => $token,
            'add_name' => 'BadEmailAdmin',
            'add_email' => 'not-an-email',
            'add_config' => 'Y',
            'add_admins' => 'N',
            'add_entries' => 'N',
            'add_release' => 'N',
        ];

        $output = $this->renderAdminPage('admins.inc.php', [
            'admin_session' => [
                'admins' => 'Y',
                'id' => 1,
                'name' => 'SuperAdmin',
            ],
            'config_admin_url' => '',
        ]);

        $this->assertStringContainsString('ltige E-Mail', $output);
    }
}
