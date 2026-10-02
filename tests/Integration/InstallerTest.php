<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\Test;

/**
 * install.php: alle Schritte gegen einen echten MySQL- bzw. MariaDB-Server,
 * wie der Browser sie aufruft (GET/POST, Sitzung, Token). Dateien
 * (pb_inc/mysql.inc.php, install.lock) landen in einem Temp-Verzeichnis.
 */
final class InstallerTest extends MysqlTestCase
{
    /** Passwort mit allem, was beim Schreiben von mysql.inc.php stören könnte. */
    private const string DB_PASSWORD = "Mö'we\\n\$blick\"2026;";

    private const string ADMIN_PASSWORD = 'Strandkorb-26';

    private static string $database;

    private static string $user;

    private string $dir = '';

    /** @var array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} */
    private array $paths;

    public static function setUpBeforeClass(): void
    {
        self::$database = self::databaseName('installer');
        self::$user = self::databaseName('inst');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::credentials() !== null) {
            self::server()->exec("DROP USER IF EXISTS '" . self::$user . "'@'%'");
        }
        self::dropDatabase(self::$database);
    }

    #[Test]
    public function welcomePageChecksTheRequirements(): void
    {
        $response = $this->request(0);

        self::assertSame(200, $response['status']);
        self::assertSame('no-store', $response['headers']['Cache-Control']);
        $html = $response['body'];
        self::assertStringContainsString('Willkommen beim PowerBook-Installer', $html);
        self::assertStringContainsString('Version 3.1.0', $html);
        self::assertStringContainsString('id="pbInstallSteps"', $html);
        self::assertStringContainsString('id="pbInstallChecks"', $html);
        self::assertStringContainsString('PHP 8.4 oder neuer', $html);
        self::assertStringContainsString('Schreibrecht für pb_inc/', $html);
        self::assertStringContainsString('id="pbInstallNext"', $html);
        self::assertStringContainsString('href="install.php?step=1"', $html);
        self::assertStringContainsString('update.php', $html);
    }

    #[Test]
    public function fullInstallationCreatesTablesConfigurationAdminFileAndLock(): void
    {
        self::assertStringContainsString('id="mysql_host"', $this->request(1)['body']);
        self::assertRedirect(2, $this->request(1, $this->databaseForm()));

        $step2 = $this->request(2)['body'];
        self::assertStringContainsString('value="http://gaestebuch.example/gb/"', $step2);
        self::assertStringContainsString('id="gb_notify" name="gb_notify" value="1" checked', $step2);
        self::assertStringContainsString('id="gb_release" name="gb_release" value="1" checked', $step2);
        self::assertRedirect(3, $this->request(2, $this->guestbookForm()));

        $step3 = $this->request(3)['body'];
        self::assertStringContainsString('value="gastgeber@example.org"', $step3, 'E-Mail des Admins: Vorgabe aus Schritt 2');
        self::assertRedirect(4, $this->request(3, $this->adminForm()));

        // Datenbank
        $pdo = $this->pdo();
        foreach (['pb_admins', 'pb_config', 'pb_entries', 'pb_login_attempts'] as $table) {
            self::assertContains($table, self::tables($pdo));
        }
        $stmt = $pdo->query('SELECT * FROM pb_config');
        self::assertNotFalse($stmt);
        $config = $stmt->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $config);
        self::assertSame('Gästebuch Möwenblick', $config[0]['title']);
        self::assertSame('https://www.moewenblick.example/gaestebuch/pb_inc/admincenter/', $config[0]['admin_url']);
        self::assertSame('gastgeber@example.org', $config[0]['email']);
        self::assertSame('Y', $config[0]['send_email']);
        self::assertSame('R', $config[0]['release'], 'Freischaltung abgewählt → Einträge sofort öffentlich');
        self::assertSame('d.m.Y', $config[0]['date']);
        self::assertSame('ger1', $config[0]['language']);
        $stmt = $pdo->query('SELECT * FROM pb_admins');
        self::assertNotFalse($stmt);
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $admins);
        self::assertSame(1, (int) $admins[0]['id']);
        self::assertSame('Anke', $admins[0]['name']);
        self::assertSame('anke@example.org', $admins[0]['email']);
        self::assertTrue(password_verify(self::ADMIN_PASSWORD, (string) $admins[0]['password']));
        self::assertSame(['Y', 'Y', 'Y', 'Y'], [$admins[0]['config'], $admins[0]['release'], $admins[0]['entries'], $admins[0]['admins']]);
        self::assertGreaterThan(0, (int) $admins[0]['pw_changed']);

        // pb_inc/mysql.inc.php: jeder Wert kommt unverändert wieder heraus
        $vars = $this->writtenConfig();
        $c = self::credentials();
        self::assertNotNull($c);
        self::assertSame($c['host'], $vars['config_sql_server']);
        self::assertSame($c['port'], $vars['config_sql_port']);
        self::assertSame(self::$user, $vars['config_sql_user']);
        self::assertSame(self::DB_PASSWORD, $vars['config_sql_password']);
        self::assertSame(self::$database, $vars['config_sql_database']);
        self::assertSame(['pb_config', 'pb_admins', 'pb_entries'], [$vars['pb_config'], $vars['pb_admin'], $vars['pb_entries']]);
        self::assertStringContainsString('angelegt von install.php am', (string) file_get_contents($this->paths['config']));

        // Sperrdatei, Abschlussseite
        self::assertFileExists($this->paths['lock']);
        self::assertStringContainsString('PowerBook 3.1.0 installiert am', (string) file_get_contents($this->paths['lock']));
        $done = $this->request(4);
        self::assertSame(200, $done['status']);
        foreach (['Fertig: PowerBook ist installiert', '<strong>Anke</strong>', 'id="pbInstallDelete"', 'install.php jetzt löschen', 'id="pbInstallAdmin"', 'id="pbInstallSite"'] as $text) {
            self::assertStringContainsString($text, $done['body']);
        }
        self::assertStringNotContainsString(self::DB_PASSWORD, $done['body']);
        self::assertArrayNotHasKey('db', $_SESSION['pb_install'], 'Zugangsdaten bleiben nicht in der Sitzung');
    }

    #[Test]
    public function installerIsLockedAfterwardsWithoutRevealingThePath(): void
    {
        $this->install();

        foreach ([0, 1, 2, 3] as $step) {
            $response = $this->request($step);
            self::assertSame(403, $response['status'], 'Schritt ' . $step);
            self::assertStringContainsString('PowerBook ist bereits installiert', $response['body']);
            self::assertStringNotContainsString($this->dir, $response['body']);
            self::assertStringNotContainsString(str_replace('\\', '/', $this->dir), $response['body']);
        }
        // Andere Sitzung: auch Schritt 4 gesperrt
        $_SESSION = [];
        self::assertSame(403, $this->request(4)['status']);
    }

    #[Test]
    public function finishedSessionOnlySeesStep4(): void
    {
        $this->install();
        unlink($this->paths['lock']);

        self::assertRedirect(4, $this->request(1));
        self::assertRedirect(4, $this->request(0));
    }

    #[Test]
    public function wrongPasswordGivesAReadableMessageWithoutServerDetails(): void
    {
        $response = $this->request(1, $this->databaseForm('falsch'));

        self::assertSame(200, $response['status']);
        $html = $response['body'];
        self::assertStringContainsString('Die Datenbank hat die Anmeldung abgelehnt: Benutzername oder Passwort stimmen nicht.', $html);
        self::assertStringNotContainsString('Access denied', $html);
        self::assertStringNotContainsString(self::$user . '@', $html);
        self::assertStringNotContainsString('SQLSTATE', $html);
        self::assertStringContainsString('value="' . self::$user . '"', $html, 'Eingaben bleiben stehen');
        self::assertStringNotContainsString('falsch', $html, 'Passwort wird nie vorbelegt');
        self::assertArrayNotHasKey('db', $_SESSION['pb_install']);
    }

    #[Test]
    public function unknownDatabaseAndUnreachableServerAreExplained(): void
    {
        $c = self::credentials();
        self::assertNotNull($c);

        $html = $this->request(1, $this->databaseForm($c['password'], $c['user'], self::databaseName('gibtsnicht')))['body'];
        self::assertStringContainsString('gibt es auf dem Server nicht. Legen Sie sie beim Hoster an oder prüfen Sie den Namen.', $html);

        $form = $this->databaseForm();
        $form['mysql_host'] = '127.0.0.1';
        $form['mysql_port'] = '1';
        $html = $this->request(1, $form)['body'];
        self::assertStringContainsString('Der Datenbankserver „127.0.0.1“ ist nicht erreichbar. Prüfen Sie Servername und Port.', $html);
    }

    #[Test]
    public function emptyFieldsAreReported(): void
    {
        $html = $this->request(1, ['mysql_host' => '', 'mysql_port' => '0', 'mysql_database' => 'a;b', 'mysql_user' => ''])['body'];

        foreach ([
            'Bitte geben Sie den Datenbankserver an.',
            'Der Port muss eine Zahl zwischen 1 und 65535 sein',
            'Der Datenbankname darf kein Semikolon enthalten',
            'Bitte geben Sie den Benutzernamen der Datenbank an.',
        ] as $text) {
            self::assertStringContainsString($text, $html);
        }

        self::assertRedirect(2, $this->request(1, $this->databaseForm()));
        $html = $this->request(2, ['gb_title' => '', 'gb_url' => 'gaestebuch', 'gb_email' => 'keine-adresse'])['body'];
        foreach ([
            'Bitte geben Sie den Namen des Gästebuchs an.',
            'Bitte geben Sie die Adresse des Gästebuchs vollständig an',
            'Bitte geben Sie eine gültige E-Mail-Adresse für Benachrichtigungen an.',
        ] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringNotContainsString(' checked', $html, 'abgewählte Kästchen bleiben abgewählt');
    }

    #[Test]
    public function passwordsMustMatchAndBeLongEnough(): void
    {
        self::assertRedirect(2, $this->request(1, $this->databaseForm()));
        self::assertRedirect(3, $this->request(2, $this->guestbookForm()));

        $html = $this->request(3, $this->adminForm('Strandkorb-27'))['body'];
        self::assertStringContainsString('Die beiden Passwörter stimmen nicht überein.', $html);

        $html = $this->request(3, ['admin_name' => 'Anke', 'admin_email' => 'anke@example.org', 'admin_password' => 'kurz', 'admin_password2' => 'kurz'])['body'];
        self::assertStringContainsString('Das Passwort muss mindestens 8 Zeichen lang sein.', $html);

        self::assertSame([], self::tables($this->pdo()));
        self::assertFileDoesNotExist($this->paths['config']);
        self::assertFileDoesNotExist($this->paths['lock']);
    }

    #[Test]
    public function existingTablesNeedExplicitConfirmation(): void
    {
        $pdo = $this->pdo();
        self::loadSqlFile($pdo, POWERBOOK_ROOT . '/powerbook.sql');
        $pdo->exec("INSERT INTO pb_entries (name, text, statement) VALUES ('Alt', 'Alter Eintrag', '')");

        $response = $this->request(1, $this->databaseForm());
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('In dieser Datenbank gibt es schon PowerBook-Tabellen (pb_admins, pb_config, pb_entries, pb_login_attempts).', $response['body']);
        self::assertStringContainsString('id="overwrite"', $response['body']);
        self::assertStringContainsString('update.php', $response['body']);
        $stmt = $pdo->query('SELECT COUNT(*) FROM pb_entries');
        self::assertNotFalse($stmt);
        self::assertSame(1, (int) $stmt->fetchColumn(), 'ohne Haken bleibt alles');

        self::assertRedirect(2, $this->request(1, $this->databaseForm() + ['overwrite' => '1']));
        self::assertRedirect(3, $this->request(2, $this->guestbookForm()));
        self::assertRedirect(4, $this->request(3, $this->adminForm()));
        $stmt = $pdo->query('SELECT COUNT(*) FROM pb_entries');
        self::assertNotFalse($stmt);
        self::assertSame(0, (int) $stmt->fetchColumn(), 'mit Haken neu angelegt');
    }

    #[Test]
    public function tablesCreatedInTheMeantimeAreNotOverwritten(): void
    {
        self::assertRedirect(2, $this->request(1, $this->databaseForm()));
        self::assertRedirect(3, $this->request(2, $this->guestbookForm()));
        $pdo = $this->pdo();
        self::loadSqlFile($pdo, POWERBOOK_ROOT . '/powerbook.sql');

        $html = $this->request(3, $this->adminForm())['body'];

        self::assertStringContainsString('In der Datenbank gibt es inzwischen PowerBook-Tabellen.', $html);
        $stmt = $pdo->query('SELECT COUNT(*) FROM pb_admins');
        self::assertNotFalse($stmt);
        self::assertSame(0, (int) $stmt->fetchColumn());
        self::assertFileDoesNotExist($this->paths['config']);
    }

    #[Test]
    public function postWithoutTokenChangesNothing(): void
    {
        $response = $this->request(1, $this->databaseForm(), false);

        self::assertSame(403, $response['status']);
        self::assertStringContainsString('Das Formular ist abgelaufen oder ungültig.', $response['body']);
        self::assertStringContainsString('id="pbInstallReload"', $response['body']);
        self::assertArrayNotHasKey('db', $_SESSION['pb_install'] ?? []);

        $_SESSION['pb_install'] = ['db' => ['host' => 'x'], 'guestbook' => ['title' => 'x']];
        $response = $this->request(3, $this->adminForm() + ['csrf_token' => 'falsch'], false);
        self::assertSame(403, $response['status']);
        self::assertSame([], self::tables($this->pdo()));
    }

    #[Test]
    public function stepsCannotBeSkipped(): void
    {
        self::assertRedirect(1, $this->request(2));
        self::assertRedirect(1, $this->request(3));
        self::assertRedirect(1, $this->request(4));
        self::assertRedirect(2, $this->request(1, $this->databaseForm()));
        self::assertRedirect(2, $this->request(3));
    }

    #[Test]
    public function existingConfigurationWithAdministratorBlocksTheInstaller(): void
    {
        $this->install();
        unlink($this->paths['lock']);
        $_SESSION = [];

        $response = $this->request(0);

        self::assertSame(403, $response['status']);
        self::assertStringContainsString('PowerBook ist bereits eingerichtet', $response['body']);
        self::assertStringContainsString('update.php', $response['body']);
        self::assertSame(403, $this->request(1, $this->databaseForm() + ['overwrite' => '1'])['status']);
    }

    #[Test]
    public function handWrittenConfigurationIsUsedAndKept(): void
    {
        $c = self::credentials();
        self::assertNotNull($c);
        $content = pb_setup_config_content(['host' => $c['host'], 'port' => $c['port'], 'database' => self::$database, 'user' => self::$user, 'password' => self::DB_PASSWORD]);
        file_put_contents($this->paths['config'], $content);

        $welcome = $this->request(0)['body'];
        self::assertStringContainsString('Zugangsdaten in pb_inc/mysql.inc.php vorhanden', $welcome);
        $step1 = $this->request(1)['body'];
        self::assertStringContainsString('id="pbInstallManual"', $step1);
        self::assertStringNotContainsString('id="mysql_password"', $step1);
        self::assertStringNotContainsString(self::DB_PASSWORD, $step1);

        $this->install2From1();

        self::assertSame($content, file_get_contents($this->paths['config']));
        self::assertContains('pb_admins', self::tables($this->pdo()));
        self::assertFileExists($this->paths['lock']);
    }

    #[Test]
    public function brokenConfigurationShowsNoDetails(): void
    {
        file_put_contents($this->paths['config'], pb_setup_config_content(['host' => '127.0.0.1', 'port' => 1, 'database' => 'gaestebuch_db', 'user' => 'gb_user', 'password' => 'Geheim-123']));

        $response = $this->request(0);

        self::assertSame(500, $response['status']);
        self::assertStringContainsString('Keine Verbindung zur Datenbank', $response['body']);
        self::assertStringNotContainsString('Geheim-123', $response['body']);
        self::assertStringNotContainsString('gb_user', $response['body']);
    }

    #[Test]
    public function missingWriteAccessExplainsTheManualWay(): void
    {
        $this->paths['config'] = $this->dir . '/fehlt/mysql.inc.php';

        $welcome = $this->request(0)['body'];
        self::assertStringContainsString('text-bg-danger', $welcome);
        self::assertStringContainsString('id="pbInstallRecheck"', $welcome);
        self::assertStringContainsString('pb_inc/mysql.inc.php.example', $welcome);

        $html = $this->request(1, $this->databaseForm())['body'];
        self::assertStringContainsString('PowerBook darf die Datei <code>pb_inc/mysql.inc.php</code> nicht anlegen.', $html);
        self::assertStringContainsString('pb_inc/mysql.inc.php.example', $html);
        self::assertStringNotContainsString(self::DB_PASSWORD, $html);
    }

    #[Test]
    public function failingLockFileIsReportedOnTheLastPage(): void
    {
        $this->paths['lock'] = $this->dir . '/fehlt/install.lock';

        $this->install();
        $html = $this->request(4)['body'];

        self::assertStringContainsString('id="pbInstallLockFailed"', $html);
        self::assertStringContainsString('Löschen Sie <code>install.php</code> jetzt per FTP', $html);
    }

    #[Test]
    public function deleteButtonRemovesTheInstallerFiles(): void
    {
        foreach ($this->paths['delete'] as $file) {
            file_put_contents($file, '<?php');
        }
        $this->install();

        $response = $this->request(4, ['action' => 'delete']);

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('id="pbInstallDeleted"', $response['body']);
        self::assertStringContainsString('install.php wurde gelöscht.', $response['body']);
        self::assertStringNotContainsString('id="pbInstallDelete"', $response['body']);
        foreach ($this->paths['delete'] as $file) {
            self::assertFileDoesNotExist($file);
        }
    }

    #[Test]
    public function oldInstallerNameNoLongerTouchesTheDatabase(): void
    {
        $pdo = $this->pdo();
        self::loadSqlFile($pdo, POWERBOOK_ROOT . '/powerbook.sql');
        $pdo->exec("INSERT INTO pb_entries (name, text, statement) VALUES ('Bleibt', 'Eintrag', '')");

        // Aufruf von install_deu.php?install=yes in einem eigenen PHP-Prozess
        $runner = $this->dir . '/aufruf.php';
        file_put_contents($runner, "<?php\n"
            . "\$_GET = ['install' => 'yes'];\n"
            . "\$_SERVER['REQUEST_METHOD'] = 'GET';\n"
            . 'include ' . var_export(POWERBOOK_ROOT . '/install_deu.php', true) . ";\n");
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' 2>&1');

        self::assertSame('', trim((string) $output));
        self::assertContains('pb_entries', self::tables($pdo));
        $stmt = $pdo->query("SELECT COUNT(*) FROM pb_entries WHERE name = 'Bleibt'");
        self::assertNotFalse($stmt);
        self::assertSame(1, (int) $stmt->fetchColumn());
    }

    protected function setUp(): void
    {
        parent::setUp();
        require_once POWERBOOK_ROOT . '/pb_inc/install.inc.php';
        self::freshDatabase(self::$database);
        $server = self::server();
        $server->exec("DROP USER IF EXISTS '" . self::$user . "'@'%'");
        $server->exec("CREATE USER '" . self::$user . "'@'%' IDENTIFIED BY " . $server->quote(self::DB_PASSWORD));
        $server->exec('GRANT ALL PRIVILEGES ON `' . self::$database . "`.* TO '" . self::$user . "'@'%'");

        $this->dir = self::tempDir('installer');
        $this->paths = [
            'lock' => $this->dir . '/install.lock',
            'installed' => $this->dir . '/.installed',
            'config' => $this->dir . '/pb_inc/mysql.inc.php',
            'schema' => POWERBOOK_ROOT . '/powerbook.sql',
            'logs' => $this->dir . '/logs',
            'delete' => [$this->dir . '/install.php', $this->dir . '/pb_inc/install.inc.php', $this->dir . '/install_deu.php'],
        ];
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['HTTP_HOST'] = 'gaestebuch.example';
        $_SERVER['SCRIPT_NAME'] = '/gb/install.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_HOST'], $_SERVER['SCRIPT_NAME']);
        self::removeDir($this->dir);
        parent::tearDown();
    }

    /**
     * @param array<string, string> $post
     *
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function request(int $step, array $post = [], bool $withToken = true): array
    {
        $_GET = $step > 0 ? ['step' => (string) $step] : [];
        if ($post !== [] && $withToken) {
            $post = ['csrf_token' => pb_setup_csrf_token(PB_INSTALL_CSRF)] + $post;
        }
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = $post === [] ? 'GET' : 'POST';

        return pb_install_handle($this->paths);
    }

    /**
     * @return array<string, string>
     */
    private function databaseForm(string $password = self::DB_PASSWORD, ?string $user = null, ?string $database = null): array
    {
        $c = self::credentials();
        self::assertNotNull($c);

        return [
            'mysql_host' => $c['host'],
            'mysql_port' => (string) $c['port'],
            'mysql_database' => $database ?? self::$database,
            'mysql_user' => $user ?? self::$user,
            'mysql_password' => $password,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function guestbookForm(): array
    {
        return [
            'gb_title' => 'Gästebuch Möwenblick',
            'gb_url' => 'https://www.moewenblick.example/gaestebuch/pbook.php',
            'gb_email' => 'gastgeber@example.org',
            'gb_notify' => '1',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function adminForm(string $password2 = self::ADMIN_PASSWORD): array
    {
        return [
            'admin_name' => 'Anke',
            'admin_email' => 'anke@example.org',
            'admin_password' => self::ADMIN_PASSWORD,
            'admin_password2' => $password2,
        ];
    }

    /**
     * @param array{status: int, headers: array<string, string>, body: string} $response
     */
    private static function assertRedirect(int $step, array $response): void
    {
        self::assertSame(303, $response['status'], strip_tags($response['body']));
        self::assertSame('install.php?step=' . $step, $response['headers']['Location'] ?? '');
    }

    private function pdo(): PDO
    {
        return self::connect(self::$database);
    }

    /**
     * Schritte 1–3 durchlaufen.
     */
    private function install(): void
    {
        self::assertRedirect(2, $this->request(1, $this->databaseForm()));
        self::assertRedirect(3, $this->request(2, $this->guestbookForm()));
        self::assertRedirect(4, $this->request(3, $this->adminForm()));
    }

    /**
     * @return array<string, mixed> Variablen aus der geschriebenen mysql.inc.php
     */
    private function writtenConfig(): array
    {
        return (static function (string $file): array {
            require $file;

            return get_defined_vars();
        })($this->paths['config']);
    }

    private function install2From1(): void
    {
        self::assertRedirect(2, $this->request(1, ['weiter' => '1']));
        self::assertRedirect(3, $this->request(2, $this->guestbookForm()));
        self::assertRedirect(4, $this->request(3, $this->adminForm()));
    }
}
