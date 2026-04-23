<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookCsrfIntegrationTest extends TestCase
{
    public function testLogCsrfFailureIsAvailableInGuestbookScope(): void
    {
        $guestbookContent = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        self::assertNotFalse($guestbookContent);
        self::assertStringContainsString(
            'error-handler.inc.php',
            $guestbookContent,
            'guestbook.inc.php muss error-handler.inc.php inkludieren, damit logCsrfFailure() verfuegbar ist.'
        );
    }
}
