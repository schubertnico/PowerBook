<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookDirectAccessTest extends TestCase
{
    public function testGuestbookFileHasDirectAccessGuard(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        self::assertNotFalse($source);

        self::assertStringContainsString(
            "if (!defined('PB_ENTRY'))",
            $source,
            'guestbook.inc.php muss einen Direktaufruf-Guard via PB_ENTRY enthalten.'
        );
        self::assertStringContainsString(
            'http_response_code(403)',
            $source,
            'Direktaufruf muss HTTP 403 liefern.'
        );
    }

    public function testPbookPhpSetsPbEntryConstant(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pbook.php');
        self::assertNotFalse($source);
        self::assertStringContainsString(
            "define('PB_ENTRY'",
            $source,
            'pbook.php muss PB_ENTRY definieren, damit guestbook.inc.php korrekt inkludiert werden kann.'
        );
    }

    public function testDirectHttpAccessBlocked(): void
    {
        $ch = curl_init('http://localhost:8080/pb_inc/guestbook.inc.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            self::markTestSkipped('Apache nicht erreichbar aus Test-Umgebung.');
        }

        self::assertTrue(
            $httpCode === 403 || strlen((string) $body) < 200,
            sprintf('Direktaufruf muss blockiert sein. HTTP=%d Bytes=%d', $httpCode, strlen((string) $body))
        );
    }
}
