<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EntryHomepageLinkTest extends TestCase
{
    public function testEntryIncPhpDefinesHomepageLink(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/entry.inc.php');
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/\$homepage_link\s*=/',
            $source,
            'entry.inc.php muss die Variable $homepage_link fuer Konsumenten setzen.'
        );
    }

    public function testAdminEntryIncPhpDefinesHomepageLink(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php');
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/\$homepage_link\s*=/',
            $source,
            'admincenter/entry.inc.php muss die Variable $homepage_link setzen.'
        );
    }

    public function testGuestbookPreviewPreservesRawValues(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/\$raw_name\s*=/',
            $source,
            'Preview-Pfad muss Roh-Werte in $raw_name bewahren (BUG-003).'
        );
        self::assertMatchesRegularExpression(
            '/\$raw_url\s*=/',
            $source,
            'Preview-Pfad muss Roh-Werte in $raw_url bewahren.'
        );
    }
}
