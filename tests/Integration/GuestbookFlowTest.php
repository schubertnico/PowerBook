<?php

/**
 * PowerBook - PHPUnit Tests
 * Ablauf im Gästebuch: Formular, Vorschau, Ändern, Eintragen, Zeitsperre,
 * abgelaufenes Formular, Suche und Grenzfälle (Befunde B01–B30).
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

final class GuestbookFlowTest extends GuestbookTestCase
{
    #[Test]
    public function previewShowsEntryWithoutSaving(): void
    {
        $html = $this->render([], $this->form('preview', [
            'name' => 'Anke & Jannik’s "Team"',
            'url' => 'www.example.org',
            'text' => 'Unser Fazit: "Jederzeit wieder" & danke! $10 :)',
            'icon' => 'happy1',
        ]));

        $this->assertStringContainsString('id="pbEntryPreview"', $html);
        $this->assertStringContainsString('Ihr Eintrag ist noch nicht gespeichert.', $html);
        $this->assertStringContainsString('<span class="pb-entry-name">Anke &amp; Jannik’s &quot;Team&quot;</span>', $html);
        $this->assertStringContainsString('Unser Fazit: &quot;Jederzeit wieder&quot; &amp; danke! $10 <img', $html);
        $this->assertStringContainsString('<input type="hidden" name="text" value="Unser Fazit: &quot;Jederzeit wieder&quot; &amp; danke! $10 :)">', $html);
        $this->assertStringContainsString('<input type="hidden" name="name" value="Anke &amp; Jannik’s &quot;Team&quot;">', $html);
        $this->assertStringContainsString('id="pbEntrySubmit" type="submit" name="action" value="save"', $html);
        $this->assertStringContainsString('id="pbPreviewBack" type="submit" name="action" value="edit"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('id="pbEntryForm"', $html);
        $this->assertSame(0, $this->countEntries());
    }

    #[Test]
    public function editReturnsToFormWithValues(): void
    {
        $html = $this->render([], $this->form('edit', ['name' => 'Anke & Co', 'text' => "Zeile 1\nZeile \"2\"", 'icon' => 'mark', 'smilies2' => 'N']));

        $this->assertStringContainsString('value="Anke &amp; Co"', $html);
        $this->assertStringContainsString(">Zeile 1\nZeile &quot;2&quot;</textarea>", $html);
        $this->assertMatchesRegularExpression('/id="pb_icon_mark"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="pb_smilies"[^>]*checked/', $html);
        $this->assertStringNotContainsString('id="pbFormError"', $html);
    }

    #[Test]
    public function saveStoresRawValuesAndShowsMessage(): void
    {
        $html = $this->render([], $this->form('save', [
            'name' => 'Ole',
            'email2' => 'ole@example.org',
            'url' => 'www.example.org/ole',
            'text' => "Preis: \$10 \\1 \"gut\" & günstig (#URL#)\r\nZweite Zeile",
            'icon' => 'happy5',
        ]), ['config_release' => 'U']);

        $row = self::$db->query('SELECT * FROM pb_entries')->fetch();
        $this->assertSame('Ole', $row['name']);
        $this->assertSame('ole@example.org', $row['email']);
        $this->assertSame('https://www.example.org/ole', $row['homepage']);
        $this->assertSame("Preis: \$10 \\1 \"gut\" & günstig (#URL#)\nZweite Zeile", $row['text']);
        $this->assertSame('happy5', $row['icon']);
        $this->assertSame('U', $row['status']);
        $this->assertSame('192.0.2.50', $row['ip']);
        $this->assertSame('', $row['statement']);

        // B01: feste Meldung (ohne Umleitung im Test direkt über der Liste)
        $this->assertStringContainsString('<div id="pbEntryMessage" class="alert alert-success" role="status">Vielen Dank! Ihr Eintrag erscheint, sobald er freigeschaltet ist.</div>', $html);
        $this->assertStringNotContainsString('Ole', strip_tags(explode('id="pbEntryCount"', $html)[1] ?? ''), 'Wartender Eintrag erscheint nicht in der Liste');
    }

    #[Test]
    public function saveWithAdminMailShowsGuestMessageNotMailText(): void
    {
        $html = $this->render([], $this->form('save', ['name' => '<img src=x onerror=alert(1)>']), [
            'config_send_email' => 'Y',
            'config_email' => '',
        ]);

        $this->assertStringContainsString('Vielen Dank! Ihr Eintrag ist jetzt im Gästebuch.', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('AdminCenter (', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    #[Test]
    public function saveValidatesLikePreview(): void
    {
        // B05: der Speicher-Schritt prüft alles selbst
        $cases = [
            ['name' => '', 'text' => ''],
            ['text' => str_repeat('x', 20000)],
            ['name' => str_repeat('N', 150)],
            ['url' => 'https://example.org/' . str_repeat('a', 210)],
            ['email2' => 'kein-mail'],
            ['icon' => 'x" onerror="alert(1)'],
            ['url' => 'javascript:alert(1)'],
        ];
        foreach ($cases as $fields) {
            $html = $this->render([], $this->form('save', $fields));
            $this->assertStringContainsString('id="pbFormError"', $html, json_encode($fields));
            $this->assertStringContainsString('invalid-feedback', $html, json_encode($fields));
        }
        $this->assertSame(0, $this->countEntries());
    }

    #[Test]
    public function legacyPreviewFieldsAreAccepted(): void
    {
        $html = $this->render([], [
            'csrf_token' => generateCsrfToken(),
            'add_entry' => 'yes',
            'name2' => 'Alt',
            'text2' => 'Altes Formular',
            'email2' => '',
            'url2' => '',
            'icon2' => 'no',
            'smilies2' => 'Y',
        ]);

        $this->assertStringContainsString('id="pbEntryMessage"', $html);
        $this->assertSame(1, $this->countEntries());
    }

    #[Test]
    public function spamLockKeepsTextAtPreviewAndSave(): void
    {
        // B13: Zeitsperre schon bei der Vorschau, Text bleibt
        $this->insertEntry(['ip' => '192.0.2.50', 'date' => time() - 5, 'status' => 'U']);

        foreach (['preview', 'save'] as $action) {
            $html = $this->render([], $this->form($action, ['text' => 'Mein langer Text']), ['config_spam_check' => 30]);
            $this->assertStringContainsString('id="pbFormError"', $html);
            $this->assertMatchesRegularExpression('/Bitte warten Sie noch <strong id="pbSpamSeconds">2[56]<\/strong> Sekunden/', $html);
            $this->assertStringContainsString('Ihr Text bleibt erhalten.', $html);
            $this->assertStringContainsString('>Mein langer Text</textarea>', $html);
        }
        $this->assertSame(1, $this->countEntries());

        // Andere IP oder Sperre aus: erlaubt
        $_SERVER['REMOTE_ADDR'] = '192.0.2.51';
        $this->assertStringContainsString('id="pbEntryPreview"', $this->render([], $this->form('preview')));
        $_SERVER['REMOTE_ADDR'] = '192.0.2.50';
        $this->assertStringContainsString('id="pbEntryPreview"', $this->render([], $this->form('preview'), ['config_spam_check' => 0]));
    }

    #[Test]
    public function expiredFormKeepsInput(): void
    {
        $html = $this->render([], array_merge($this->form('save', ['text' => 'Bleibt stehen']), ['csrf_token' => 'falsch']));

        $this->assertStringContainsString('Das Formular war abgelaufen. Bitte senden Sie es erneut ab.', $html);
        $this->assertStringContainsString('>Bleibt stehen</textarea>', $html);
        $this->assertSame(0, $this->countEntries());
    }

    #[Test]
    public function searchEscapesWildcardsAndShowsTerm(): void
    {
        $this->insertEntry(['name' => 'Lotte', 'text' => 'Eine Möwe hat mein Brötchen geklaut']);
        $this->insertEntry(['name' => 'Kai', 'text' => 'Rabatt 100% und mehr']);

        $html = $this->render(['tmp_search' => 'möwe', 'tmp_where' => 'text']);
        $this->assertStringContainsString('<span id="pbEntryCount"><b>1</b> Eintrag mit „möwe“ im Eintragstext</span>', $html);
        $this->assertStringContainsString('<form id="pbSearchForm"', $html);

        $this->assertStringContainsString('<b>1</b> Eintrag mit „100%“', $this->render(['tmp_search' => '100%', 'tmp_where' => 'text']));
        $empty = $this->render(['tmp_search' => '%', 'tmp_where' => 'name']);
        $this->assertStringContainsString('Keine Einträge mit „%“ im Namen', $empty);
        $this->assertStringContainsString('id="pbSearchEmpty"', $empty);
        $this->assertStringNotContainsString('javascript:', $empty);
    }

    #[Test]
    public function missingTableShowsInstallationHint(): void
    {
        $html = $this->render([], [], ['pb_entries' => 'pb_gibt_es_nicht']);

        $this->assertStringContainsString('Installation erforderlich', $html);
        $this->assertStringContainsString('href="install.php"', $html);
        $this->assertStringContainsString('Bitte führen Sie zuerst die Installation aus.', $html);
    }

    #[Test]
    public function listUsesConfiguredDesignSafely(): void
    {
        $id = $this->insertEntry(['name' => 'Henrik', 'text' => 'Hallo']);
        $design = '<table bgcolor="#fff"><tr><td onclick="x()">(#EMAIL_NAME#) (#TEXT#)<script>document.title=1</script></td></tr></table>';

        $html = $this->render([], [], ['config_design' => $design]);

        $this->assertStringContainsString('bgcolor="#fff"', $html);
        $this->assertStringContainsString('<div id="pbEntry' . $id . '" data-pb-entry-id="' . $id . '" class="pb-entry">', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('document.title', $html);
    }

    #[Test]
    public function pbookPhpUsesTitleAndHeading(): void
    {
        $_GET = [];
        $_POST = [];
        $pdo = self::$db;
        $pb_entries = 'pb_entries';
        $config_title = 'Gästebuch <Möwenblick>';
        $config_guestbook_name = 'pbook.php';
        $config_show_entries = 10;

        ob_start();
        include POWERBOOK_ROOT . '/pbook.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('<title>Gästebuch &lt;Möwenblick&gt;</title>', $html);
        $this->assertStringContainsString('<h1 id="pbTitle" class="navbar-brand pb-title mb-0"><a class="text-reset text-decoration-none" href="pbook.php">Gästebuch &lt;Möwenblick&gt;</a></h1>', $html);
        $this->assertStringContainsString('>AdminCenter</a>', $html);
        $this->assertStringContainsString('integrity="sha384-', $html);
        $this->assertStringNotContainsString('Adminbereich', $html);
    }
}
