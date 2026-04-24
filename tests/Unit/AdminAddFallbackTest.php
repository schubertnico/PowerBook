<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * IMP-008 Regression: Admin-Add muss bei SMTP-Fehler das Temp-Passwort
 * einmalig im UI anzeigen (Self-Lockout vermeiden).
 */
final class AdminAddFallbackTest extends TestCase
{
    public function testSendAdminEmailReturnsBool(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admin_email_helpers.inc.php');
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/function sendAdminEmail\(string \$type, array \$data\): bool/',
            $source,
            'sendAdminEmail muss bool zurueckgeben (IMP-008).'
        );
    }

    public function testAdminsIncHandlesMailFailure(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admins.inc.php');
        self::assertNotFalse($source);
        // The add-branch must capture the return value and react on false.
        self::assertMatchesRegularExpression(
            '/\$mailSent\s*=\s*sendAdminEmail\(/',
            $source,
            'admins.inc.php muss den Rueckgabewert von sendAdminEmail erfassen.'
        );
        self::assertMatchesRegularExpression(
            '/if\s*\(!?\$mailSent\)/',
            $source,
            'admins.inc.php muss auf Mail-Fail reagieren.'
        );
        self::assertStringContainsString(
            'Notieren Sie das Initial-Passwort jetzt',
            $source,
            'Bei Mail-Fail muss das Temp-Passwort im UI angezeigt werden.'
        );
    }
}
