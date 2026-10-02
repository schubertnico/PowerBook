<?php

/**
 * PowerBook - PHPUnit Tests
 * Gemeinsame Grundlage für die Gästebuch-Tests: eigene SQLite-Datenbank und
 * Aufruf von guestbook.inc.php mit $_GET/$_POST wie im Browser.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

abstract class GuestbookTestCase extends TestCase
{
    protected static PDO $db;

    public static function setUpBeforeClass(): void
    {
        self::$db = new PDO('sqlite::memory:');
        self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$db->exec('CREATE TABLE pb_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL DEFAULT "",
            text TEXT NOT NULL,
            date INTEGER NOT NULL DEFAULT 0,
            homepage TEXT NOT NULL DEFAULT "",
            ip TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "R",
            icon TEXT NOT NULL DEFAULT "",
            smilies TEXT NOT NULL DEFAULT "Y",
            statement TEXT NOT NULL,
            statement_by TEXT NOT NULL DEFAULT ""
        )');

        // config.inc.php einmal vorab laden, damit es beim Aufruf von
        // guestbook.inc.php (require_once) die Testwerte nicht überschreibt.
        (static function (PDO $pdo): void {
            $pb_config = 'pb_config_fehlt';
            require_once POWERBOOK_ROOT . '/pb_inc/config.inc.php';
        })(self::$db);
    }

    protected function setUp(): void
    {
        self::$db->exec('DELETE FROM pb_entries');
        unset($_SESSION['pb_entry_saved']);
        $_SERVER['REMOTE_ADDR'] = '192.0.2.50';
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        unset($_SESSION['pb_entry_saved'], $_SERVER['REMOTE_ADDR']);
    }

    /**
     * Ruft guestbook.inc.php auf und liefert die Ausgabe.
     *
     * @param array<string, string>  $get
     * @param array<string, mixed>   $post
     * @param array<string, mixed>   $vars Konfiguration überschreiben (config_*)
     */
    protected function render(array $get = [], array $post = [], array $vars = []): string
    {
        $_GET = $get;
        $_POST = $post;

        $pdo = self::$db;
        $pb_config = 'pb_config';
        $pb_admin = 'pb_admins';
        $pb_entries = 'pb_entries';

        $config_title = 'Gästebuch Möwenblick';
        $config_release = 'R';
        $config_send_email = 'N';
        $config_email = '';
        $config_mail_from = '';
        $config_date = 'd.m.Y';
        $config_time = 'H:i';
        $config_spam_check = 30;
        $config_show_entries = 10;
        $config_guestbook_name = 'pbook.php';
        $config_admin_url = '';
        $config_text_format = 'Y';
        $config_icons = 'Y';
        $config_smilies = 'Y';
        $config_pages = 'D';
        $config_use_thanks = 'N';
        $config_design = '';
        $config_thanks_title = '';
        $config_thanks = '';
        $config_statements = 'Y';

        extract($vars);

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php';
        $output = (string) ob_get_clean();

        $_GET = [];
        $_POST = [];

        return $output;
    }

    /**
     * Formulardaten eines Eintrags mit gültigem Token.
     *
     * @param array<string, string> $fields
     *
     * @return array<string, string>
     */
    protected function form(string $action, array $fields = []): array
    {
        return array_merge([
            'csrf_token' => generateCsrfToken(),
            'action' => $action,
            'name' => 'Maren aus Kiel',
            'email2' => '',
            'url' => '',
            'text' => 'Schöne Tage auf Föhr.',
            'icon' => 'no',
            'smilies2' => 'Y',
        ], $fields);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function insertEntry(array $data = []): int
    {
        $row = array_merge([
            'name' => 'Gast',
            'email' => '',
            'text' => 'Text',
            'date' => time() - 3600,
            'homepage' => '',
            'ip' => '198.51.100.1',
            'status' => 'R',
            'icon' => 'no',
            'smilies' => 'Y',
            'statement' => '',
            'statement_by' => '',
        ], $data);
        $stmt = self::$db->prepare('INSERT INTO pb_entries (name, email, text, date, homepage, ip, status, icon, smilies, statement, statement_by)
            VALUES (:name, :email, :text, :date, :homepage, :ip, :status, :icon, :smilies, :statement, :statement_by)');
        $stmt->execute($row);

        return (int) self::$db->lastInsertId();
    }

    protected function countEntries(): int
    {
        return (int) self::$db->query('SELECT COUNT(*) FROM pb_entries')->fetchColumn();
    }
}
