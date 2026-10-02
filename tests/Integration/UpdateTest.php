<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * update.php: Datenbanken von PowerBook 1.21, 2.0 und 3.0 auf den Stand 3.1
 * bringen – ergänzend, wiederholbar, nur nach Anmeldung, Daten bleiben.
 *
 * Läuft gegen einen echten MySQL- bzw. MariaDB-Server (siehe MysqlTestCase),
 * unter MySQL mit sql_require_primary_key.
 */
final class UpdateTest extends MysqlTestCase
{
    private const array LOGINS = [
        '1.21' => ['PowerBook', 'powerbook'],
        '2.0' => ['PowerBook', 'Altbestand2020'],
        '3.0' => ['admin@example.com', 'Moewenblick2026'],
    ];

    private static string $database;

    private PDO $pdo;

    private string $dir = '';

    /** @var array{config: string, lock: string, schema: string, installDeu: string, delete: list<string>} */
    private array $paths;

    public static function setUpBeforeClass(): void
    {
        self::$database = self::databaseName('update');
    }

    public static function tearDownAfterClass(): void
    {
        self::dropDatabase(self::$database);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function versions(): array
    {
        return ['1.21' => ['1.21'], '2.0' => ['2.0'], '3.0' => ['3.0']];
    }

    #[Test]
    #[DataProvider('versions')]
    public function planListsTasksWithoutChangingAnything(string $version): void
    {
        $this->loadFixture($version);
        $columnsBefore = self::columns($this->pdo, 'pb_config');

        $response = $this->request();

        self::assertSame(200, $response['status']);
        $html = $response['body'];
        self::assertStringContainsString('PowerBook auf Version 3.1 aktualisieren', $html);
        self::assertStringContainsString('id="pbUpdatePlan"', $html);
        self::assertStringContainsString('id="update_name"', $html);
        self::assertStringContainsString('id="update_password"', $html);
        self::assertStringContainsString('id="pbUpdateStart"', $html);
        self::assertStringContainsString('Version 3.1.0', $html);
        foreach ([
            'Spalte pb_config.id als Primärschlüssel ergänzen',
            'Spalte pb_config.title ergänzen (Name des Gästebuchs)',
            'Spalte pb_config.mail_from ergänzen',
            'Spalte pb_admins.pw_changed ergänzen',
            'Tabelle pb_login_attempts anlegen',
            'Adresse des AdminCenters eintragen: http://gaestebuch.example/gb/pb_inc/admincenter/',
            'Datei install.lock anlegen',
            'Alten Installer install_deu.php löschen',
            'Legen Sie vorher eine Sicherung der Datenbank an',
        ] as $task) {
            self::assertStringContainsString($task, $html, $version);
        }
        self::assertSame($columnsBefore, self::columns($this->pdo, 'pb_config'));
        self::assertFileDoesNotExist($this->paths['lock']);
        self::assertFileExists($this->paths['installDeu']);
    }

    #[Test]
    public function plan121ContainsTheOldVersionTasks(): void
    {
        $this->loadFixture('1.21');

        $html = $this->request()['body'];

        foreach ([
            'Tabelle pb_admins von MyISAM auf InnoDB umstellen',
            'Rechte der Admins von PERMITTED/FORBIDDEN auf Y/N umstellen',
            'Spalte pb_admins.password auf 255 Zeichen erweitern',
            'Spalte pb_config.spam_check von Text auf Zahl umstellen',
            'Spalte pb_entries.ip auf 45 Zeichen erweitern (IPv6-Adressen)',
            'Spalte pb_entries.date von Text auf Zahl umstellen',
            'Vorgabewerte ergänzen (pb_entries: email, icon, statement_by, icq)',
            'Index idx_status für pb_entries anlegen',
            'Dateiname des Gästebuchs eintragen: pbook.php',
            'Altes Standarddesign der Einträge (Tabelle aus PowerBook 1.x/2.0)',
            'noch einen älteren Zeichensatz',
            '2 Konten haben noch ein Passwort im alten Format',
        ] as $text) {
            self::assertStringContainsString($text, $html);
        }
    }

    #[Test]
    public function plan30ReplacesTheStandardDesignAndExplainsTheMissingAddress(): void
    {
        $this->loadFixture('3.0');

        $html = $this->request()['body'];

        self::assertStringContainsString('Standarddesign der Einträge: „(#TIME#)h“ durch „(#TIME#) Uhr“ ersetzen', $html);
        self::assertStringContainsString('Spalte pb_admins.reset_token ergänzen', $html);
        self::assertStringContainsString('Spalte pb_entries.date auf BIGINT erweitern', $html);
        self::assertStringContainsString('Für Benachrichtigungen ist noch keine E-Mail-Adresse eingetragen.', $html);
        self::assertStringNotContainsString('PERMITTED', $html);
    }

    #[Test]
    #[DataProvider('versions')]
    public function updateBringsTheDatabaseTo31AndKeepsTheData(string $version): void
    {
        $this->loadFixture($version);
        $before = $this->snapshot();
        [$login, $password] = self::LOGINS[$version];

        $response = $this->start($login, $password);

        $html = $response['body'];
        self::assertStringContainsString('Aktualisierung abgeschlossen', $html, $version);
        self::assertStringContainsString('id="pbUpdateDone"', $html);
        self::assertStringContainsString('Die Datenbank ist jetzt auf dem Stand 3.1.', $html);
        self::assertStringContainsString('id="pbUpdateLog"', $html);
        self::assertStringContainsString('id="pbUpdateDelete"', $html);
        self::assertStringContainsString('id="pbUpdateAdmin"', $html);
        self::assertStringContainsString('id="pbUpdateSite"', $html);

        // Einträge und Konten unverändert (Umlaute inklusive), Passwörter nicht angefasst
        self::assertSame($before, $this->snapshot());

        // Schema auf Stand 3.1
        self::assertContains('pb_login_attempts', self::tables($this->pdo));
        foreach (['id', 'title', 'mail_from'] as $column) {
            self::assertContains($column, self::columns($this->pdo, 'pb_config'));
        }
        foreach (['reset_token', 'reset_token_expires', 'pw_changed'] as $column) {
            self::assertContains($column, self::columns($this->pdo, 'pb_admins'));
        }
        self::assertSame('bigint', preg_replace('/\(\d+\)/', '', self::columnType($this->pdo, 'pb_entries', 'date')));
        self::assertSame('varchar(45)', self::columnType($this->pdo, 'pb_entries', 'ip'));
        self::assertSame('varchar(255)', self::columnType($this->pdo, 'pb_entries', 'homepage'));
        self::assertSame('varchar(255)', self::columnType($this->pdo, 'pb_admins', 'password'));

        // Konfiguration: Adresse, Titel, Standarddesign mit „Uhr“, Danke-Mail mit echten Umlauten
        $config = $this->config();
        self::assertSame(1, (int) $config['id']);
        self::assertSame('Gästebuch', $config['title']);
        self::assertSame('http://gaestebuch.example/gb/pb_inc/admincenter/', $config['admin_url']);
        self::assertSame('pbook.php', $config['guestbook_name']);
        self::assertStringContainsString('(#TIME#) Uhr', (string) $config['design']);
        self::assertStringNotContainsString('(#TIME#)h', (string) $config['design']);
        self::assertStringContainsString('Mit freundlichen Grüßen', (string) $config['thanks']);
        self::assertSame('Danke für Ihren Eintrag!', $config['thanks_title']);

        // Rechte als Y/N, der Superadmin hat alle
        $stmt = $this->pdo->query('SELECT config, `release`, entries, admins FROM pb_admins WHERE id = 1');
        self::assertNotFalse($stmt);
        self::assertSame(['config' => 'Y', 'release' => 'Y', 'entries' => 'Y', 'admins' => 'Y'], $stmt->fetch(PDO::FETCH_ASSOC));

        // Ein neuer Eintrag wie von PowerBook 3.1 (ohne icq), mit IPv6 und Datum nach 2038
        $insert = $this->pdo->prepare('INSERT INTO pb_entries (name, email, text, date, homepage, ip, status, icon, smilies, statement, statement_by)'
            . " VALUES ('Neuer Gast', '', 'Hallo aus 3.1', 2208988800, ?, '2001:db8:85a3:8d3:1319:8a2e:370:7348', 'U', '', 'Y', '', '')");
        $insert->execute(['https://www.example.org/' . str_repeat('a', 220)]);
        $stmt = $this->pdo->query("SELECT date FROM pb_entries WHERE name = 'Neuer Gast'");
        self::assertNotFalse($stmt);
        self::assertSame(2208988800, (int) $stmt->fetchColumn());

        // Neue Zeile in pb_admins wie vom AdminCenter angelegt (Vorgaben genügen)
        $this->pdo->exec("INSERT INTO pb_admins (name, email, password) VALUES ('Neu', 'neu@example.org', 'x')");
        $this->pdo->exec("INSERT INTO pb_login_attempts (ip, name, time) VALUES ('2001:db8::1', 'Neu', 1)");

        // Dateien: Sperrdatei angelegt, alter Installer weg
        self::assertFileExists($this->paths['lock']);
        self::assertFileDoesNotExist($this->paths['installDeu']);
    }

    #[Test]
    #[DataProvider('versions')]
    public function secondRunHasNothingToDo(string $version): void
    {
        $this->loadFixture($version);
        [$login, $password] = self::LOGINS[$version];
        $this->start($login, $password);
        $after = $this->snapshot();
        $config = $this->config();

        $response = $this->request();

        self::assertStringContainsString('id="pbUpdateCurrent"', $response['body']);
        self::assertStringContainsString('Die Datenbank ist bereits auf dem Stand 3.1.', $response['body']);
        self::assertStringNotContainsString('id="pbUpdateStart"', $response['body']);
        self::assertStringContainsString('id="pbUpdateDelete"', $response['body']);

        // Erneutes Absenden ändert nichts
        $response = $this->request(['csrf_token' => (string) $_SESSION['pb_update_csrf'], 'action' => 'start']);
        self::assertStringContainsString('Die Datenbank ist bereits auf dem Stand 3.1.', $response['body']);
        self::assertSame($after, $this->snapshot());
        self::assertSame($config, $this->config());
    }

    #[Test]
    public function updateNeedsAnAccountWithConfigurationRights(): void
    {
        $this->loadFixture('1.21');
        $columns = self::columns($this->pdo, 'pb_admins');

        // falsches Passwort
        $html = $this->start('PowerBook', 'falsch')['body'];
        self::assertStringContainsString('Anmeldung fehlgeschlagen: Name oder Passwort stimmt nicht, oder das Konto darf die Konfiguration nicht ändern.', $html);
        // richtiges Passwort, aber ohne Konfigurationsrecht (FORBIDDEN)
        $html = $this->start('Helfer', 'helfer2003')['body'];
        self::assertStringContainsString('Anmeldung fehlgeschlagen', $html);
        // ohne Anmeldedaten
        $html = $this->request(['csrf_token' => (string) $_SESSION['pb_update_csrf'], 'action' => 'start'])['body'];
        self::assertStringContainsString('Anmeldung fehlgeschlagen', $html);

        self::assertSame($columns, self::columns($this->pdo, 'pb_admins'));
        self::assertFileDoesNotExist($this->paths['lock']);
        self::assertArrayNotHasKey('pb_update_auth', $_SESSION);
    }

    #[Test]
    public function accountWithoutConfigurationRightIsRejectedIn30(): void
    {
        $this->loadFixture('3.0');

        $html = $this->start('Jannik', 'Helferlein2026')['body'];

        self::assertStringContainsString('Anmeldung fehlgeschlagen', $html);
        self::assertNotContains('title', self::columns($this->pdo, 'pb_config'));
    }

    #[Test]
    public function base64PasswordFrom121IsAcceptedAndKeptForTheNextLogin(): void
    {
        $this->loadFixture('1.21');

        $html = $this->start('powerbook@powerscripts.org', 'powerbook')['body'];

        self::assertStringContainsString('Die Datenbank ist jetzt auf dem Stand 3.1.', $html);
        self::assertStringContainsString('2 Konten haben noch ein Passwort im alten Format', $html);
        $stmt = $this->pdo->query('SELECT password FROM pb_admins WHERE id = 1');
        self::assertNotFalse($stmt);
        self::assertSame(base64_encode('powerbook'), $stmt->fetchColumn());
    }

    #[Test]
    public function invalidTokenIsRejected(): void
    {
        $this->loadFixture('3.0');
        $this->request();

        $response = $this->request(['csrf_token' => 'falsch', 'action' => 'start', 'update_name' => 'PowerBook', 'update_password' => 'Moewenblick2026']);

        self::assertSame(403, $response['status']);
        self::assertStringContainsString('Das Formular ist abgelaufen oder ungültig.', $response['body']);
        self::assertNotContains('title', self::columns($this->pdo, 'pb_config'));
    }

    #[Test]
    public function deletingNeedsACompletedUpdate(): void
    {
        $this->loadFixture('3.0');
        foreach ($this->paths['delete'] as $file) {
            file_put_contents($file, '<?php');
        }
        $this->request();

        $html = $this->request(['csrf_token' => (string) $_SESSION['pb_update_csrf'], 'action' => 'delete'])['body'];

        self::assertStringContainsString('Bitte führen Sie zuerst die Aktualisierung durch.', $html);
        self::assertFileExists($this->paths['delete'][0]);
    }

    #[Test]
    public function deleteButtonRemovesUpdateAndInstaller(): void
    {
        $this->loadFixture('3.0');
        foreach ($this->paths['delete'] as $file) {
            file_put_contents($file, '<?php');
        }
        $this->start('PowerBook', 'Moewenblick2026');

        $html = $this->request(['csrf_token' => (string) $_SESSION['pb_update_csrf'], 'action' => 'delete'])['body'];

        self::assertStringContainsString('id="pbUpdateDeleted"', $html);
        self::assertStringContainsString('update.php wurde gelöscht.', $html);
        foreach ($this->paths['delete'] as $file) {
            self::assertFileDoesNotExist($file);
        }
    }

    #[Test]
    public function placeholderInstallDeuIsNotDeleted(): void
    {
        $this->loadFixture('3.0');
        copy(POWERBOOK_ROOT . '/install_deu.php', $this->paths['installDeu']);

        $html = $this->request()['body'];

        self::assertStringNotContainsString('install_deu.php löschen', $html);
    }

    #[Test]
    public function customDesignStaysAndGetsAHint(): void
    {
        $this->loadFixture('3.0');
        $design = '<div class="eintrag">(#DATE#) (#TIME#)h – (#TEXT#)</div>';
        $this->pdo->prepare('UPDATE pb_config SET design = ?')->execute([$design]);

        $html = $this->start('PowerBook', 'Moewenblick2026')['body'];

        self::assertStringContainsString('Ihr eigenes Design der Einträge enthält „(#TIME#)h“', $html);
        self::assertSame($design, $this->config()['design']);
    }

    #[Test]
    public function customAdminAddressIsKept(): void
    {
        $this->loadFixture('3.0');
        $this->pdo->exec("UPDATE pb_config SET admin_url = 'https://www.example.org/gb/pb_inc/admincenter/'");

        $html = $this->request()['body'];

        self::assertStringNotContainsString('Adresse des AdminCenters eintragen', $html);
    }

    #[Test]
    public function emptyConfigTableGetsTheStandardRow(): void
    {
        $this->loadFixture('3.0');
        $this->pdo->exec('DELETE FROM pb_config');

        $html = $this->start('PowerBook', 'Moewenblick2026')['body'];

        self::assertStringContainsString('Standardkonfiguration anlegen', $html);
        $config = $this->config();
        self::assertSame('Gästebuch', $config['title']);
        self::assertSame('http://gaestebuch.example/gb/pb_inc/admincenter/', $config['admin_url']);
    }

    #[Test]
    public function severalConfigRowsStopTheUpdate(): void
    {
        $this->loadFixture('3.0');
        self::setRequirePrimaryKey($this->pdo, false);
        $this->pdo->exec('INSERT INTO pb_config SELECT * FROM pb_config');

        $response = $this->request();

        self::assertStringContainsString('enthält 2 Zeilen, PowerBook nutzt aber nur eine', $response['body']);
        self::assertStringNotContainsString('id="pbUpdateStart"', $response['body']);
    }

    #[Test]
    public function nonNumericDatesAreLeftAloneWithAHint(): void
    {
        $this->loadFixture('1.21');
        $this->pdo->exec("UPDATE pb_entries SET date = '30.06.2003' WHERE id = 1");

        $html = $this->start('PowerBook', 'powerbook')['body'];

        self::assertStringContainsString('Die Spalte pb_entries.date enthält 1 Werte, die keine Zahlen sind.', $html);
        self::assertStringStartsWith('varchar', self::columnType($this->pdo, 'pb_entries', 'date'));
        self::assertStringContainsString('Die Datenbank ist jetzt auf dem Stand 3.1.', $html);
    }

    #[Test]
    public function databaseWithoutTablesPointsToTheInstaller(): void
    {
        $html = $this->request()['body'];

        self::assertStringContainsString('In dieser Datenbank gibt es keine PowerBook-Tabellen.', $html);
        self::assertStringContainsString('install.php', $html);
        self::assertStringNotContainsString('id="pbUpdateStart"', $html);
    }

    #[Test]
    public function installationWithoutAdministratorPointsToTheInstaller(): void
    {
        $this->loadFixture('3.0');
        $this->pdo->exec('DELETE FROM pb_admins');

        $html = $this->request()['body'];

        self::assertStringContainsString('Es gibt keinen Administrator.', $html);
        self::assertStringNotContainsString('id="pbUpdateStart"', $html);
    }

    #[Test]
    public function currentSchemaIsReportedAsUpToDate(): void
    {
        self::loadSqlFile($this->pdo, POWERBOOK_ROOT . '/powerbook.sql');
        $this->pdo->exec("UPDATE pb_config SET email = 'gast@example.org', admin_url = 'https://www.example.org/pb_inc/admincenter/'");
        $this->pdo->exec("INSERT INTO pb_admins (id, name, email, password, config) VALUES (1, 'Anke', 'anke@example.org', '" . password_hash('x', PASSWORD_DEFAULT) . "', 'Y')");
        touch($this->paths['lock']);

        $response = $this->request();

        self::assertStringContainsString('id="pbUpdateCurrent"', $response['body']);
        self::assertStringNotContainsString('id="pbUpdatePlan"', $response['body']);
        self::assertStringNotContainsString('id="pbUpdateNotes"', $response['body']);
    }

    #[Test]
    public function customTableNamesFromMysqlIncAreUsed(): void
    {
        $this->loadFixture('3.0');
        foreach (['pb_admins' => 'gb_admins', 'pb_config' => 'gb_config', 'pb_entries' => 'gb_entries'] as $old => $new) {
            $this->pdo->exec('RENAME TABLE ' . $old . ' TO ' . $new);
        }
        $names = pb_setup_table_names(['pb_admin' => 'gb_admins', 'pb_config' => 'gb_config', 'pb_entries' => 'gb_entries']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        pb_update_handle($this->pdo, $names, $this->paths);
        $_POST = ['csrf_token' => (string) $_SESSION['pb_update_csrf'], 'action' => 'start', 'update_name' => 'PowerBook', 'update_password' => 'Moewenblick2026'];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $html = pb_update_handle($this->pdo, $names, $this->paths)['body'];

        self::assertStringContainsString('Die Datenbank ist jetzt auf dem Stand 3.1.', $html);
        self::assertStringContainsString('Spalte gb_config.title ergänzen', $html);
        self::assertContains('title', self::columns($this->pdo, 'gb_config'));
        self::assertContains('pw_changed', self::columns($this->pdo, 'gb_admins'));
        self::assertNotContains('pb_config', self::tables($this->pdo));
    }

    protected function setUp(): void
    {
        parent::setUp();
        require_once POWERBOOK_ROOT . '/pb_inc/update.inc.php';
        self::freshDatabase(self::$database);
        $this->pdo = self::connect(self::$database);
        $this->dir = self::tempDir('update');
        $this->paths = [
            'config' => $this->dir . '/pb_inc/mysql.inc.php',
            'lock' => $this->dir . '/install.lock',
            'schema' => POWERBOOK_ROOT . '/powerbook.sql',
            'installDeu' => $this->dir . '/install_deu.php',
            'delete' => [$this->dir . '/update.php', $this->dir . '/pb_inc/update.inc.php', $this->dir . '/install.php'],
        ];
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['HTTP_HOST'] = 'gaestebuch.example';
        $_SERVER['SCRIPT_NAME'] = '/gb/update.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_HOST'], $_SERVER['SCRIPT_NAME']);
        self::removeDir($this->dir);
        parent::tearDown();
    }

