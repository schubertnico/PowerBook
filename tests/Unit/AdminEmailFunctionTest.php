<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminEmailFunctionTest extends TestCase
{
    public function testSendAdminEmailIsDefinedAtRootScope(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admins.inc.php');
        self::assertNotFalse($source);

        self::assertStringNotContainsString(
            "if (!function_exists('sendAdminEmail'))",
            $source,
            'sendAdminEmail muss ausserhalb eines if-Blocks deklariert werden.'
        );

        self::assertStringContainsString(
            'function sendAdminEmail(',
            $source,
            'sendAdminEmail() muss weiterhin deklariert sein.'
        );
    }

    public function testRelatedHelperFunctionsAreAtRootScope(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admins.inc.php');
        self::assertNotFalse($source);

        $helpers = [
            'formatPermission',
            'formatAdminPermissions',
            'getEmailFooter',
            'buildAddedEmailBody',
            'buildEditedEmailBody',
            'buildDeletedEmailBody',
        ];
        foreach ($helpers as $helper) {
            self::assertStringNotContainsString(
                "if (!function_exists('{$helper}'))",
                $source,
                "{$helper}() muss ausserhalb eines if-Blocks deklariert werden (hoisting)."
            );
        }
    }
}
