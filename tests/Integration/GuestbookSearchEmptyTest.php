<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookSearchEmptyTest extends TestCase
{
    public function testSearchEmptyMessageIsNotDoubleEscaped(): void
    {
        $response = @file_get_contents(
            'http://localhost:8080/pbook.php?tmp_where=name&tmp_search=UNLIKELY_STRING_XZY42'
        );

        if ($response === false) {
            self::markTestSkipped('Apache-Container nicht erreichbar.');
        }

        self::assertMatchesRegularExpression(
            '/<a href="javascript:history\.back\(\)">Keine passenden/',
            $response,
            'Empty-Suchresultat muss als echter Anchor-Tag gerendert werden.'
        );

        self::assertStringNotContainsString(
            '&lt;a href=&quot;javascript:history.back()&quot;&gt;',
            $response,
            'Anchor-Markup darf nicht doppelt escapet sein.'
        );
    }
}
