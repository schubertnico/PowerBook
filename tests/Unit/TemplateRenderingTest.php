<?php

/**
 * PowerBook - PHPUnit Tests
 * Template Rendering Tests
 *
 * Tests template files in pb_inc/ by including them with proper globals
 * and using output buffering to capture and assert output.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TemplateRenderingTest extends TestCase
{
    private static bool $dependenciesLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$dependenciesLoaded) {
            require_once POWERBOOK_ROOT . '/pb_inc/database.inc.php';
            require_once POWERBOOK_ROOT . '/pb_inc/csrf.inc.php';
            self::$dependenciesLoaded = true;
        }
    }

    // ========================================
    // Frontend Entry Display (entry.inc.php)
    // ========================================

    #[Test]
    public function testEntryDisplayBasic(): void
    {
        $vars = $this->makeEntryVars();
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('Test User', $output);
        $this->assertStringContainsString('Hello World', $output);
        $this->assertStringContainsString('15.06.2025', $output);
        $this->assertStringContainsString('14:30', $output);
    }

    #[Test]
    public function testEntryDisplayNeverPublishesEmail(): void
    {
        // B07: Die E-Mail-Adresse des Gastes erscheint nie öffentlich.
        $vars = $this->makeEntryVars(['email' => 'test@example.com']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringNotContainsString('mailto:', $output);
        $this->assertStringNotContainsString('test@example.com', $output);
        $this->assertStringContainsString('<span class="pb-entry-name">Test User</span>', $output);
    }

    #[Test]
    public function testEntryDisplayWithHomepage(): void
    {
        // Ohne Schema gespeicherte Adressen (Altbestand) bekommen https:// davor.
        $vars = $this->makeEntryVars(['homepage' => 'example.com']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('href="https://example.com"', $output);
        $this->assertStringContainsString('Homepage</a>', $output);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow ugc"', $output);
    }

    #[Test]
    public function testEntryDisplayWithInvalidHomepage(): void
    {
        $vars = $this->makeEntryVars(['homepage' => 'javascript:alert(1)']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringNotContainsString('javascript', $output);
        $this->assertStringContainsString('Keine Homepage', $output);
    }

    #[Test]
    public function testEntryDisplayWithHomepageHttps(): void
    {
        $vars = $this->makeEntryVars(['homepage' => 'https://secure.example.com']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('https://secure.example.com', $output);
        // Should NOT have double http://
        $this->assertStringNotContainsString('http://https://', $output);
    }

    #[Test]
    public function testEntryDisplayWithIcon(): void
    {
        $vars = $this->makeEntryVars(
            ['icon' => 'happy1'],
            ['config_icons' => 'Y']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('<img src="pb_inc/smilies/happy1.gif"', $output);
    }

    #[Test]
    public function testEntryDisplayWithoutIcon(): void
    {
        $vars = $this->makeEntryVars(
            ['icon' => ''],
            ['config_icons' => 'Y']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringNotContainsString('<img src="pb_inc/smilies/', $output);
    }

    #[Test]
    public function testEntryDisplayWithBBCode(): void
    {
        $vars = $this->makeEntryVars(
            ['text' => '[b]Bold Text[/b] and [i]Italic[/i]'],
            ['config_text_format' => 'Y']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('<b>Bold Text</b>', $output);
        $this->assertStringContainsString('<i>Italic</i>', $output);
    }

    #[Test]
    public function testEntryDisplayWithSmilies(): void
    {
        $vars = $this->makeEntryVars(
            ['text' => 'Hello :)', 'smilies' => 'Y'],
            ['config_smilies' => 'Y']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('happy1.gif', $output);
    }

    #[Test]
    public function testEntryDisplayWithStatement(): void
    {
        $vars = $this->makeEntryVars(
            [
                'id' => 4,
                'statement' => 'Admin reply here',
                'statement_by' => 'AdminUser',
            ],
            ['config_statements' => 'Y']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('Admin reply here', $output);
        $this->assertStringContainsString('<b>Antwort von AdminUser:</b>', $output);
        $this->assertStringContainsString('id="pbEntryAnswer4"', $output);
        $this->assertStringNotContainsString('Statement', $output);
    }

    #[Test]
    public function testEntryDisplayNeverShowsIcq(): void
    {
        // ICQ-Feature wurde komplett entfernt — auch wenn ein alter DB-Eintrag
        // noch eine ICQ-Nummer enthält, darf sie NICHT mehr angezeigt werden.
        $vars = $this->makeEntryVars(
            ['icq' => '123456789'],
            ['config_icq' => 'Y']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringNotContainsString('ICQ', $output);
        $this->assertStringNotContainsString('123456789', $output);
    }

    #[Test]
    public function testEntryDisplayDesignTemplate(): void
    {
        $vars = $this->makeEntryVars(
            [],
            ['config_design' => '<div class="entry">(#EMAIL_NAME#): (#TEXT#)</div>']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('<div class="entry">', $output);
        $this->assertStringContainsString('Test User', $output);
        $this->assertStringContainsString('Hello World', $output);
        // Placeholders should be replaced
        $this->assertStringNotContainsString('(#EMAIL_NAME#)', $output);
        $this->assertStringNotContainsString('(#TEXT#)', $output);
    }

    #[Test]
    public function testFormRendersCsrfField(): void
    {
        $vars = $this->makeFormVars();
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('name="csrf_token"', $output);
        $this->assertStringContainsString('type="hidden"', $output);
    }

    #[Test]
    public function testFormRendersNameField(): void
    {
        $vars = $this->makeFormVars();
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('name="name"', $output);
        // Bootstrap-5-Migration: Label hat keinen Doppelpunkt mehr.
        $this->assertStringContainsString('>Name', $output);
    }

    #[Test]
    public function testFormRendersEmailField(): void
    {
        $vars = $this->makeFormVars();
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('name="email2"', $output);
        // Label wurde auf "E-Mail-Adresse" vereinheitlicht.
        $this->assertStringContainsString('>E-Mail-Adresse', $output);
    }

    #[Test]
    public function testFormRendersTextarea(): void
    {
        $vars = $this->makeFormVars();
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        // Bootstrap-Migration: textarea hat zusaetzliche Attribute (id, class, rows ...).
        $this->assertStringContainsString('name="text"', $output);
        $this->assertStringContainsString('<textarea', $output);
        $this->assertStringContainsString('</textarea>', $output);
    }

    #[Test]
    public function testFormDoesNotRenderIcqField(): void
    {
        // ICQ-Feature wurde komplett entfernt — das Eingabefeld darf NICHT
        // mehr im Formular auftauchen, auch wenn $config_icq aus alten DB-
        // Konfigurationen noch auf 'Y' steht.
        $vars = $this->makeFormVars(['config_icq' => 'Y']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringNotContainsString('name="icq2"', $output);
        $this->assertStringNotContainsString('>ICQ', $output);
    }

    #[Test]
    public function testFormRendersIconsWhenEnabled(): void
    {
        $vars = $this->makeFormVars(['config_icons' => 'Y']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('name="icon"', $output);
        $this->assertStringContainsString('Kein Icon', $output);
        $this->assertStringContainsString('pb_inc/smilies/text.gif', $output);
    }

    #[Test]
    public function testFormRendersSmiliesCheckbox(): void
    {
        $vars = $this->makeFormVars(['config_smilies' => 'Y']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('name="smilies2"', $output);
        $this->assertStringContainsString('Smileys als Bilder anzeigen', $output);
    }

    #[Test]
    public function testFormHasTwoButtonsHelpAndPrivacyNote(): void
    {
        $vars = $this->makeFormVars();
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('id="pbEntryForm"', $output);
        $this->assertMatchesRegularExpression('/id="pbEntryPreviewBtn"[^>]*value="preview"[^>]*>Vorschau</', $output);
        $this->assertMatchesRegularExpression('/id="pbEntrySubmit"[^>]*value="save"[^>]*>Eintragen</', $output);
        $this->assertStringContainsString('id="pbHelpToggle"', $output);
        $this->assertStringContainsString('data-bs-toggle="collapse"', $output);
        $this->assertStringNotContainsString('javascript:', $output);
        $this->assertStringNotContainsString('window.open', $output);
        $this->assertStringContainsString('Ihre E-Mail-Adresse wird nicht veröffentlicht. Zum Schutz vor Missbrauch speichern wir die IP-Adresse Ihres Eintrags.', $output);
    }

    #[Test]
    public function testFormShowsErrorsAtFieldsAndEscapesOnce(): void
    {
        $vars = $this->makeFormVars([
            'pbFormValues' => ['name' => 'Anke & Co "Team"', 'email' => '', 'url' => '', 'text' => 'Er sagte "Moin" & ging.', 'icon' => 'happy1', 'smilies' => 'N'],
            'pbFormErrors' => ['email' => 'Bitte prüfen Sie die E-Mail-Adresse.', 'name' => 'Name zu lang.'],
        ]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('value="Anke &amp; Co &quot;Team&quot;"', $output);
        $this->assertStringContainsString('>Er sagte &quot;Moin&quot; &amp; ging.</textarea>', $output);
        $this->assertStringNotContainsString('&amp;amp;', $output);
        $this->assertStringContainsString('id="pbFormError"', $output);
        $this->assertStringContainsString('<div id="pb_email_error" class="invalid-feedback">Bitte prüfen Sie die E-Mail-Adresse.</div>', $output);
        $this->assertSame(2, substr_count($output, 'is-invalid'));
        $this->assertSame(2, substr_count($output, 'aria-invalid="true"'));
        $this->assertSame(1, substr_count($output, ' autofocus'));
        $this->assertMatchesRegularExpression('/id="pb_name"[^>]*autofocus/', $output);
        $this->assertMatchesRegularExpression('/id="pb_icon_happy1"[^>]*checked/', $output);
        $this->assertDoesNotMatchRegularExpression('/id="pb_smilies"[^>]*checked/', $output);
    }

    #[Test]
    public function testLinearPaginationFirstPage(): void
    {
        $vars = $this->makePagesVars(['config_pages' => 'L', 'tmp_start' => 0, 'count_pages' => 30]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        $this->assertStringContainsString('id="pbPagerTop"', $output);
        $this->assertMatchesRegularExpression('/<span class="page-link pb-pager-first"[^>]*aria-disabled="true">/', $output);
        $this->assertMatchesRegularExpression('/<span class="page-link pb-pager-prev"[^>]*aria-disabled="true">/', $output);
        $this->assertStringContainsString('Seite 1 von 3', $output);
        $this->assertMatchesRegularExpression('/<a class="page-link pb-pager-next"[^>]*href="pbook.php\?tmp_start=10">/', $output);
        $this->assertMatchesRegularExpression('/<a class="page-link pb-pager-last"[^>]*href="pbook.php\?tmp_start=20">/', $output);
        // IDs der Knöpfe nur in der unteren Leiste
        $this->assertStringNotContainsString('id="pbPagerNext"', $output);
    }

    #[Test]
    public function testLinearPaginationMiddlePageBottom(): void
    {
        $vars = $this->makePagesVars(['config_pages' => 'L', 'tmp_start' => 10, 'count_pages' => 30, 'pbPagerPosition' => 'bottom']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        $this->assertStringContainsString('id="pbPagerBottom"', $output);
        $this->assertMatchesRegularExpression('/<a class="page-link pb-pager-first" id="pbPagerFirst"[^>]*href="pbook.php">/', $output);
        $this->assertMatchesRegularExpression('/<a class="page-link pb-pager-prev" id="pbPagerPrev"[^>]*href="pbook.php">/', $output);
        $this->assertMatchesRegularExpression('/<a class="page-link pb-pager-next" id="pbPagerNext"[^>]*href="pbook.php\?tmp_start=20">/', $output);
        $this->assertStringContainsString('id="pbPagerLast"', $output);
        $this->assertStringContainsString('Seite 2 von 3', $output);
    }

    #[Test]
    public function testLinearPaginationLastPage(): void
    {
        $vars = $this->makePagesVars(['config_pages' => 'L', 'tmp_start' => 20, 'count_pages' => 30]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        $this->assertMatchesRegularExpression('/<span class="page-link pb-pager-next"[^>]*aria-disabled="true">/', $output);
        $this->assertMatchesRegularExpression('/<span class="page-link pb-pager-last"[^>]*aria-disabled="true">/', $output);
        $this->assertStringContainsString('Seite 3 von 3', $output);
    }

    #[Test]
    public function testDirectPaginationMarksPageFromStart(): void
    {
        // B24: aktive Seite aus tmp_start, nicht aus tmp_page
        $vars = $this->makePagesVars(['config_pages' => 'D', 'tmp_start' => 10, 'tmp_page' => 1, 'count_pages' => 30]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        $this->assertStringContainsString('class="pagination', $output);
        $this->assertMatchesRegularExpression('/<li class="page-item active" aria-current="page"><span[^>]*data-page="2">2<\/span>/', $output);
        $this->assertMatchesRegularExpression('/data-page="1" href="pbook.php">1<\/a>/', $output);
        $this->assertMatchesRegularExpression('/data-page="3" href="pbook.php\?tmp_start=20">3<\/a>/', $output);
        $this->assertStringNotContainsString('tmp_page', $output);
    }

    #[Test]
    public function testDirectPaginationShortensManyPages(): void
    {
        $vars = $this->makePagesVars(['config_pages' => 'D', 'tmp_start' => 100, 'count_pages' => 400, 'pbPagerPosition' => 'bottom']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        preg_match_all('/data-page="(\d+)"[^>]*>(\d+)</', $output, $m);
        $this->assertSame(['1', '9', '10', '11', '12', '13', '40'], $m[2]);
        $this->assertSame(2, substr_count($output, '…'));
        $this->assertStringContainsString('id="pbPagerFirst"', $output);
        $this->assertStringContainsString('id="pbPagerLast"', $output);
    }

    #[Test]
    public function testUnknownPagerModeFallsBackToNumbers(): void
    {
        $vars = $this->makePagesVars(['config_pages' => 'Y', 'tmp_start' => 0, 'count_pages' => 30]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        $this->assertStringContainsString('pb-pager-page', $output);
    }

    #[Test]
    public function testNoPaginationSinglePage(): void
    {
        $vars = $this->makePagesVars(['config_pages' => 'L', 'count_pages' => 5]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        $this->assertEmpty(trim($output));
    }

    #[Test]
    public function testPaginationWithSearch(): void
    {
        $vars = $this->makePagesVars([
            'config_pages' => 'L',
            'tmp_where' => 'name',
            'tmp_search' => 'Möwe & Co',
            'tmp_start' => 0,
            'count_pages' => 30,
        ]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        $this->assertStringContainsString('href="pbook.php?tmp_start=10&amp;tmp_where=name&amp;tmp_search=M%C3%B6we%20%26%20Co"', $output);
    }

    #[Test]
    public function testAdminEntryBasic(): void
    {
        $vars = $this->makeAdminEntryVars();
        // Admin entry.inc.php does not echo; it sets variables in scope.
        // We need to capture the variables set after inclusion.
        extract($vars);
        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';
        ob_get_clean();

        /** @var array<string, mixed> $entry */
        $this->assertStringContainsString('Admin test text', $entry['text']);
        $this->assertSame('Admin Test User', $email_name);
        $this->assertSame('192.168.1.1', $ip);
    }

    #[Test]
    public function testAdminEntryWithBBCode(): void
    {
        $vars = $this->makeAdminEntryVars(
            ['text' => '[b]Bold Admin[/b] and [u]underline[/u]'],
            ['config_text_format' => 'Y']
        );
        extract($vars);
        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';
        ob_get_clean();

        /** @var array<string, mixed> $entry */
        $this->assertStringContainsString('<b>Bold Admin</b>', $entry['text']);
        $this->assertStringContainsString('<u>underline</u>', $entry['text']);
    }

    #[Test]
    public function testAdminEntryWithSmilies(): void
    {
        $vars = $this->makeAdminEntryVars(
            ['text' => 'Hello :)', 'smilies' => 'Y'],
            ['config_smilies' => 'Y']
        );
        extract($vars);
        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';
        ob_get_clean();

        /** @var array<string, mixed> $entry */
        $this->assertStringContainsString('happy1.gif', $entry['text']);
    }

    #[Test]
    public function testAdminEntryWithHomepage(): void
    {
        $vars = $this->makeAdminEntryVars(
            ['homepage' => 'example.org']
        );
        extract($vars);
        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';
        ob_get_clean();

        // AdminCenter (Agent C): Link mit Adresse als Text
        $this->assertStringContainsString('href="https://example.org"', $url);
        $this->assertStringContainsString('example.org</a>', $url);
    }

    #[Test]
    public function testAdminEntryWithStatement(): void
    {
        $vars = $this->makeAdminEntryVars(
            [
                'statement' => 'Admin response text',
                'statement_by' => 'SuperAdmin',
            ],
            ['db_statement' => 'Y']
        );
        extract($vars);
        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';
        ob_get_clean();

        /** @var array<string, mixed> $entry */
        $this->assertStringContainsString('Admin response text', $entry['text']);
        $this->assertStringContainsString('SuperAdmin', $entry['text']);
        $this->assertStringContainsString('Antwort von SuperAdmin:', $entry['text']);
    }

    /**
     * Include a template file in an isolated scope with extracted variables.
     * Uses output buffering to capture all output.
     *
     * @param string               $file Path to the template file
     * @param array<string, mixed> $vars Variables to make available in the template scope
     *
     * @return string The captured output
     */
    private function renderTemplate(string $file, array $vars): string
    {
        extract($vars);
        ob_start();
        include $file;

        return ob_get_clean() ?: '';
    }

    /**
     * Build a default entry array for frontend entry tests.
     *
     * @param array<string, mixed> $overrides Values to override
     *
     * @return array<string, mixed>
     */
    private function makeEntry(array $overrides = []): array
    {
        return array_merge([
            'text' => 'Hello World',
            'name' => 'Test User',
            'email' => '',
            'homepage' => '',
            'icq' => '',
            'date' => mktime(14, 30, 0, 6, 15, 2025),
            'icon' => '',
            'smilies' => 'N',
            'statement' => '',
            'statement_by' => '',
        ], $overrides);
    }

    /**
     * Build default config variables for frontend entry tests.
     *
     * @param array<string, mixed> $overrides Values to override
     *
     * @return array<string, mixed>
     */
    private function makeEntryVars(array $entryOverrides = [], array $configOverrides = []): array
    {
        return array_merge([
            'entry' => $this->makeEntry($entryOverrides),
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'config_statements' => 'N',
            'config_date' => 'd.m.Y',
            'config_time' => 'H:i',
            'config_design' => '(#ICON#) (#DATE#) (#TIME#) (#EMAIL_NAME#) (#TEXT#) (#URL#) (#ICQ#)',
        ], $configOverrides);
    }

    // ========================================
    // Form Template (form.inc.php)
    // ========================================

    /**
     * Build default variables for form template tests.
     *
     * @param array<string, mixed> $overrides Values to override
     *
     * @return array<string, mixed>
     */
    private function makeFormVars(array $overrides = []): array
    {
        return array_merge([
            'name' => '',
            'email2' => '',
            'url' => '',
            'icq2' => '',
            'text' => '',
            'icon' => '',
            'smilies2' => '',
            'show_gb' => '',
            'config_guestbook_name' => 'pbook.php',
            'config_icons' => 'Y',
            'config_smilies' => 'Y',
            'config_icq' => 'Y',
            'config_text_format' => 'Y',
        ], $overrides);
    }

    // ========================================
    // Pagination Template (pages.inc.php)
    // ========================================

    /**
     * Build default variables for pagination template tests.
     *
     * @param array<string, mixed> $overrides Values to override
     *
     * @return array<string, mixed>
     */
    private function makePagesVars(array $overrides = []): array
    {
        return array_merge([
            'tmp_where' => '',
            'tmp_search' => '',
            'tmp_pages' => 3,
            'tmp_start' => 0,
            'tmp_page' => 1,
            'count_pages' => 30,
            'config_pages' => 'L',
            'config_show_entries' => 10,
            'config_guestbook_name' => 'pbook.php',
        ], $overrides);
    }

    // ========================================
    // Admin Entry Display (admincenter/entry.inc.php)
    // ========================================

    /**
     * Build default variables for admin entry template tests.
     *
     * @param array<string, mixed> $entryOverrides  Entry overrides
     * @param array<string, mixed> $configOverrides Config overrides
     *
     * @return array<string, mixed>
     */
    private function makeAdminEntryVars(array $entryOverrides = [], array $configOverrides = []): array
    {
        $entry = array_merge([
            'text' => 'Admin test text',
            'name' => 'Admin Test User',
            'email' => '',
            'homepage' => '',
            'icq' => '',
            'date' => mktime(10, 0, 0, 3, 20, 2025),
            'icon' => '',
            'smilies' => 'N',
            'ip' => '192.168.1.1',
            'statement' => '',
            'statement_by' => '',
        ], $entryOverrides);

        return array_merge([
            'entry' => $entry,
            'config_icons' => 'N',
            'config_text_format' => 'N',
            'config_smilies' => 'N',
            'config_icq' => 'N',
            'db_statement' => 'N',
        ], $configOverrides);
    }
}
