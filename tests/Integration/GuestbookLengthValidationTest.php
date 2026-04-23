<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookLengthValidationTest extends TestCase
{
    public function testGuestbookRejectsOverlongName(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        self::assertNotFalse($source);

        self::assertMatchesRegularExpression(
            '/mb_strlen\s*\(\s*\$name\s*\)\s*>\s*\d+/',
            $source,
            'guestbook.inc.php muss serverseitig die Namenslaenge pruefen.'
        );
    }

    public function testGuestbookRejectsOverlongText(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/mb_strlen\s*\(\s*\$text\s*\)\s*>\s*\d+/',
            $source,
            'guestbook.inc.php muss serverseitig die Textlaenge pruefen.'
        );
    }

    public function testAdminEditValidatesLengths(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/edit.inc.php');
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/mb_strlen\s*\(\s*\$edit_name\s*\)/',
            $source,
            'edit.inc.php muss mb_strlen fuer edit_name pruefen.'
        );
    }
}
