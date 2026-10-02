<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Laufzeit ohne Installation bzw. ohne Datenbank: Gästebuch und AdminCenter
 * zeigen eine kleine Seite statt einer Rohmeldung (Befunde B20, A30, I05).
 */
final class SetupPagesTest extends TestCase
{
    private string $copy = '';

    #[Test]
    public function notInstalledPageLinksToTheInstaller(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = POWERBOOK_ROOT . '/pbook.php';

        $response = pb_setup_not_installed_response();

        self::assertSame(503, $response['status']);
        self::assertStringContainsString('<title>PowerBook ist noch nicht eingerichtet</title>', $response['body']);
        self::assertStringContainsString('id="pbSetupInstall"', $response['body']);
        self::assertStringContainsString('href="install.php"', $response['body']);
        self::assertStringNotContainsString('Version', $response['body'], 'öffentlich keine Versionsnummer');
    }

    #[Test]
    public function adminCenterLinksTwoFoldersUp(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = POWERBOOK_ROOT . '/pb_inc/admincenter/index.php';

        self::assertSame('../../', pb_setup_relative_root());
        self::assertStringContainsString('href="../../install.php"', pb_setup_not_installed_response()['body']);
        self::assertStringContainsString('href="../../assets/powerbook.css', pb_setup_unavailable_response()['body']);
    }

    #[Test]
    public function unavailablePageIsNeutral(): void
    {
        $response = pb_setup_unavailable_response();

        self::assertSame(503, $response['status']);
        self::assertSame('300', $response['headers']['Retry-After']);
        self::assertStringContainsString('Die Datenbank ist gerade nicht erreichbar', $response['body']);
        self::assertStringNotContainsString('SQLSTATE', $response['body']);
    }

    #[Test]
    public function connectionErrorFromGetDatabaseHasNoDetails(): void
    {
        $source = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/database.inc.php');

        self::assertStringContainsString("throw new RuntimeException('Die Datenbank ist gerade nicht erreichbar.', 0, \$e);", $source);
        self::assertStringNotContainsString('htmlspecialchars($e->getMessage()', $source);
    }

    #[Test]
    public function missingMysqlIncShowsTheNotInstalledPage(): void
    {
        $output = $this->runConnectCopy(false);

        self::assertStringContainsString('PowerBook ist noch nicht eingerichtet', $output);
        self::assertStringContainsString('install.php', $output);
    }

    #[Test]
    public function failingConnectionShowsANeutralPage(): void
    {
        $output = $this->runConnectCopy(true);

        self::assertStringContainsString('Die Datenbank ist gerade nicht erreichbar', $output);
        self::assertStringNotContainsString('gb_user', $output);
        self::assertStringNotContainsString('Geheim', $output);
        self::assertStringNotContainsString('SQLSTATE', $output);
        $log = (string) @file_get_contents($this->copy . '/logs/error.log');
        self::assertStringContainsString('Datenbankverbindung fehlgeschlagen', $log, 'Details stehen im Log');
    }

    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/setup.inc.php';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['SCRIPT_FILENAME']);
        if ($this->copy !== '' && is_dir($this->copy)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->copy, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->copy);
        }
    }

    /**
     * Bindet mysql-connect.inc.php in einer Kopie von pb_inc/ in einem eigenen
     * PHP-Prozess ein – mit oder ohne pb_inc/mysql.inc.php.
     */
    private function runConnectCopy(bool $withConfig): string
    {
        $this->copy = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pbtest-connect-' . getmypid();
        @mkdir($this->copy . '/pb_inc', 0o777, true);
        @mkdir($this->copy . '/logs', 0o777, true);
        foreach (['mysql-connect.inc.php', 'database.inc.php', 'setup.inc.php', 'version.inc.php'] as $file) {
            copy(POWERBOOK_ROOT . '/pb_inc/' . $file, $this->copy . '/pb_inc/' . $file);
        }
        if ($withConfig) {
            file_put_contents($this->copy . '/pb_inc/mysql.inc.php', pb_setup_config_content([
                'host' => '127.0.0.1', 'port' => 1, 'database' => 'gaestebuch_db', 'user' => 'gb_user', 'password' => 'Geheim-123',
            ]));
        }
        $runner = $this->copy . '/pbook.php';
        file_put_contents($runner, "<?php\nrequire __DIR__ . '/pb_inc/mysql-connect.inc.php';\necho 'WEITER';\n");

        $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' 2>&1');
        self::assertStringNotContainsString('WEITER', $output, 'nach der Seite ist Schluss');

        return $output;
    }
}
