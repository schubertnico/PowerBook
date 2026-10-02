<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PasswordResetTokenFlowTest extends TestCase
{
    public function testPasswordFileUsesResetToken(): void
    {
        $source = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        $helpers = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admin_email_helpers.inc.php');

        self::assertStringContainsString('reset_token', $source, 'Token-Flow muss reset_token nutzen.');
        self::assertStringContainsString('reset_token_expires', $helpers);
        self::assertStringContainsString("hash('sha256', \$token)", $helpers, 'In der Datenbank steht nur der Hash des Links.');
    }

    public function testRecoveryResponseIsGeneric(): void
    {
        $source = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        self::assertStringNotContainsString(
            'Admin in Datenbank nicht gefunden',
            $source,
            'Enumerations-freundliche Meldung darf nicht mehr verwendet werden (BUG-009).'
        );
        self::assertMatchesRegularExpression('/Falls\s+ein\s+Konto/i', $source, 'Antwort muss allgemein sein.');
    }

    public function testRecoveryDoesNotDirectlyUpdatePasswordInRecoverBranch(): void
    {
        $source = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        // Der Anforderungs-Zweig darf kein Passwort setzen (nur nach Prüfung des Links).
        if (!preg_match('/if \(\$action === \'recover\'\) \{(.*?)pb_admin_card_open\(/s', $source, $m)) {
            self::fail('Anforderungs-Zweig nicht gefunden.');
        }
        self::assertStringNotContainsString('SET password', $m[1]);
    }

    public function testPasswordChangeLogsOutOtherSessions(): void
    {
        $source = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        self::assertMatchesRegularExpression('/SET password = \?, reset_token = NULL, reset_token_expires = NULL, pw_changed = \?/', $source);

        $account = (string) file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/account.inc.php');
        self::assertStringContainsString('pw_changed = ?', $account);
        self::assertStringContainsString("\$_SESSION['pb_login_time'] = \$now", $account);
    }
}
