<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

/**
 * B03: Rohwerte bleiben bis zur Ausgabe unverändert und werden genau einmal maskiert –
 * in der Vorschau, in den versteckten Feldern und nach Fehlerrunden.
 */
final class GuestbookPreviewEscapeTest extends GuestbookTestCase
{
    public function testPreviewEscapesExactlyOnce(): void
    {
        $html = $this->render([], $this->form('preview', ['name' => 'Anke & Co', 'url' => 'https://example.org/?a=1&b=2']));

        $this->assertStringContainsString('<span class="pb-entry-name">Anke &amp; Co</span>', $html);
        $this->assertStringContainsString('<input type="hidden" name="name" value="Anke &amp; Co">', $html);
        $this->assertStringContainsString('<input type="hidden" name="url" value="https://example.org/?a=1&amp;b=2">', $html);
        $this->assertStringContainsString('href="https://example.org/?a=1&amp;b=2"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function testErrorRoundsDoNotAccumulateEntities(): void
    {
        $fields = ['name' => 'Anke & Co "Team"', 'text' => ''];
        for ($round = 0; $round < 3; $round++) {
            $html = $this->render([], $this->form('preview', $fields));
            $this->assertStringContainsString('value="Anke &amp; Co &quot;Team&quot;"', $html);
            $this->assertStringNotContainsString('&amp;amp;', $html);
        }
    }
}
