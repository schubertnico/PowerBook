<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InstallSecurityTest extends TestCase
{
    public function testInstallScriptDoesNotHardcodePassword(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/install_deu.php');
        self::assertNotFalse($source);
        // Hardcoded password 'powerbook' as default must be gone
        self::assertStringNotContainsString(
            "password_hash('powerbook'",
            $source,
            'install_deu.php darf kein hardcoded Initial-Passwort mehr verwenden (IMP-006).'
        );
        self::assertStringNotContainsString(
            '<b>Passwort:</b> powerbook<br>',
            $source,
            'Die UI darf nicht mehr das Wort "powerbook" als Standard-Passwort anzeigen.'
        );
    }

    public function testInstallScriptUsesRandomPassword(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/install_deu.php');
        self::assertNotFalse($source);
        self::assertStringContainsString(
            'random_bytes',
            $source,
            'install_deu.php muss random_bytes fuer das Initial-Passwort verwenden.'
        );
    }
}
