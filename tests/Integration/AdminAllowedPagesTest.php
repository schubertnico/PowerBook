<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class AdminAllowedPagesTest extends TestCase
{
    public function testPseudoPagesAreNotWhitelisted(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/index.php');
        self::assertNotFalse($source);

        // Nur den Whitelist-Block pruefen, sonst triggern andere $_GET['page']-Vergleiche die Assertion.
        $parts = explode('// Get request parameters', $source, 2);
        $whitelistBlock = $parts[0];

        $forbidden = ['emails', 'pages', 'empty'];
        foreach ($forbidden as $page) {
            self::assertDoesNotMatchRegularExpression(
                "/'{$page}'/",
                $whitelistBlock,
                sprintf('"%s" darf nicht in $allowedPages stehen.', $page)
            );
        }
    }

    public function testRealPagesStillWhitelisted(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/index.php');
        self::assertNotFalse($source);

        $required = ['home', 'login', 'logout', 'license', 'admins', 'entries',
                     'configuration', 'password', 'release', 'entry', 'edit', 'statement'];
        foreach ($required as $page) {
            self::assertMatchesRegularExpression(
                "/'{$page}'/",
                $source,
                sprintf('"%s" muss in $allowedPages stehen.', $page)
            );
        }
    }
}
