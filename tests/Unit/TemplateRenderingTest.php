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
    public function testEntryDisplayWithEmail(): void
    {
        $vars = $this->makeEntryVars(['email' => 'test@example.com']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('mailto:test@example.com', $output);
        $this->assertStringContainsString('>Test User</a>', $output);
    }

    #[Test]
    public function testEntryDisplayWithHomepage(): void
    {
        $vars = $this->makeEntryVars(['homepage' => 'example.com']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('http://example.com', $output);
        $this->assertStringContainsString('Homepage</a>', $output);
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
                'statement' => 'Admin reply here',
                'statement_by' => 'AdminUser',
            ],
            ['config_statements' => 'Y']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('Admin reply here', $output);
        $this->assertStringContainsString('AdminUser', $output);
        $this->assertStringContainsString('Statement:', $output);
    }

    #[Test]
    public function testEntryDisplayWithIcq(): void
    {
        $vars = $this->makeEntryVars(
            ['icq' => '123456789'],
            ['config_icq' => 'Y']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringContainsString('ICQ: 123456789', $output);
    }

    #[Test]
    public function testEntryDisplayNoIcq(): void
    {
        $vars = $this->makeEntryVars(
            ['icq' => ''],
            ['config_icq' => 'N']
        );
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/entry.inc.php', $vars);

        $this->assertStringNotContainsString('ICQ:', $output);
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
        $this->assertStringContainsString('Name:', $output);
    }

    #[Test]
    public function testFormRendersEmailField(): void
    {
        $vars = $this->makeFormVars();
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('name="email2"', $output);
        $this->assertStringContainsString('eMail:', $output);
    }

    #[Test]
    public function testFormRendersTextarea(): void
    {
        $vars = $this->makeFormVars();
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('<textarea name="text"', $output);
        $this->assertStringContainsString('</textarea>', $output);
    }

    #[Test]
    public function testFormRendersIcqFieldWhenEnabled(): void
    {
        $vars = $this->makeFormVars(['config_icq' => 'Y']);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/form.inc.php', $vars);

        $this->assertStringContainsString('name="icq2"', $output);
        $this->assertStringContainsString('ICQ#:', $output);
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
        $this->assertStringContainsString('Smilies aktivieren', $output);
    }

    #[Test]
    public function testLinearPaginationFirstPage(): void
    {
        $vars = $this->makePagesVars([
            'config_pages' => 'L',
            'tmp_start' => 0,
            'tmp_pages' => 3,
            'count_pages' => 30,
        ]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        // On first page, "Anfang" should not be a link
        $this->assertStringNotContainsString('<a href="pbook.php">&laquo;&laquo; Anfang</a>', $output);
        $this->assertStringContainsString('&laquo;&laquo; Anfang', $output);
        // "Vorherige Seite" should not be a link on first page
        $this->assertStringNotContainsString('Vorherige Seite</a>', $output);
        // "Nächste Seite" should be a link
        $this->assertStringContainsString('Nächste Seite &raquo;</a>', $output);
        // "Ende" should be a link
        $this->assertStringContainsString('Ende &raquo;&raquo;</a>', $output);
    }

    #[Test]
    public function testLinearPaginationMiddlePage(): void
    {
        $vars = $this->makePagesVars([
            'config_pages' => 'L',
            'tmp_start' => 10,
            'tmp_pages' => 3,
            'count_pages' => 30,
        ]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        // On middle page, both "Anfang" and previous should be links
        $this->assertStringContainsString('Anfang</a>', $output);
        $this->assertStringContainsString('Vorherige Seite</a>', $output);
        // Next page and Ende should also be links
        $this->assertStringContainsString('Nächste Seite &raquo;</a>', $output);
        $this->assertStringContainsString('Ende &raquo;&raquo;</a>', $output);
    }

    #[Test]
    public function testLinearPaginationLastPage(): void
    {
        $vars = $this->makePagesVars([
            'config_pages' => 'L',
            'tmp_start' => 20,
            'tmp_pages' => 3,
            'count_pages' => 30,
            'config_show_entries' => 10,
        ]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        // On last page, "Ende" should not be a link
        $this->assertStringNotContainsString('Ende &raquo;&raquo;</a>', $output);
        $this->assertStringContainsString('Ende &raquo;&raquo;', $output);
        // "Nächste Seite" should not be a link
        $this->assertStringNotContainsString('Nächste Seite &raquo;</a>', $output);
        // "Vorherige Seite" and "Anfang" should be links
        $this->assertStringContainsString('Vorherige Seite</a>', $output);
        $this->assertStringContainsString('Anfang</a>', $output);
    }

    #[Test]
    public function testDirectPaginationMultiplePages(): void
    {
        $vars = $this->makePagesVars([
            'config_pages' => 'D',
            'tmp_pages' => 3,
            'tmp_page' => 2,
            'config_show_entries' => 10,
        ]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        $this->assertStringContainsString('Seite', $output);
        // Current page (2) should be shown as non-link with dashes
        $this->assertStringContainsString('- 2 -', $output);
        // Other pages should be links
        $this->assertStringContainsString('>1</a>', $output);
        $this->assertStringContainsString('>3</a>', $output);
    }

    #[Test]
    public function testNoPaginationSinglePage(): void
    {
        $vars = $this->makePagesVars([
            'config_pages' => 'L',
            'tmp_pages' => 1,
            'count_pages' => 5,
        ]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        // Single page should produce no pagination output
        $this->assertEmpty(trim($output));
    }

    #[Test]
    public function testPaginationWithSearch(): void
    {
        $vars = $this->makePagesVars([
            'config_pages' => 'L',
            'tmp_where' => 'name',
            'tmp_search' => 'TestSearch',
            'tmp_start' => 0,
            'tmp_pages' => 3,
            'count_pages' => 30,
        ]);
        $output = $this->renderTemplate(POWERBOOK_ROOT . '/pb_inc/pages.inc.php', $vars);

        // Search parameters should be included in pagination links
        $this->assertStringContainsString('tmp_where=name', $output);
        $this->assertStringContainsString('tmp_search=TestSearch', $output);
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

        $this->assertStringContainsString('http://example.org', $url);
        $this->assertStringContainsString('Homepage</a>', $url);
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
        $this->assertStringContainsString('Statement:', $entry['text']);
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
