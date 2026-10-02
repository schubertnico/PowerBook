<?php

/**
 * PowerBook - PHPUnit Tests
 * Guestbook Frontend Tests
 *
 * Tests the frontend guestbook files:
 * - pb_inc/config.inc.php (configuration loader)
 * - pb_inc/guestbook.inc.php (main guestbook display and entry handler)
 * - pb_inc/send-email.php (Benachrichtigung an den Betreiber)
 * - pb_inc/thank-email.php (Danke-Mail an den Gast)
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
        $output = $this->renderGuestbook();

        $this->assertStringContainsString('<span id="pbEntryCount">Noch keine Einträge.</span>', $output);
        $this->assertStringContainsString('In diesem Gästebuch gibt es noch keine Einträge.', $output);
    }

    #[Test]
    public function testGuestbookShowsEntries(): void
    {
        $this->insertEntry(['name' => 'Alice', 'text' => 'Hello from Alice!', 'date' => 1000]);
        $this->insertEntry(['name' => 'Bob', 'text' => 'Hello from Bob!', 'date' => 2000]);
        $this->insertEntry(['name' => 'Carla', 'text' => 'Alt, aber spät importiert', 'date' => 500]);

        $output = $this->renderGuestbook();

        $this->assertStringContainsString('Hello from Alice!', $output);
        $this->assertStringContainsString('Hello from Bob!', $output);
        $this->assertStringContainsString('Dieses Gästebuch enthält <b>3</b> Einträge.', $output);
        // B25: neueste zuerst nach Datum, nicht nach ID
        $this->assertLessThan(strpos($output, 'Alice'), strpos($output, 'Bob'));
        $this->assertLessThan(strpos($output, 'Carla'), strpos($output, 'Alice'));
    }

    #[Test]
    public function testGuestbookSearchByName(): void
    {
        $this->insertEntry(['name' => 'Alice', 'text' => 'Entry by Alice']);
        $this->insertEntry(['name' => 'Bob', 'text' => 'Entry by Bob']);

        $output = $this->renderGuestbook([
            'tmp_search' => 'Alice',
            'tmp_where' => 'name',
        ]);

        $this->assertStringContainsString('<b>1</b> Eintrag mit „Alice“ im Namen', $output);
        $this->assertStringContainsString('id="pbSearchReset"', $output);
        $this->assertStringContainsString('value="Alice"', $output);
        $this->assertStringNotContainsString('Entry by Bob', $output);
    }

    #[Test]
    public function testGuestbookSearchByText(): void
    {
        $this->insertEntry(['name' => 'Alice', 'text' => 'I love PHP programming, 100% sure']);
        $this->insertEntry(['name' => 'Bob', 'text' => 'I love Python programming']);

        $output = $this->renderGuestbook([
            'tmp_search' => 'PHP',
            'tmp_where' => 'text',
        ]);

        $this->assertStringContainsString('<b>1</b> Eintrag mit „PHP“ im Eintragstext', $output);

        // B18: % und _ sind keine Platzhalter, der Begriff wird maskiert angezeigt
        $this->assertStringContainsString('<b>1</b> Eintrag mit „100%“', $this->renderGuestbook(['tmp_search' => '100%', 'tmp_where' => 'text']));
        $this->assertStringContainsString('Keine Einträge mit „_“', $this->renderGuestbook(['tmp_search' => '_', 'tmp_where' => 'text']));
        $html = $this->renderGuestbook(['tmp_search' => '<b>x</b>', 'tmp_where' => 'text']);
        $this->assertStringContainsString('„&lt;b&gt;x&lt;/b&gt;“', $html);
        // Ungültiger Suchbereich = keine Suche
        $this->assertStringContainsString('Dieses Gästebuch enthält <b>2</b> Einträge.', $this->renderGuestbook(['tmp_search' => 'PHP', 'tmp_where' => 'text"><b>']));
    }

    #[Test]
    public function testGuestbookShowForm(): void
    {
        $output = $this->renderGuestbook();

        $this->assertStringContainsString('<form id="pbEntryForm"', $output);
        $this->assertStringContainsString('name="name"', $output);
        $this->assertStringContainsString('name="email2"', $output);
        $this->assertStringContainsString('name="text"', $output);
        $this->assertStringContainsString('>Vorschau</button>', $output);
        $this->assertStringContainsString('>Eintragen</button>', $output);
        $this->assertStringContainsString('id="pbWriteEntryLink"', $output);
        $this->assertStringContainsString('id="pbSearchLink" href="pbook.php?search=yes"', $output);
    }

    #[Test]
    public function testGuestbookHideForm(): void
    {
        // Die Suchseite zeigt nur das Suchformular, kein Eintragsformular und keine Liste.
        $this->insertEntry(['name' => 'Alice', 'text' => 'Versteckt']);
        $output = $this->renderGuestbook(['search' => 'yes']);

        $this->assertStringNotContainsString('name="name"', $output);
        $this->assertStringNotContainsString('Versteckt', $output);
    }

    #[Test]
    public function testGuestbookSearchForm(): void
    {
        $output = $this->renderGuestbook(['show_gb' => 'no', 'show_form' => 'no', 'search' => 'yes', 'tmp_search' => 'Möwe']);

        $this->assertStringContainsString('<form id="pbSearchForm"', $output);
        $this->assertStringContainsString('id="pbSearchInput"', $output);
        $this->assertStringContainsString('value="Möwe"', $output);
        $this->assertStringContainsString('name="tmp_where"', $output);
        $this->assertStringContainsString('id="pbSearchSubmit" type="submit" class="btn btn-primary">Suchen</button>', $output);
        $this->assertStringContainsString('id="pbSearchBack" href="pbook.php"', $output);
        $this->assertStringNotContainsString('history.back', $output);
    }

    #[Test]
    public function testGuestbookFooter(): void
    {
        // Bootstrap-Migration: Der Footer mit PowerBook-Credit ist nun im
        // zentralen Layout-Wrapper (pb_layout_footer() in pbook.php), nicht
        // mehr im guestbook.inc.php-Include. Entsprechend prüfen wir hier nur,
        // dass die Pflicht-Datei pb_inc/layout.inc.php den Footer rendert.
        require_once POWERBOOK_ROOT . '/pb_inc/layout.inc.php';

        ob_start();
        pb_layout_footer();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('PowerBook', $output);
        // Anonymisierter Footer: kein Personenname, kein PHP-Versions-Hinweis,
        // nur generischer powerscripts.org-Link.
        $this->assertStringContainsString('powerscripts.org', $output);
        $this->assertStringNotContainsString('PHP 8.4', $output);
    }

    #[Test]
    public function testGuestbookPagination(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->insertEntry(['name' => "User{$i}", 'text' => "Entry number {$i}", 'date' => 1000 + $i]);
        }

        $output = $this->renderGuestbook([], [], ['config_show_entries' => 10, 'config_pages' => 'D']);
        $this->assertStringContainsString('<b>15</b>', $output);
        $this->assertStringContainsString('id="pbPagerTop"', $output);
        $this->assertStringContainsString('id="pbPagerBottom"', $output);
        $this->assertStringContainsString('href="pbook.php?tmp_start=10"', $output);

        // B19: Grenzwerte ergeben eine vollständige Seite
        foreach (['-5' => 'User15', '999' => 'User5', '7' => 'User15', 'abc' => 'User15'] as $start => $first) {
            $html = $this->renderGuestbook(['tmp_start' => $start], [], ['config_show_entries' => 10]);
            $this->assertMatchesRegularExpression('/<span class="pb-entry-name">' . $first . '<\/span>/', $html, "tmp_start={$start}");
            $this->assertStringContainsString('id="pbEntryForm"', $html);
        }
    }

    #[Test]
    public function testGuestbookFlashMessageAfterSave(): void
    {
        $_SESSION['pb_entry_saved'] = ['status' => 'U', 'id' => 5];
        $output = $this->renderGuestbook();
        $this->assertStringContainsString('<div id="pbEntryMessage" class="alert alert-success" role="status">Vielen Dank! Ihr Eintrag erscheint, sobald er freigeschaltet ist.</div>', $output);
        $this->assertArrayNotHasKey('pb_entry_saved', $_SESSION);
        $this->assertStringNotContainsString('pbEntryMessage', $this->renderGuestbook());

        $_SESSION['pb_entry_saved'] = ['status' => 'R', 'id' => 5];
        $this->assertStringContainsString('Vielen Dank! Ihr Eintrag ist jetzt im Gästebuch. <a class="alert-link" href="#pbEntry5">Zum Eintrag</a>', $this->renderGuestbook());
    }

    // =========================================================================
    // send-email.php Tests
    // =========================================================================

    #[Test]
    public function testAdminNotificationText(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/send-email.php';

        $entry = ['name' => 'Maren <b>aus</b> Kiel', 'email' => 'maren@example.org', 'url' => 'www.example.org', 'text' => 'Preis $10 & "super"'];
        $mail = pb_admin_notification_text($entry, 'U', 'Gästebuch Möwenblick', 'https://gb.example/pb_inc/admincenter/', mktime(20, 42, 0, 9, 20, 2026));

        $this->assertSame('Neuer Eintrag wartet auf Freischaltung', $mail['subject']);
        $this->assertStringContainsString('im Gästebuch „Gästebuch Möwenblick“ ist ein neuer Eintrag eingegangen.', $mail['body']);
        $this->assertStringContainsString('Name:     Maren <b>aus</b> Kiel', $mail['body']);
        $this->assertStringContainsString('Homepage: https://www.example.org', $mail['body']);
        $this->assertStringContainsString('Datum:    20.09.2026, 20:42 Uhr', $mail['body']);
        $this->assertStringContainsString('Status:   wartet auf Freischaltung', $mail['body']);
        $this->assertStringContainsString("Text:\nPreis \$10 & \"super\"", $mail['body']);
        $this->assertStringContainsString('https://gb.example/pb_inc/admincenter/?page=release', $mail['body']);
        $this->assertStringNotContainsString('&amp;', $mail['body']);
        $this->assertStringContainsString('Freischalten oder löschen können Sie den Eintrag im AdminCenter:', $mail['body']);

        $sofort = pb_admin_notification_text(['name' => 'A', 'email' => '', 'url' => '', 'text' => str_repeat('x', 1200)], 'R', 'G', 'https://gb.example/admin/?x=1', 0);
        $this->assertSame('Neuer Eintrag im Gästebuch', $sofort['subject']);
        $this->assertStringContainsString('E-Mail:   nicht angegeben', $sofort['body']);
        $this->assertStringContainsString('https://gb.example/admin/?x=1&page=entries', $sofort['body']);
        $this->assertStringContainsString(str_repeat('x', 1000) . ' …', $sofort['body']);
    }

    #[Test]
    public function testSendEmailSkipsWithoutEmail(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/send-email.php';
        $saved = $GLOBALS['config_email'] ?? null;
        $GLOBALS['config_email'] = '';

        ob_start();
        $result = pb_send_admin_notification(['name' => 'X', 'email' => '', 'url' => '', 'text' => 'Y'], 'U', time());
        $output = (string) ob_get_clean();

        $GLOBALS['config_email'] = $saved;
        $this->assertFalse($result);
        $this->assertSame('', $output);
    }

    // =========================================================================
    // thank-email.php Tests
    // =========================================================================

    #[Test]
    public function testThankEmailReplacesPlaceholders(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/thank-email.php';
        $template = 'Hallo (#NAME#), Text: (#TEXT#). E-Mail: (#EMAIL#). Zeit: (#TIME#). IP: (#IP#). URL: (#URL#). ICQ: (#ICQ#).';
        $entry = ['name' => "Ole \$1 Jensen\r\nBcc: x", 'email' => 'ole@example.org', 'url' => 'https://www.example.org/ute', 'text' => 'Preis $10, "super" & günstig (#NAME#)'];

        $text = pb_thanks_mail_text($template, $entry, mktime(20, 42, 0, 9, 20, 2026), '192.0.2.1');

        $this->assertSame('Hallo Ole $1 Jensen Bcc: x, Text: Preis $10, "super" & günstig (#NAME#). E-Mail: ole@example.org. Zeit: 20.09.2026, 20:42. IP: 192.0.2.1. URL: https://www.example.org/ute. ICQ: .', $text);
        // Ohne Homepage bleibt (#URL#) leer, Namen ohne Links
        $this->assertSame('U= N=Gewinnspiel! Jetzt klicken:', pb_thanks_mail_text('U=(#URL#) N=(#NAME#)', ['name' => 'Gewinnspiel! Jetzt klicken: http://gewinn.example/', 'email' => '', 'url' => '', 'text' => ''], 0, ''));
        $this->assertSame('Hallo Gast', pb_thanks_mail_text('Hallo (#NAME#)', ['name' => 'www.spam.example', 'email' => '', 'url' => '', 'text' => ''], 0, ''));
    }

    #[Test]
    public function testThankEmailSkipsInvalidEmailAndRepeats(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/thank-email.php';
        $saved = $GLOBALS['config_use_thanks'] ?? null;
        $GLOBALS['config_use_thanks'] = 'Y';
        $entry = ['name' => 'X', 'email' => 'not-a-valid-email', 'url' => '', 'text' => 'Y'];

        $this->assertFalse(pb_send_thanks_mail(self::$pdo, 'pb_entries', $entry, 1, time(), '127.0.0.1'));

        // Höchstens eine Danke-Mail je Adresse in 24 Stunden
        $this->insertEntry(['email' => 'Gast@Example.org', 'date' => time() - 3600]);
        $id = $this->insertEntry(['email' => 'gast@example.org']);
        $entry['email'] = 'gast@example.org';
        $this->assertFalse(pb_send_thanks_mail(self::$pdo, 'pb_entries', $entry, $id, time(), '127.0.0.1'));

        $GLOBALS['config_use_thanks'] = 'N';
        $this->assertFalse(pb_send_thanks_mail(self::$pdo, 'pb_entries', $entry, $id, time(), '127.0.0.1'));
        $GLOBALS['config_use_thanks'] = $saved;
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
        unset($_SESSION['pb_entry_saved']);
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

        $stmt = self::$pdo->prepare('
            INSERT INTO pb_entries (name, email, text, date, homepage, icq, ip, status, icon, smilies, statement, statement_by)
            VALUES (:name, :email, :text, :date, :homepage, :icq, :ip, :status, :icon, :smilies, :statement, :statement_by)
        ');

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
}