    /**
     * Tabellen einer alten Version einspielen (ohne Primärschlüssel-Zwang) und den alten Installer hinlegen.
     */
    private function loadFixture(string $version): void
    {
        self::setRequirePrimaryKey($this->pdo, false);
        self::loadSqlFile($this->pdo, POWERBOOK_ROOT . '/tests/Fixtures/schema-' . $version . '.sql');
        self::setRequirePrimaryKey($this->pdo, true);
        file_put_contents($this->paths['installDeu'], "<?php\n// Alter Installer\n\$install = \$_GET['install'] ?? ''; // install=yes\n");
    }

    /**
     * Ruft update.php auf wie der Browser.
     *
     * @param array<string, string> $post
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function request(array $post = []): array
    {
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = $post === [] ? 'GET' : 'POST';

        return pb_update_handle($this->pdo, pb_setup_table_names(), $this->paths);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function start(string $login, string $password): array
    {
        $this->request();

        return $this->request([
            'csrf_token' => (string) $_SESSION['pb_update_csrf'],
            'action' => 'start',
            'update_name' => $login,
            'update_password' => $password,
        ]);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $data = [];
        foreach (['pb_admins' => 'id, name, email, password', 'pb_entries' => 'id, name, email, text, statement, statement_by, ip, status'] as $table => $columns) {
            $stmt = $this->pdo->query('SELECT ' . $columns . ' FROM ' . $table . ' ORDER BY id');
            self::assertNotFalse($stmt);
            $data[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM pb_config');
        self::assertNotFalse($stmt);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $rows);

        return $rows[0];
    }
}
