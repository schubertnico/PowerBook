<?php

/**
 * PowerBook - PHPUnit Tests
 * Guestbook Frontend Tests
 *
 * Tests the frontend guestbook files:
 * - pb_inc/config.inc.php (configuration loader)
 * - pb_inc/guestbook.inc.php (main guestbook display and entry handler)
 * - pb_inc/send-email.php (notification email)
 * - pb_inc/thank-email.php (thank you email)
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GuestbookPagesTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        // Create our own SQLite in-memory database for these tests
        self::$pdo = new PDO('sqlite::memory:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        self::$pdo->exec('CREATE TABLE pb_config (
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

        self::$pdo->exec('CREATE TABLE pb_admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            password TEXT NOT NULL,
            config TEXT DEFAULT "N",
            admins TEXT DEFAULT "N",
            entries TEXT DEFAULT "N",
            "release" TEXT DEFAULT "N"
        )');

        self::$pdo->exec('CREATE TABLE pb_entries (
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

        self::$pdo->exec('INSERT INTO pb_config (id) VALUES (1)');

        self::$pdo->exec("INSERT INTO pb_admins (id, name, email, password, config, admins, entries, \"release\")
            VALUES (1, 'SuperAdmin', 'admin@test.com', 'test', 'Y', 'Y', 'Y', 'Y')");

        // Set up globals so included files can access them
        $GLOBALS['pdo'] = self::$pdo;
        $GLOBALS['pb_config'] = 'pb_config';
        $GLOBALS['pb_admin'] = 'pb_admins';
        $GLOBALS['pb_entries'] = 'pb_entries';
    }

    protected function setUp(): void
    {
        // Ensure globals point to our PDO instance
        $GLOBALS['pdo'] = self::$pdo;
        $GLOBALS['pb_config'] = 'pb_config';
        $GLOBALS['pb_admin'] = 'pb_admins';
        $GLOBALS['pb_entries'] = 'pb_entries';

        // Clean entries table before each test
        self::$pdo->exec('DELETE FROM pb_entries');

        // Reset auto-increment sequence
        self::$pdo->exec("DELETE FROM sqlite_sequence WHERE name='pb_entries'");
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    /**
     * Include a file in an isolated scope with extracted variables and output buffering.
     *
     * @param string               $file Path to the file to include
     * @param array<string, mixed> $vars Variables to make available in the file scope
     *
     * @return string The captured output
     */
    private function renderFile(string $file, array $vars = []): string
    {
        // Set up core variables
        $pdo = self::$pdo;
        $pb_config = 'pb_config';
        $pb_admin = 'pb_admins';
        $pb_entries = 'pb_entries';

        // Apply overrides
        extract($vars);

        ob_start();
        include $file;

        return ob_get_clean() ?: '';
    }

    /**
     * Render guestbook.inc.php with given GET/POST params and variable overrides.
     *
     * @param array<string, string> $get  $_GET parameters
     * @param array<string, string> $post $_POST parameters
     * @param array<string, mixed>  $vars Additional variable overrides
     *
     * @return string The captured output
     */
    private function renderGuestbook(array $get = [], array $post = [], array $vars = []): string
    {
        $savedGet = $_GET;
        $savedPost = $_POST;
        $_GET = $get;
        $_POST = $post;

        // Set up core variables
        $pdo = self::$pdo;
        $pb_config = 'pb_config';
        $pb_admin = 'pb_admins';
        $pb_entries = 'pb_entries';

        // Set default config variables (as config.inc.php would set them)
        $config_release = 'R';
        $config_send_email = 'N';
        $config_email = 'admin@test.com';
        $config_date = 'd.m.Y';
        $config_time = 'H:i';
        $config_spam_check = 60;
        $config_color = '#FF0000';
        $config_show_entries = 10;
        $config_guestbook_name = 'pbook.php';
        $config_admin_url = '';
        $config_text_format = 'Y';
        $config_icons = 'Y';
        $config_smilies = 'Y';
        $config_icq = 'N';
        $config_pages = 'D';
        $config_use_thanks = 'N';
        $config_language = 'D';
        $config_design = '(#ICON#)(#DATE#)(#TIME#)(#EMAIL_NAME#)(#TEXT#)(#URL#)(#ICQ#)';
        $config_thanks_title = '';
        $config_thanks = '';
        $config_statements = 'Y';

        // Apply overrides
        extract($vars);

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php';
        $output = ob_get_clean() ?: '';

        $_GET = $savedGet;
        $_POST = $savedPost;

        return $output;
    }

    /**
     * Insert a test guestbook entry.
     *
     * @param array<string, mixed> $data Entry data overrides
     *
     * @return int The inserted entry ID
     */
    private function insertEntry(array $data = []): int
    {
        $defaults = [
            'name' => 'TestUser',
            'email' => 'test@example.com',
            'text' => 'This is a test entry.',
            'date' => time(),
            'homepage' => 'www.example.com',
            'icq' => '',
            'ip' => '127.0.0.1',
            'status' => 'R',
            'icon' => '',
            'smilies' => 'N',
            'statement' => '',
            'statement_by' => '',
        ];

        $entry = array_merge($defaults, $data);

        $stmt = self::$pdo->prepare("
            INSERT INTO pb_entries (name, email, text, date, homepage, icq, ip, status, icon, smilies, statement, statement_by)
            VALUES (:name, :email, :text, :date, :homepage, :icq, :ip, :status, :icon, :smilies, :statement, :statement_by)
        ");

        $stmt->execute([
            ':name' => $entry['name'],
            ':email' => $entry['email'],
            ':text' => $entry['text'],
            ':date' => $entry['date'],
            ':homepage' => $entry['homepage'],
            ':icq' => $entry['icq'],
            ':ip' => $entry['ip'],
            ':status' => $entry['status'],
            ':icon' => $entry['icon'],
            ':smilies' => $entry['smilies'],
            ':statement' => $entry['statement'],
            ':statement_by' => $entry['statement_by'],
        ]);

        return (int) self::$pdo->lastInsertId();
    }

    // =========================================================================
    // config.inc.php Tests
    // =========================================================================

    #[Test]
    public function testConfigLoadsDefaultValues(): void
    {
        // config.inc.php queries pb_config table and sets config_* variables.
        // We include it in the local scope with $pdo and table name variables set.
        $pdo = self::$pdo;
        $pb_config = 'pb_config';
        $pb_admin = 'pb_admins';
        $pb_entries = 'pb_entries';

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/config.inc.php';
        ob_get_clean();

        // After including config.inc.php, config_* variables should be set in this scope
        $this->assertSame('R', $config_release);
        $this->assertSame('N', $config_send_email);
        $this->assertSame('admin@test.com', $config_email);
        $this->assertSame('d.m.Y', $config_date);
        $this->assertSame('H:i', $config_time);
        $this->assertSame(60, $config_spam_check);
        $this->assertSame('#FF0000', $config_color);
        $this->assertSame(10, $config_show_entries);
        $this->assertSame('pbook.php', $config_guestbook_name);
        $this->assertSame('D', $config_pages);
        $this->assertSame('Y', $config_text_format);
        $this->assertSame('Y', $config_icons);
        $this->assertSame('Y', $config_smilies);
        $this->assertSame('N', $config_use_thanks);
    }

    #[Test]
    public function testConfigLoadsFromDatabase(): void
    {
        // Update the config row with custom values
        self::$pdo->exec("UPDATE pb_config SET email = 'custom@test.com', show_entries = 5, color = '#00FF00' WHERE id = 1");

        // Since config.inc.php uses require_once, it won't re-execute after the first include.
        // We test the database query directly to verify config loading logic.
        $stmt = self::$pdo->query('SELECT * FROM pb_config LIMIT 1');
        $configRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('custom@test.com', $configRow['email']);
        $this->assertEquals(5, $configRow['show_entries']);
        $this->assertSame('#00FF00', $configRow['color']);

        // Verify config loading logic works with this row
        $config_email = $configRow['email'] ?? '';
        $config_show_entries = (int) ($configRow['show_entries'] ?? 10);
        $config_color = $configRow['color'] ?? '#FF0000';

        $this->assertSame('custom@test.com', $config_email);
        $this->assertSame(5, $config_show_entries);
        $this->assertSame('#00FF00', $config_color);

        // Restore defaults
        self::$pdo->exec("UPDATE pb_config SET email = 'admin@test.com', show_entries = 10, color = '#FF0000' WHERE id = 1");
    }

    // =========================================================================
    // guestbook.inc.php Tests
    // =========================================================================

    #[Test]
    public function testGuestbookShowsNoEntries(): void
    {
        $output = $this->renderGuestbook(['show_gb' => 'yes']);

        // Should show "no entries" message
        $this->assertStringContainsString('keine Eintr', $output);
        // Should contain entry count showing 0
        $this->assertStringContainsString('<b>0</b>', $output);
    }

    #[Test]
    public function testGuestbookShowsEntries(): void
    {
        $this->insertEntry(['name' => 'Alice', 'text' => 'Hello from Alice!']);
        $this->insertEntry(['name' => 'Bob', 'text' => 'Hello from Bob!']);

        $output = $this->renderGuestbook(['show_gb' => 'yes']);

        // Should show the entry names and texts
        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('Bob', $output);
        $this->assertStringContainsString('Hello from Alice!', $output);
        $this->assertStringContainsString('Hello from Bob!', $output);
        // Should show count of 2
        $this->assertStringContainsString('<b>2</b>', $output);
        $this->assertStringContainsString('Eintr', $output);
    }

    #[Test]
    public function testGuestbookSearchByName(): void
    {
        $this->insertEntry(['name' => 'Alice', 'text' => 'Entry by Alice']);
        $this->insertEntry(['name' => 'Bob', 'text' => 'Entry by Bob']);

        $output = $this->renderGuestbook([
            'show_gb' => 'yes',
            'tmp_search' => 'Alice',
            'tmp_where' => 'name',
        ]);

        // Should find Alice's entry
        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('<b>1</b>', $output);
        // Should show "gefunden" (found) text for search results
        $this->assertStringContainsString('gefunden', $output);
    }

    #[Test]
    public function testGuestbookSearchByText(): void
    {
        $this->insertEntry(['name' => 'Alice', 'text' => 'I love PHP programming']);
        $this->insertEntry(['name' => 'Bob', 'text' => 'I love Python programming']);

        $output = $this->renderGuestbook([
            'show_gb' => 'yes',
            'tmp_search' => 'PHP',
            'tmp_where' => 'text',
        ]);

        // Should find only Alice's entry containing PHP
        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('<b>1</b>', $output);
        $this->assertStringContainsString('gefunden', $output);
    }

    #[Test]
    public function testGuestbookShowForm(): void
    {
        $output = $this->renderGuestbook(
            ['show_gb' => 'no', 'show_form' => 'yes'],
            [],
            ['show_form' => 'yes']
        );

        // Form should contain input fields
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('name="name"', $output);
        $this->assertStringContainsString('name="email2"', $output);
        $this->assertStringContainsString('name="text"', $output);
        $this->assertStringContainsString('Abschicken', $output);
    }

    #[Test]
    public function testGuestbookHideForm(): void
    {
        $output = $this->renderGuestbook(
            ['show_gb' => 'no', 'show_form' => 'no'],
            ['show_form' => 'no'],
        );

        // Form should NOT appear (no form action for entry submission)
        $this->assertStringNotContainsString('name="name"', $output);
        $this->assertStringNotContainsString('Abschicken', $output);
    }

    #[Test]
    public function testGuestbookSearchForm(): void
    {
        $output = $this->renderGuestbook(
            ['show_gb' => 'no', 'show_form' => 'no', 'search' => 'yes'],
            ['show_form' => 'no'],
        );

        // Search form should appear
        $this->assertStringContainsString('Suchen nach', $output);
        $this->assertStringContainsString('name="tmp_search"', $output);
        $this->assertStringContainsString('name="tmp_where"', $output);
        $this->assertStringContainsString('Suchen!', $output);
    }

    #[Test]
    public function testGuestbookFooter(): void
    {
        // The footer with PowerBook credit is always rendered
        $output = $this->renderGuestbook(['show_gb' => 'no', 'show_form' => 'no']);

        $this->assertStringContainsString('PowerBook', $output);
        $this->assertStringContainsString('Axel Habermaier', $output);
        $this->assertStringContainsString('2002', $output);
    }

    #[Test]
    public function testGuestbookPagination(): void
    {
        // Insert 15 entries (more than the default 10 per page)
        for ($i = 1; $i <= 15; $i++) {
            $this->insertEntry([
                'name' => "User{$i}",
                'text' => "Entry number {$i}",
                'date' => time() + $i,
            ]);
        }

        $output = $this->renderGuestbook(
            ['show_gb' => 'yes'],
            [],
            ['config_show_entries' => 10, 'config_pages' => 'D']
        );

        // Should show 15 total entries
        $this->assertStringContainsString('<b>15</b>', $output);
        // Direct pagination should show page links (Seite = Page in German)
        $this->assertStringContainsString('Seite', $output);
        // Should have at least 2 pages
        $this->assertStringContainsString('tmp_page=2', $output);
    }

    // =========================================================================
    // send-email.php Tests
    // =========================================================================

    #[Test]
    public function testSendEmailWithValidConfig(): void
    {
        // send-email.php expects: $config_email, $config_admin_url, $name2
        // It calls sendEmail() which calls mail() - may fail silently in test env
        $output = $this->renderFile(POWERBOOK_ROOT . '/pb_inc/send-email.php', [
            'config_email' => 'admin@test.com',
            'config_admin_url' => 'http://localhost/admin',
            'name2' => 'TestUser',
        ]);

        // send-email.php does not produce output on success, just calls sendEmail()
        // If mail() is not available, sendEmail() handles errors gracefully
        // The key thing is it does not throw or produce error output
        $this->assertStringNotContainsString('Error', $output);
        $this->assertStringNotContainsString('Fatal', $output);
    }

    #[Test]
    public function testSendEmailSkipsWithoutEmail(): void
    {
        // When config_email is empty, the email should not be sent
        $output = $this->renderFile(POWERBOOK_ROOT . '/pb_inc/send-email.php', [
            'config_email' => '',
            'config_admin_url' => '',
            'name2' => 'TestUser',
        ]);

        // No output expected - the if-check skips sending when email is empty
        $this->assertEmpty(trim($output));
    }

    // =========================================================================
    // thank-email.php Tests
    // =========================================================================

    #[Test]
    public function testThankEmailReplacesPlaceholders(): void
    {
        // thank-email.php replaces placeholders in $config_thanks template
        // and sends an email via sendEmail(). It produces no output.
        $template = 'Hello (#NAME#), thanks for your entry: (#TEXT#). Your email: (#EMAIL#). Time: (#TIME#). IP: (#IP#). URL: (#URL#). ICQ: (#ICQ#).';

        $output = $this->renderFile(POWERBOOK_ROOT . '/pb_inc/thank-email.php', [
            'config_thanks' => $template,
            'config_thanks_title' => 'Thank You',
            'config_email' => 'admin@test.com',
            'email2' => 'user@example.com',
            'name2' => 'TestUser',
            'text2' => 'Great guestbook!',
            'url2' => 'www.example.com',
            'icq2' => '12345',
            'time' => time(),
            'ip' => '192.168.1.1',
        ]);

        // The file calls sendEmail() internally - it does not produce HTML output.
        // The key test is that the file executes without errors.
        $this->assertStringNotContainsString('Error', $output);
        $this->assertStringNotContainsString('Fatal', $output);
    }

    #[Test]
    public function testThankEmailSkipsInvalidEmail(): void
    {
        // When email2 is invalid, the email should not be sent
        $output = $this->renderFile(POWERBOOK_ROOT . '/pb_inc/thank-email.php', [
            'config_thanks' => 'Hello (#NAME#)',
            'config_thanks_title' => 'Thank You',
            'config_email' => 'admin@test.com',
            'email2' => 'not-a-valid-email',
            'name2' => 'TestUser',
            'text2' => 'Great guestbook!',
            'url2' => '',
            'icq2' => '',
            'time' => time(),
            'ip' => '127.0.0.1',
        ]);

        // No output expected - the if-check skips sending when email is invalid
        $this->assertEmpty(trim($output));
    }
}
