<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EntryHomepageLinkTest extends TestCase
{
    public function testEntryShowsValidHomepageOnly(): void
    {
        $config = ['design' => '<p>(#URL#)</p>'];

        self::assertSame('<p><small><a class="pb-entry-homepage" href="https://www.example.org" target="_blank" rel="noopener noreferrer nofollow ugc">Homepage</a></small></p>', pb_render_entry(['homepage' => 'www.example.org'], $config));
        self::assertStringContainsString('href="https://secure.example.com/x"', pb_render_entry(['homepage' => 'https://secure.example.com/x'], $config));
        self::assertStringNotContainsString('http://https://', pb_render_entry(['homepage' => 'https://secure.example.com/x'], $config));
        foreach (['', 'kein link', 'javascript:alert(1)', '" onmouseover="x'] as $homepage) {
            self::assertSame('<p><small class="text-body-secondary">Keine Homepage</small></p>', pb_render_entry(['homepage' => $homepage], $config), $homepage);
        }
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

    public function testFormFieldForHomepageWithoutHttpPrefix(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/form.inc.php');
        self::assertNotFalse($source);
        self::assertStringContainsString('id="pb_url" name="url" type="url"', $source);
        self::assertStringNotContainsString('<span class="input-group-text">http://</span>', $source);
    }
}
