<?php

/**
 * PowerBook - PHPUnit Tests
 * Helper Functions Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversFunction('pb_format_date')]
#[CoversFunction('pb_render_text')]
#[CoversFunction('pb_render_help')]
#[CoversFunction('pb_normalize_url')]
#[CoversFunction('pb_sanitize_design')]
#[CoversFunction('pb_prepare_design')]
#[CoversFunction('pb_render_entry')]
#[CoversFunction('getVisitorIp')]
class FunctionsTest extends TestCase
{
    // ========================================
    // pb_format_date(): deutsche Namen (A26)
    // ========================================

    #[Test]
    public function formatDateTranslatesWeekdaysAndMonths(): void
    {
        $sunday = mktime(21, 48, 0, 10, 4, 2026);

        $this->assertSame('Sonntag, 4. Oktober 2026', pb_format_date('l, j. F Y', $sunday));
        $this->assertSame('So, 04. Okt 2026', pb_format_date('D, d. M Y', $sunday));
        $this->assertSame('04.10.2026', pb_format_date('d.m.Y', $sunday));
        $this->assertSame('21:48', pb_format_date('H:i', $sunday));
    }

    #[Test]
    public function formatDateCoversAllNames(): void
    {
        $days = [];
        $months = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = pb_format_date('l D', mktime(12, 0, 0, 1, 5 + $i, 2026));
        }
        for ($m = 1; $m <= 12; $m++) {
            $months[] = pb_format_date('F M', mktime(12, 0, 0, $m, 1, 2026));
        }

        $this->assertSame(['Montag Mo', 'Dienstag Di', 'Mittwoch Mi', 'Donnerstag Do', 'Freitag Fr', 'Samstag Sa', 'Sonntag So'], $days);
        $this->assertSame('März Mär', $months[2]);
        $this->assertSame('Mai Mai', $months[4]);
        $this->assertSame('Dezember Dez', $months[11]);
    }

    #[Test]
    public function formatDateKeepsEscapedCharactersAndCase(): void
    {
        $t = mktime(9, 5, 0, 9, 20, 2026);

        $this->assertSame('KW 38, 20.09.26', pb_format_date('\K\W W, d.m.y', $t));
        $this->assertSame('20. September 2026', pb_format_date('jS F Y', $t));
        $this->assertSame('Uhr 09:05', pb_format_date('\U\h\r H:i', $t));
        $this->assertSame('lDFM Sonntag', pb_format_date('\l\D\F\M l', $t));
    }

    // ========================================
    // pb_render_text(): BBCode, Links, Smileys (B04, B09, B10)
    // ========================================

    #[Test]
    public function renderTextEscapesHtmlAndConvertsNewlines(): void
    {
        $result = pb_render_text("<script>alert(1)</script> & \"x\"\r\nZeile 2", false, false);

        $this->assertSame("&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;x&quot;<br>\nZeile 2", $result);
    }

    #[Test]
    public function renderTextBbcodeOnlyInPairs(): void
    {
        $this->assertSame('<b>fett</b> <i>k</i> <u>u</u> <small>s</small>', pb_render_text('[b]fett[/b] [i]k[/i] [u]u[/u] [small]s[/small]', true, false));
        $this->assertSame('<b>a <i>b</i> c</b>', pb_render_text('[B]a [i]b[/I] c[/b]', true, false));
        $this->assertSame('[b]offen [u]und weiter', pb_render_text('[b]offen [u]und weiter', true, false));
        $this->assertSame('[b]x[i]y[/b]z[/i]', pb_render_text('[b]x[i]y[/b]z[/i]', true, false));
        $this->assertSame('[b]fett[/b]', pb_render_text('[b]fett[/b]', false, false));
    }

    #[Test]
    public function renderTextKeepsDollarBackslashAndPlaceholders(): void
    {
        $text = 'Fähre: $10 hin, \1 zurück, $0 Ende (#URL#) (#TEXT#)';

        $this->assertSame('Fähre: $10 hin, \1 zurück, $0 Ende (#URL#) (#TEXT#)', pb_render_text($text, true, true));
    }

    #[Test]
    public function renderTextLinksWithoutTrailingPunctuation(): void
    {
        $result = pb_render_text('Siehe https://www.example.org/foehr. Oder (https://de.wikipedia.org/wiki/Föhr_(Insel)), ftp://files.example.com/a.zip', true, false);

        $this->assertStringContainsString('<a href="https://www.example.org/foehr" target="_blank" rel="noopener noreferrer nofollow ugc">https://www.example.org/foehr</a>.', $result);
        $this->assertStringContainsString('href="https://de.wikipedia.org/wiki/Föhr_(Insel)"', $result);
        $this->assertStringContainsString('</a>),', $result);
        $this->assertStringContainsString('href="ftp://files.example.com/a.zip"', $result);
    }

    #[Test]
    public function renderTextLinksCannotBreakOutOfAttribute(): void
    {
        $result = pb_render_text('https://example.org/"onmouseover="alert(1)" und https://example.org/\'x', true, true);

        $this->assertStringNotContainsString('onmouseover="alert', $result);
        $this->assertStringContainsString('href="https://example.org/"', $result);
    }

    #[Test]
    public function renderTextSmileysNeedSpaceBefore(): void
    {
        $result = pb_render_text(':) (Haus "Möwe") (Zimmer \'Seeblick\') super:) Gut :-) ;o) :( ;(', false, true);

        $this->assertSame(5, substr_count($result, 'class="pb-smiley"'));
        $this->assertStringContainsString('super:)', $result);
        $this->assertStringContainsString('(Haus &quot;Möwe&quot;)', $result);
        $this->assertStringContainsString('src="pb_inc/smilies/sad1.gif" alt=":("', $result);
        $this->assertStringContainsString('src="pb_inc/smilies/sad2.gif" alt=";("', $result);
        $this->assertStringContainsString('alt=":-)" title="Lächeln"', $result);
    }

    #[Test]
    public function renderTextSmileyNeverInsideLink(): void
    {
        $result = pb_render_text('Bild https://www.example.org/bild:Dx.jpg :D', true, true);

        $this->assertStringContainsString('href="https://www.example.org/bild:Dx.jpg"', $result);
        $this->assertSame(1, substr_count($result, 'pb-smiley'));
    }

    #[Test]
    public function renderTextUsesSmileyBase(): void
    {
        $this->assertStringContainsString('src="../smilies/happy1.gif"', pb_render_text(':)', false, true, '../smilies/'));
    }

    #[Test]
    public function renderHelpListsCodesFromSameSource(): void
    {
        $help = pb_render_help(true, true);

        $this->assertStringContainsString('<code>[b]fett[/b]</code>', $help);
        $this->assertSame(10, substr_count($help, 'class="pb-smiley"'));
        $this->assertStringContainsString('<code>:(</code></td><td><img src="pb_inc/smilies/sad1.gif"', $help);
        $this->assertStringNotContainsString('Smileys</h3>', pb_render_help(true, false));
    }

    // ========================================
    // pb_normalize_url(), Design, Eintrag
    // ========================================

    #[Test]
    public function normalizeUrlAddsHttpsAndRejectsJunk(): void
    {
        $this->assertSame('https://www.example.org', pb_normalize_url('www.example.org'));
        $this->assertSame('http://example.org/x', pb_normalize_url('http://example.org/x'));
        $this->assertSame('https://www.görlitz.de/über', pb_normalize_url('https://www.görlitz.de/über'));
        foreach (['', 'kein link', 'javascript:alert(1)', 'data:text/html,x', 'https://example.org/"x', 'mailto:a@b.de', 'https://localhost'] as $url) {
            $this->assertSame('', pb_normalize_url($url), $url);
        }
    }

    #[Test]
    public function sanitizeDesignRemovesScripts(): void
    {
        $design = '<table bgcolor="#fff" onclick="x()"><tr><td>(#TEXT#)<script>alert(1)</script>'
            . '<a href="jav&#x61;script:alert(2)">a</a><a href="https://ok.example/" title="a>b">ok</a>'
            . '<img src=x onerror=alert(3)><iframe src="https://x"></iframe><svg onload=alert(4)></svg></td></tr></table>';
        $result = pb_sanitize_design($design);

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('javascript', $result);
        $this->assertStringNotContainsString('<iframe', $result);
        $this->assertStringNotContainsString('<svg', $result);
        $this->assertStringContainsString('bgcolor="#fff"', $result);
        $this->assertStringContainsString('href="https://ok.example/"', $result);
        $this->assertStringContainsString('(#TEXT#)', $result);
    }

    #[Test]
    public function prepareDesignUsesDefaultOnlyWithoutPlaceholder(): void
    {
        $this->assertSame(PB_DEFAULT_DESIGN, pb_prepare_design(''));
        $this->assertSame(PB_DEFAULT_DESIGN, pb_prepare_design('<table bgcolor="#000"><tr><td>alt</td></tr></table>'));
        $this->assertStringContainsString('bgcolor', pb_prepare_design('<table bgcolor="#000"><tbody><tr><td>(#TEXT#)</td></tr></tbody></table>'));
        $this->assertStringContainsString('(#TIME#) Uhr', PB_DEFAULT_DESIGN);
    }

    #[Test]
    public function renderEntryUsesPlaceholdersOnce(): void
    {
        $html = pb_render_entry([
            'id' => 7,
            'name' => 'Kai (#TEXT#) $1',
            'email' => 'kai@example.org',
            'text' => 'Preis $10 (#URL#)',
            'homepage' => 'www.example.org',
            'icon' => 'happy1',
            'smilies' => 'Y',
            'date' => mktime(20, 42, 0, 9, 20, 2026),
            'statement' => 'Danke :)',
            'statement_by' => 'Anke',
        ], ['design' => '', 'date' => 'l, j. F Y', 'time' => 'H:i', 'icons' => 'Y', 'smilies' => 'Y', 'text_format' => 'Y', 'statements' => 'Y']);

        $this->assertStringStartsWith('<article id="pbEntry7" data-pb-entry-id="7" class="card pb-entry-card', $html);
        $this->assertStringContainsString('<span class="pb-entry-name">Kai (#TEXT#) $1</span>', $html);
        $this->assertStringContainsString('Preis $10 (#URL#)', $html);
        $this->assertStringContainsString('Sonntag, 20. September 2026', $html);
        $this->assertStringContainsString('20:42 Uhr', $html);
        $this->assertStringContainsString('href="https://www.example.org"', $html);
        $this->assertStringContainsString('<div id="pbEntryAnswer7" class="pb-entry-statement', $html);
        $this->assertStringContainsString('<b>Antwort von Anke:</b>', $html);
        $this->assertStringContainsString('alt="Lächeln"', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('kai@example.org', $html);
    }

    #[Test]
    public function renderEntryStatementSmileysFollowConfigOnly(): void
    {
        $entry = ['id' => 3, 'name' => 'Henrik', 'text' => 'Hallo :)', 'smilies' => 'N', 'date' => 0, 'statement' => 'Danke :)', 'statement_by' => ''];
        $html = pb_render_entry($entry, ['design' => '', 'smilies' => 'Y', 'statements' => 'Y']);

        $this->assertSame(1, substr_count($html, 'pb-smiley'));
        $this->assertStringContainsString('<b>Antwort:</b>', $html);
        $this->assertStringNotContainsString('pb-smiley', pb_render_entry($entry, ['design' => '', 'smilies' => 'N', 'statements' => 'Y']));
        $this->assertStringNotContainsString('Antwort', pb_render_entry($entry, ['design' => '', 'smilies' => 'Y', 'statements' => 'N']));
    }

    #[Test]
    public function renderEntryWrapsCustomDesignWithId(): void
    {
        $html = pb_render_entry(['id' => 5, 'name' => 'A', 'text' => 'B', 'date' => 0], ['design' => '<p>(#EMAIL_NAME#): (#TEXT#) (#ICQ#)</p>']);

        $this->assertSame('<div id="pbEntry5" data-pb-entry-id="5" class="pb-entry"><p><span class="pb-entry-name">A</span>: B </p></div>', $html);
    }

    #[Test]
    public function renderEntryIgnoresUnknownIcon(): void
    {
        $html = pb_render_entry(['icon' => 'x" onerror="a', 'name' => 'A', 'text' => 'B'], ['design' => '', 'icons' => 'Y']);

        $this->assertStringNotContainsString('pb-entry-icon', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    #[Test]
    public function helpersForUrlsAndSearch(): void
    {
        $this->assertSame('pbook.php?tmp_start=10&tmp_search=M%C3%B6we', pb_url_with('pbook.php', ['tmp_start' => 10, 'tmp_where' => '', 'tmp_search' => 'Möwe']));
        $this->assertSame('pbook.php', pb_url_with('pbook.php', ['tmp_start' => 0]));
        $this->assertSame('index.php?page=gb&page=release', pb_url_with('index.php?page=gb', ['page' => 'release']));
        $this->assertSame('100!% sicher!_ !!', pb_like_escape('100% sicher_ !'));
        $this->assertSame('https://gb.example/admin/', pb_admin_url('https://gb.example/admin/'));
    }

    // ========================================
    // Tests for getVisitorIp() function
    // ========================================

    #[Test]
    public function getVisitorIpReturnsServerRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $this->assertSame('192.168.1.100', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpReturnsDefaultForMissingAddr(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $this->assertSame('0.0.0.0', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpReturnsDefaultForInvalidIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'invalid-ip-address';
        $this->assertSame('0.0.0.0', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpHandlesIpv6(): void
    {
        $_SERVER['REMOTE_ADDR'] = '::1';
        $this->assertSame('::1', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpHandlesFullIpv6(): void
    {
        $_SERVER['REMOTE_ADDR'] = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        $this->assertSame('2001:0db8:85a3:0000:0000:8a2e:0370:7334', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpHandlesLocalhost(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertSame('127.0.0.1', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpRejectsEmptyString(): void
    {
        $_SERVER['REMOTE_ADDR'] = '';
        $this->assertSame('0.0.0.0', getVisitorIp());
    }

    protected function setUp(): void
    {
        // functions.inc.php wird über Composer geladen.
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
    }
}
