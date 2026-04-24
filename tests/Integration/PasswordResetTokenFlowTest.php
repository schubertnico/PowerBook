<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PasswordResetTokenFlowTest extends TestCase
{
    public function testPasswordFileUsesResetToken(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        self::assertNotFalse($source);
        self::assertStringContainsString('reset_token', $source, 'Token-Flow muss reset_token nutzen.');
        self::assertStringContainsString('reset_token_expires', $source);
    }

    public function testRecoveryResponseIsGeneric(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        self::assertStringNotContainsString(
            'Admin in Datenbank nicht gefunden',
            $source,
            'Enumerations-freundliche Meldung darf nicht mehr verwendet werden (BUG-009).'
        );
        self::assertMatchesRegularExpression(
            '/Falls\s+ein\s+Konto/i',
            $source,
            'Response-Text muss generisch sein.'
        );
    }

    public function testRecoveryDoesNotDirectlyUpdatePasswordInRecoverBranch(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        // The recover branch must NOT contain a direct password update without token validation.
        // Heuristic: between "action === 'recover'" and next regenerateCsrfToken, no UPDATE ... SET password
        if (!preg_match('/action === \'recover\'(.*?)regenerateCsrfToken/s', $source, $m)) {
            self::fail('Recover-Block nicht gefunden.');
        }
        self::assertStringNotContainsString(
            'SET password',
            $m[1],
            'Recover-Block darf kein direktes SET password enthalten (nur nach Token-Validierung).'
        );
    }

    public function testMigrationFileExists(): void
    {
        self::assertFileExists(
            POWERBOOK_ROOT . '/pb_inc/admincenter/password_migrate.php',
            'Migration-Helper muss existieren.'
        );
    }
}
