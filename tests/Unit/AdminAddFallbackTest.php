<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * IMP-008 / A15: Neue Admins bekommen kein Passwort per Mail, sondern einen
 * Link „Passwort festlegen“. Kommt die Mail nicht an, zeigt die Admins-Seite
 * den Link einmal an, damit sich niemand aussperrt.
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
            'sendAdminEmail muss bool zurückgeben (IMP-008).'
        );
        self::assertStringContainsString('return pb_mail(', $source, 'Admin-Mails laufen über pb_mail().');
    }

    public function testAdminsIncHandlesMailFailureWithLink(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admins.inc.php');
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression('/\$mailSent\s*=\s*sendAdminEmail\(/', $source);
        self::assertMatchesRegularExpression('/if\s*\(!?\$mailSent\)/', $source);
        self::assertStringContainsString('id="pbAdminLink"', $source, 'Bei Mail-Fehler wird der Link angezeigt.');
        self::assertStringNotContainsString('Initial-Passwort', $source, 'Kein Klartext-Passwort mehr.');
        self::assertStringNotContainsString('edit_password1', $source, 'Passwörter anderer werden nicht mehr eingetippt.');
    }
}
