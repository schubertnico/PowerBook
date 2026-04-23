<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminEmailFunctionTest extends TestCase
{
    public function testSendAdminEmailIsDefinedAtRootScope(): void
    {
        // BUG-006: Helpers wurden in admin_email_helpers.inc.php ausgelagert.
        $helpers = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admin_email_helpers.inc.php');
        self::assertNotFalse($helpers);

        self::assertStringNotContainsString(
            "if (!function_exists('sendAdminEmail'))",
            $helpers,
            'sendAdminEmail muss ausserhalb eines if-Blocks deklariert werden.'
        );

        self::assertStringContainsString(
            'function sendAdminEmail(',
            $helpers,
            'sendAdminEmail() muss weiterhin deklariert sein.'
        );
    }

    public function testAdminsIncLoadsHelpersViaRequireOnce(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admins.inc.php');
        self::assertNotFalse($source);
        self::assertStringContainsString(
            "require_once __DIR__ . '/admin_email_helpers.inc.php'",
            $source,
            'admins.inc.php muss admin_email_helpers.inc.php per require_once laden.'
        );
    }

    public function testRelatedHelperFunctionsAreAtRootScope(): void
    {
        $helpers = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admin_email_helpers.inc.php');
        self::assertNotFalse($helpers);

        $fns = [
            'formatPermission',
            'formatAdminPermissions',
            'getEmailFooter',
            'buildAddedEmailBody',
            'buildEditedEmailBody',
            'buildDeletedEmailBody',
        ];
        foreach ($fns as $fn) {
            self::assertStringNotContainsString(
                "if (!function_exists('{$fn}'))",
                $helpers,
                "{$fn}() muss ausserhalb eines if-Blocks deklariert werden (hoisting)."
            );
        }
    }
}
