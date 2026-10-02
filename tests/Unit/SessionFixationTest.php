<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SessionFixationTest extends TestCase
{
    public function testLoginRegeneratesSessionIdAndToken(): void
    {
        $auth = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/auth.inc.php');
        self::assertNotFalse($auth);

        self::assertMatchesRegularExpression(
            '/function pb_admin_start_session\b.*?session_regenerate_id\s*\(\s*true\s*\).*?regenerateCsrfToken\s*\(\s*\)/s',
            $auth,
            'Die Anmeldung muss session_regenerate_id(true) und regenerateCsrfToken() aufrufen.'
        );
    }

    public function testIndexUsesStartSessionOnLogin(): void
    {
        $index = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/index.php');
        self::assertNotFalse($index);

        self::assertStringContainsString('pb_admin_start_session($account, $now)', $index);
        self::assertStringContainsString('pb_session_start()', $index);
    }

    public function testLogoutRegeneratesSessionId(): void
    {
        $auth = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/auth.inc.php');
        self::assertNotFalse($auth);

        self::assertMatchesRegularExpression(
            '/function pb_admin_end_session\b.*?\$_SESSION\s*=\s*\[\].*?session_regenerate_id\s*\(\s*true\s*\)/s',
            $auth
        );
    }

    public function testOnlyLoginRegeneratesCsrfToken(): void
    {
        foreach (['edit', 'statement', 'release', 'entries', 'home', 'login', 'logout'] as $page) {
            $source = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/' . $page . '.inc.php');
            self::assertStringNotContainsString('regenerateCsrfToken', $source, $page . '.inc.php');
        }
    }
}
