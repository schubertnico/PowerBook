<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

/**
 * B18: Suche ohne Treffer zeigt den Suchbegriff und einen Hinweis ohne javascript:-Link.
 */
final class GuestbookSearchEmptyTest extends GuestbookTestCase
{
    public function testSearchWithoutResultsShowsHint(): void
    {
        $this->insertEntry(['name' => 'Lotte', 'text' => 'Möwe']);

        $html = $this->render(['tmp_where' => 'name', 'tmp_search' => 'UNLIKELY_<b>42']);

        $this->assertStringContainsString('<div id="pbSearchEmpty" class="alert alert-warning" role="alert">Keine Einträge mit „UNLIKELY_&lt;b&gt;42“ im Namen gefunden.', $html);
        $this->assertStringContainsString('<a id="pbSearchReset" href="pbook.php">Alle Einträge anzeigen</a>', $html);
        $this->assertStringContainsString('value="UNLIKELY_&lt;b&gt;42"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<article', $html);
    }
}
