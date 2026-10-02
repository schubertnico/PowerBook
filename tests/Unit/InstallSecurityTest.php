<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Sicherheit des Installers ohne Datenbank: Token, nichts per GET,
 * kein Rohtext bei Verbindungsfehlern, korrekt geschriebene mysql.inc.php,
 * Versionsprüfung und der Platzhalter install_deu.php.
 */
final class InstallSecurityTest extends TestCase
{
    /** @var array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} */
    private array $paths;

    #[Test]
    public function postWithoutOrWithWrongTokenIsRejected(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = ['step' => '1'];
        $_POST = ['mysql_host' => 'localhost'];

        $response = pb_install_handle($this->paths);
        self::assertSame(403, $response['status']);
        self::assertStringContainsString('Sicherheitsfehler', $response['body']);

        $_POST['csrf_token'] = 'falsch';
        self::assertSame(403, pb_install_handle($this->paths)['status']);
        self::assertArrayNotHasKey('db', $_SESSION['pb_install']);
    }

    #[Test]
    public function nothingHappensPerGet(): void
    {
        foreach (['2' => 1, '3' => 1, '4' => 1] as $step => $target) {
            $_GET = ['step' => (string) $step, 'install' => 'yes'];
            $response = pb_install_handle($this->paths);
            self::assertSame(303, $response['status']);
            self::assertSame('install.php?step=' . $target, $response['headers']['Location']);
        }
        self::assertFileDoesNotExist($this->paths['lock']);
    }

