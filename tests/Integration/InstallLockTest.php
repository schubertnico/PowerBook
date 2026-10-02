<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Sperre des Installers: install.lock (3.1), .installed (3.0) und die
 * Regel in der .htaccess. Ohne Datenbank: Die Sperre greift vor jeder Verbindung.
 */
final class InstallLockTest extends TestCase
{
    private string $dir;

    /** @var array{lock: string, installed: string, config: string, schema: string, logs: string, delete: list<string>} */
    private array $paths;

    #[Test]
    public function lockFileBlocksEveryStepWithoutShowingTheServerPath(): void
    {
        file_put_contents($this->paths['lock'], 'PowerBook 3.1.0');

        foreach ([0, 1, 2, 3, 4] as $step) {
            $response = $this->request($step);
            self::assertSame(403, $response['status']);
            self::assertStringContainsString('PowerBook ist bereits installiert', $response['body']);
            self::assertStringContainsString('<code>install.lock</code>', $response['body']);
            self::assertStringContainsString('update.php', $response['body']);
            self::assertStringNotContainsString($this->dir, $response['body']);
            self::assertStringNotContainsString(str_replace('\\', '/', $this->dir), $response['body']);
        }
    }

    #[Test]
    public function installedFileFromVersion30AlsoBlocks(): void
    {
        file_put_contents($this->paths['installed'], '2026-05-10');

        $response = $this->request(0);

        self::assertSame(403, $response['status']);
        self::assertStringContainsString('<code>.installed</code>', $response['body']);
        self::assertStringContainsString('<title>PowerBook ist bereits installiert – PowerBook-Installer</title>', $response['body']);
    }

    #[Test]
    public function lastStepStaysVisibleForTheInstallingSession(): void
    {
        file_put_contents($this->paths['lock'], 'PowerBook 3.1.0');
        $_SESSION['pb_install'] = ['done' => true, 'admin' => 'Anke', 'lockFailed' => false];

        $response = $this->request(4);

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('Fertig: PowerBook ist installiert', $response['body']);
        self::assertSame(403, $this->request(1)['status']);
    }

    #[Test]
    public function htaccessBlocksTheInstallerAndTheOldName(): void
    {
        $htaccess = (string) file_get_contents(POWERBOOK_ROOT . '/.htaccess');

        self::assertStringContainsString('RewriteCond %1install.lock -f', $htaccess);
        self::assertStringContainsString('RewriteCond %1.installed -f', $htaccess);
        self::assertStringContainsString('RewriteCond %{QUERY_STRING} !(^|&)step=4(&|$)', $htaccess);
        self::assertStringContainsString('RewriteRule ^install\.php$ - [F,L]', $htaccess);
        self::assertMatchesRegularExpression('/<Files "install_deu\.php">\s*<IfModule mod_authz_core\.c>\s*Require all denied/', $htaccess);
        self::assertStringNotContainsString('session.cookie_secure Off', $htaccess);
    }

    #[Test]
    public function lockAndSchemaFilesAreNotPublic(): void
    {
        $htaccess = (string) file_get_contents(POWERBOOK_ROOT . '/.htaccess');

        // install.lock, powerbook.sql und mysql.inc.php.example über die Endungsliste
        self::assertMatchesRegularExpression('/FilesMatch "\(\?i\)\\\\\.\([^"]*\block\b[^"]*\bsql\b[^"]*\bexample\b/', $htaccess);
    }

    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/install.inc.php';
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pbtest-lock-' . getmypid();
        @mkdir($this->dir . DIRECTORY_SEPARATOR . 'pb_inc', 0o777, true);
        $this->paths = [
            'lock' => $this->dir . '/install.lock',
            'installed' => $this->dir . '/.installed',
            'config' => $this->dir . '/pb_inc/mysql.inc.php',
            'schema' => POWERBOOK_ROOT . '/powerbook.sql',
            'logs' => $this->dir . '/logs',
            'delete' => [],
        ];
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        foreach (['install.lock', '.installed'] as $file) {
            @unlink($this->dir . '/' . $file);
        }
        @rmdir($this->dir . '/pb_inc');
        @rmdir($this->dir);
        $_SESSION = [];
        $_GET = [];
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function request(int $step): array
    {
        $_GET = $step > 0 ? ['step' => (string) $step] : [];

        return pb_install_handle($this->paths);
    }
}
