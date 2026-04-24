<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class InstallLockTest extends TestCase
{
    public function testInstallDeuChecksLockFile(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/install_deu.php');
        self::assertNotFalse($source);

        self::assertStringContainsString(
            '.installed',
            $source,
            'install_deu.php muss das .installed Lock-File referenzieren.'
        );
        self::assertStringContainsString(
            'file_exists',
            $source,
            'install_deu.php muss file_exists-Check fuer Lock-File enthalten.'
        );
        self::assertStringContainsString(
            'http_response_code(403)',
            $source,
            'install_deu.php muss HTTP 403 liefern, wenn bereits installiert.'
        );
    }

    public function testInstallDeuCreatesLockAfterSuccess(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/install_deu.php');
        self::assertNotFalse($source);

        self::assertStringContainsString(
            "file_put_contents(__DIR__ . '/.installed'",
            $source,
            'install_deu.php muss nach erfolgreicher Installation das Lock-File schreiben.'
        );
    }
}