    #[Test]
    public function formsCarryTheTokenAndNeverPrefillThePassword(): void
    {
        $_GET = ['step' => '1'];
        $_SESSION['pb_install'] = ['db' => ['host' => 'sql.example.org', 'port' => 3306, 'database' => 'gaestebuch_db', 'user' => 'gb_user', 'password' => 'Moewenblick-DB26']];

        $html = pb_install_handle($this->paths)['body'];

        self::assertStringContainsString('name="csrf_token" value="' . $_SESSION['pb_install_csrf'] . '"', $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('value="sql.example.org"', $html);
        self::assertStringNotContainsString('Moewenblick-DB26', $html);
        foreach (['mysql_host', 'mysql_port', 'mysql_database', 'mysql_user', 'mysql_password', 'pbInstallNext', 'pbInstallBack'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $html);
        }
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function connectionErrors(): array
    {
        return [
            'Passwort' => [1045, 'Die Datenbank hat die Anmeldung abgelehnt: Benutzername oder Passwort stimmen nicht.'],
            'Rechte' => [1044, 'Der Benutzer darf nicht auf die Datenbank „gaestebuch_db“ zugreifen.'],
            'Datenbank' => [1049, 'Die Datenbank „gaestebuch_db“ gibt es auf dem Server nicht.'],
            'Server' => [2002, 'Der Datenbankserver „sql.example.org“ ist nicht erreichbar. Prüfen Sie Servername und Port.'],
            'Name' => [2005, 'Der Datenbankserver „sql.example.org“ ist nicht erreichbar.'],
            'Sonstiges' => [1234, 'Die Verbindung zur Datenbank ist fehlgeschlagen (Fehler 1234).'],
        ];
    }

    #[Test]
    #[DataProvider('connectionErrors')]
    public function connectionErrorsAreExplainedWithoutRawMessage(int $code, string $expected): void
    {
        $e = new PDOException("SQLSTATE[HY000] [{$code}] Access denied for user 'gb_user'@'10.142.0.6' (using password: YES)", $code);

        $message = pb_setup_connect_error($e, 'sql.example.org', 'gaestebuch_db');

        self::assertStringContainsString($expected, $message);
        self::assertStringNotContainsString('10.142.0.6', $message);
        self::assertStringNotContainsString('gb_user', $message);
    }

    #[Test]
    public function errorCodeIsReadFromTheMessageWhenTheCodeIsAString(): void
    {
        $e = new PDOException('SQLSTATE[HY000] [2002] Connection refused');

        self::assertSame(2002, pb_setup_error_code($e));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function serverVersions(): array
    {
        return [
            'MySQL 8.0' => ['8.0.46', true],
            'MySQL 8.4' => ['8.4.3', true],
            'MySQL 5.7' => ['5.7.44-log', false],
            'MariaDB 10.6' => ['10.6.22-MariaDB-ubu2004', true],
            'MariaDB 11.4' => ['11.4.5-MariaDB', true],
            'MariaDB 10.5' => ['10.5.27-MariaDB', false],
            'MariaDB mit Präfix' => ['5.5.5-10.11.6-MariaDB', true],
        ];
    }

    #[Test]
    #[DataProvider('serverVersions')]
    public function serverVersionIsChecked(string $version, bool $ok): void
    {
        $problem = pb_setup_version_problem($version);

        if ($ok) {
            self::assertNull($problem);
        } else {
            self::assertNotNull($problem);
            self::assertStringContainsString('PowerBook braucht MySQL 8.0 oder MariaDB 10.6 oder neuer.', $problem);
        }
    }

    #[Test]
    public function writtenConfigurationKeepsEveryValueExactly(): void
    {
        $db = [
            'host' => 'sql.example.org',
            'port' => 3307,
            'database' => "gäste'buch",
            'user' => 'gb_user\\',
            'password' => "Mö'we\\\\\$blick\"\n<?php echo 1; ?>",
        ];
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pbtest-mysql-' . getmypid() . '.php';
        file_put_contents($file, pb_setup_config_content($db, pb_setup_table_names(['pb_admin' => 'gb_admins']), mktime(20, 30, 0, 10, 2, 2026)));

        $vars = (static function (string $configFile): array {
            require $configFile;

            return get_defined_vars();
        })($file);
        $content = (string) file_get_contents($file);
        unlink($file);

        self::assertSame($db['host'], $vars['config_sql_server']);
        self::assertSame(3307, $vars['config_sql_port']);
        self::assertSame($db['database'], $vars['config_sql_database']);
        self::assertSame($db['user'], $vars['config_sql_user']);
        self::assertSame($db['password'], $vars['config_sql_password']);
        self::assertSame('gb_admins', $vars['pb_admin']);
        self::assertSame('pb_config', $vars['pb_config']);
        self::assertStringContainsString('angelegt von install.php am 02.10.2026 um 20:30 Uhr', $content);
        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $content);
    }

    #[Test]
    public function tableNamesFromOldConfigurationsAreChecked(): void
    {
        $names = pb_setup_table_names(['pb_admin' => 'gb_admins', 'pb_config' => 'x; DROP TABLE y', 'pb_entries' => '']);

        self::assertSame('gb_admins', $names['pb_admins']);
        self::assertSame('pb_config', $names['pb_config']);
        self::assertSame('pb_entries', $names['pb_entries']);
        self::assertSame('pb_login_attempts', $names['pb_login_attempts']);
    }

    #[Test]
    public function placeholderInstallDeuOnlyRedirects(): void
    {
        $source = (string) file_get_contents(POWERBOOK_ROOT . '/install_deu.php');

        self::assertStringContainsString("header('Location: install.php', true, 301);", $source);
        foreach (['DROP', 'CREATE', 'INSERT', 'PDO', 'getDatabase', 'mysql.inc.php', 'install=yes'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    #[Test]
    public function entryPointsCheckThePhpVersionBeforeLoadingModernCode(): void
    {
        foreach (['install.php' => 'install.inc.php', 'update.php' => 'update.inc.php'] as $entry => $logic) {
            $source = (string) file_get_contents(POWERBOOK_ROOT . '/' . $entry);
            $check = strpos($source, "version_compare(PHP_VERSION, \$pbRequiredPhp, '<')");
            $require = strpos($source, "require_once __DIR__ . '/pb_inc/" . $logic . "'");
            self::assertNotFalse($check, $entry);
            self::assertNotFalse($require, $entry);
            self::assertLessThan($require, $check, $entry);
            self::assertStringContainsString("\$pbRequiredPhp = '8.4.0';", $source);
            self::assertStringNotContainsString('match (', $source);
            self::assertStringNotContainsString('fn (', $source);
            self::assertStringNotContainsString('?->', $source);
        }
    }

    #[Test]
    public function installerDoesNotCreateADefaultAdministrator(): void
    {
        self::assertStringNotContainsString('INSERT INTO pb_admins', (string) file_get_contents(POWERBOOK_ROOT . '/powerbook.sql'));
        $logic = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/install.inc.php');
        self::assertStringNotContainsString('random_bytes', $logic, 'kein erzeugtes Passwort, das nur einmal im Browser steht');
        self::assertStringContainsString('password_hash($admin[\'password\'], PASSWORD_DEFAULT)', $logic);
    }

    #[Test]
    public function addressSuggestionAndNormalisation(): void
    {
        $_SERVER['HTTP_HOST'] = 'www.moewenblick.example';
        $_SERVER['SCRIPT_NAME'] = '/gaestebuch/install.php';
        $_SERVER['HTTPS'] = 'on';

        self::assertSame('https://www.moewenblick.example/gaestebuch/', pb_setup_base_url());
        self::assertSame('https://www.moewenblick.example/gaestebuch/', pb_install_normalize_url('https://www.moewenblick.example/gaestebuch/pbook.php?x=1'));
        self::assertSame('https://www.moewenblick.example/', pb_install_normalize_url('https://www.moewenblick.example'));
        self::assertTrue(pb_setup_valid_url('https://www.moewenblick.example/'));
        self::assertFalse(pb_setup_valid_url('javascript:alert(1)'));
        self::assertFalse(pb_setup_valid_url('www.moewenblick.example'));

        unset($_SERVER['HTTP_HOST'], $_SERVER['SCRIPT_NAME'], $_SERVER['HTTPS']);
        self::assertSame('', pb_setup_base_url());
    }

    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/install.inc.php';
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pbtest-security-' . getmypid();
        $this->paths = [
            'lock' => $dir . '/install.lock',
            'installed' => $dir . '/.installed',
            'config' => $dir . '/pb_inc/mysql.inc.php',
            'schema' => POWERBOOK_ROOT . '/powerbook.sql',
            'logs' => $dir . '/logs',
            'delete' => [],
        ];
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}
