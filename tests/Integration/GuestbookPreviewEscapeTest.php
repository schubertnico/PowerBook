<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookPreviewEscapeTest extends TestCase
{
    public function testPreviewDoesNotDoubleEscapeHiddenFields(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        self::assertNotFalse($source);

        self::assertMatchesRegularExpression(
            '/\$raw_name\s*=/',
            $source,
            'Preview-Pfad muss Roh-Werte in $raw_name bewahren (kein Doppel-Escape).'
        );
        self::assertMatchesRegularExpression(
            '/\$raw_url\s*=/',
            $source,
            'Preview-Pfad muss Roh-Werte in $raw_url bewahren.'
        );
        self::assertMatchesRegularExpression(
            '/name="name2" value="\' \. e\(\$raw_name\)/',
            $source,
            'Hidden-Field name2 muss aus $raw_name erzeugt werden.'
        );
    }
}
