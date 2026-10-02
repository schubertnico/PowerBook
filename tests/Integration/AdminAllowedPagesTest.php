<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class AdminAllowedPagesTest extends TestCase
{
    public function testHelperFilesAreNotWhitelisted(): void
    {
        $pages = $this->allowedPages();

        foreach (['emails', 'pages', 'empty', 'entry', 'layout', 'auth', 'config', 'admin_email_helpers', 'password_migrate'] as $page) {
            self::assertNotContains($page, $pages, sprintf('"%s" darf nicht in $allowedPages stehen.', $page));
        }
    }

    public function testRealPagesAreWhitelisted(): void
    {
        $pages = $this->allowedPages();

        foreach (['home', 'login', 'logout', 'license', 'admins', 'entries', 'configuration', 'password', 'release', 'edit', 'statement', 'account'] as $page) {
            self::assertContains($page, $pages, sprintf('"%s" muss in $allowedPages stehen.', $page));
        }
    }

    public function testEveryWhitelistedPageExists(): void
    {
        foreach ($this->allowedPages() as $page) {
            self::assertFileExists(POWERBOOK_ROOT . '/pb_inc/admincenter/' . $page . '.inc.php');
        }
    }

    public function testPasswordMigrationIsNoLongerIncluded(): void
    {
        $source = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/index.php');

        self::assertStringNotContainsString('password_migrate', $source);
    }

    /**
     * @return list<string>
     */
    private function allowedPages(): array
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/index.php');
        self::assertNotFalse($source);
        self::assertSame(1, preg_match('/\$allowedPages\s*=\s*\[(.*?)\];/s', $source, $match));
        preg_match_all("/'([a-z_]+)'/", $match[1], $names);

        return $names[1];
    }
}
