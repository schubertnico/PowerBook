<?php

/**
 * PowerBook - PHPUnit Tests
 * Admin Helper Functions Tests
 *
 * Tests helper functions from admins.inc.php and statement.inc.php
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversFunction('formatPermission')]
#[CoversFunction('formatAdminPermissions')]
#[CoversFunction('getEmailFooter')]
#[CoversFunction('buildAddedEmailBody')]
#[CoversFunction('buildEditedEmailBody')]
#[CoversFunction('buildDeletedEmailBody')]
#[CoversFunction('formatStatement')]
class AdminHelpersTest extends TestCase
{
    private static bool $adminsLoaded = false;
    private static bool $statementLoaded = false;

    public static function setUpBeforeClass(): void
    {
        // Load dependencies first (only if not already loaded)
        $incPath = POWERBOOK_ROOT . '/pb_inc';

        if (!function_exists('e')) {
            require_once $incPath . '/database.inc.php';
        }
        if (!function_exists('validateCsrfToken')) {
            require_once $incPath . '/csrf.inc.php';
        }
        if (!function_exists('validateGuestbookEntry')) {
            require_once $incPath . '/validation.inc.php';
        }
        if (!function_exists('logDbError')) {
            require_once $incPath . '/error-handler.inc.php';
        }

        // Load admins.inc.php with proper global state
        if (!function_exists('formatPermission')) {
            // Set up globals required by admins.inc.php
            $GLOBALS['admin_session'] = [
                'admins' => 'Y',
                'id' => 1,
                'name' => 'TestAdmin',
                'email' => 'admin@test.com',
                'config' => 'Y',
                'release' => 'Y',
                'entries' => 'Y',
            ];

            $pdo = new \PDO('sqlite::memory:');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE pb_admins (
                id INTEGER PRIMARY KEY,
                name TEXT,
                email TEXT,
                password TEXT,
                config TEXT DEFAULT \'N\',
                admins TEXT DEFAULT \'N\',
                entries TEXT DEFAULT \'N\',
                "release" TEXT DEFAULT \'N\'
            )');
            $GLOBALS['pdo'] = $pdo;
            $GLOBALS['pb_admin'] = 'pb_admins';
            $GLOBALS['config_admin_url'] = '';

            // Avoid action processing
            $_POST = [];

            ob_start();
            include $incPath . '/admincenter/admins.inc.php';
            ob_end_clean();

            self::$adminsLoaded = true;
        }

        // Load statement.inc.php with proper global state
        if (!function_exists('formatStatement')) {
            $GLOBALS['admin_session'] = [
                'entries' => 'Y',
                'name' => 'TestAdmin',
            ];
            $GLOBALS['pb_entries'] = 'pb_entries';
            $GLOBALS['config_icons'] = 'N';
            $GLOBALS['config_text_format'] = 'Y';
            $GLOBALS['config_smilies'] = 'Y';
            $GLOBALS['config_icq'] = 'N';

            // Set $_GET and $_POST so $id = 0, triggering $showForm = false early
            $_GET = [];
            $_POST = [];

            ob_start();
            include $incPath . '/admincenter/statement.inc.php';
            ob_end_clean();

            self::$statementLoaded = true;
        }
    }

    // ========================================
    // Tests for formatPermission()
    // ========================================

    #[Test]
    public function formatPermissionReturnsJaForY(): void
    {
        $this->assertSame('Ja', formatPermission('Y'));
    }

    #[Test]
    public function formatPermissionReturnsNeinForN(): void
    {
        $this->assertSame('Nein', formatPermission('N'));
    }

    #[Test]
    public function formatPermissionReturnsNeinForEmptyString(): void
    {
        $this->assertSame('Nein', formatPermission(''));
    }

    #[Test]
    public function formatPermissionReturnsNeinForLowercaseY(): void
    {
        $this->assertSame('Nein', formatPermission('y'));
    }

    #[Test]
    public function formatPermissionReturnsNeinForArbitraryValue(): void
    {
        $this->assertSame('Nein', formatPermission('maybe'));
    }

    // ========================================
    // Tests for formatAdminPermissions()
    // ========================================

    #[Test]
    public function formatAdminPermissionsAllYes(): void
    {
        $data = [
            'config' => 'Y',
            'admins' => 'Y',
            'entries' => 'Y',
            'release' => 'Y',
        ];

        $result = formatAdminPermissions($data);

        $this->assertStringContainsString('Konfiguration: Ja', $result);
        $this->assertStringContainsString('Admin-Verwaltung: Ja', $result);
        $this->assertStringContainsString('Eintrag-Verwaltung: Ja', $result);
        $this->assertStringContainsString('Freischalten: Ja', $result);
    }

    #[Test]
    public function formatAdminPermissionsAllNo(): void
    {
        $data = [
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
        ];

        $result = formatAdminPermissions($data);

        $this->assertStringContainsString('Konfiguration: Nein', $result);
        $this->assertStringContainsString('Admin-Verwaltung: Nein', $result);
        $this->assertStringContainsString('Eintrag-Verwaltung: Nein', $result);
        $this->assertStringContainsString('Freischalten: Nein', $result);
    }

    #[Test]
    public function formatAdminPermissionsMixed(): void
    {
        $data = [
            'config' => 'Y',
            'admins' => 'N',
            'entries' => 'Y',
            'release' => 'N',
        ];

        $result = formatAdminPermissions($data);

        $this->assertStringContainsString('Konfiguration: Ja', $result);
        $this->assertStringContainsString('Admin-Verwaltung: Nein', $result);
        $this->assertStringContainsString('Eintrag-Verwaltung: Ja', $result);
        $this->assertStringContainsString('Freischalten: Nein', $result);
    }

    #[Test]
    public function formatAdminPermissionsContainsNewlines(): void
    {
        $data = [
            'config' => 'Y',
            'admins' => 'Y',
            'entries' => 'Y',
            'release' => 'Y',
        ];

        $result = formatAdminPermissions($data);

        // Each line should be separated by newlines
        $lines = explode("\n", trim($result));
        $this->assertCount(4, $lines);
    }

    // ========================================
    // Tests for getEmailFooter()
    // ========================================

    #[Test]
    public function getEmailFooterContainsSeparatorLine(): void
    {
        $footer = getEmailFooter();

        $this->assertStringContainsString('--------------------------------------------------------', $footer);
    }

    #[Test]
    public function getEmailFooterContainsPowerBookBranding(): void
    {
        $footer = getEmailFooter();

        $this->assertStringContainsString('PowerBook - PHP Guestbook System', $footer);
    }

    #[Test]
    public function getEmailFooterContainsGitHubUrl(): void
    {
        $footer = getEmailFooter();

        $this->assertStringContainsString('https://github.com/schubertnico/PowerBook.git', $footer);
    }

    #[Test]
    public function getEmailFooterContainsAutoGeneratedNotice(): void
    {
        $footer = getEmailFooter();

        $this->assertStringContainsString('DIESE E-MAIL WURDE AUTOMATISCH GENERIERT!', $footer);
    }

    // ========================================
    // Tests for buildAddedEmailBody()
    // ========================================

    #[Test]
    public function buildAddedEmailBodyContainsGreeting(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'NewAdmin',
            'email' => 'new@test.com',
            'password' => 'secret123',
            'config' => 'Y',
            'admins' => 'N',
            'entries' => 'Y',
            'release' => 'N',
            'admin_url' => 'https://example.com/admin',
        ];

        $body = buildAddedEmailBody($data);

        $this->assertStringStartsWith("Hallo!\n\n", $body);
    }

    #[Test]
    public function buildAddedEmailBodyContainsAdderName(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'NewAdmin',
            'email' => 'new@test.com',
            'password' => 'secret123',
            'config' => 'Y',
            'admins' => 'N',
            'entries' => 'Y',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildAddedEmailBody($data);

        $this->assertStringContainsString('SuperAdmin hat Sie zur Admin-Datenbank', $body);
    }

    #[Test]
    public function buildAddedEmailBodyContainsCredentials(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'NewAdmin',
            'email' => 'new@test.com',
            'password' => 'secret123',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildAddedEmailBody($data);

        $this->assertStringContainsString('Ihr Name: NewAdmin', $body);
        $this->assertStringContainsString('Ihre E-Mail: new@test.com', $body);
        $this->assertStringContainsString('Ihr Passwort: secret123', $body);
    }

    #[Test]
    public function buildAddedEmailBodyContainsPermissions(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'NewAdmin',
            'email' => 'new@test.com',
            'password' => 'secret123',
            'config' => 'Y',
            'admins' => 'N',
            'entries' => 'Y',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildAddedEmailBody($data);

        $this->assertStringContainsString('Konfiguration: Ja', $body);
        $this->assertStringContainsString('Admin-Verwaltung: Nein', $body);
        $this->assertStringContainsString('Eintrag-Verwaltung: Ja', $body);
    }

    #[Test]
    public function buildAddedEmailBodyContainsSecurityWarning(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'NewAdmin',
            'email' => 'new@test.com',
            'password' => 'secret123',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildAddedEmailBody($data);

        $this->assertStringContainsString('Geben Sie diese Informationen niemals weiter!', $body);
    }

    #[Test]
    public function buildAddedEmailBodyWithAdminUrl(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'NewAdmin',
            'email' => 'new@test.com',
            'password' => 'secret123',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => 'https://example.com/admin',
        ];

        $body = buildAddedEmailBody($data);

        $this->assertStringContainsString('(https://example.com/admin)', $body);
    }

    #[Test]
    public function buildAddedEmailBodyWithoutAdminUrl(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'NewAdmin',
            'email' => 'new@test.com',
            'password' => 'secret123',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildAddedEmailBody($data);

        $this->assertStringNotContainsString('(https://', $body);
        $this->assertStringContainsString("im AdminCenter.\n", $body);
    }

    // ========================================
    // Tests for buildEditedEmailBody()
    // ========================================

    #[Test]
    public function buildEditedEmailBodyContainsGreeting(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'EditedAdmin',
            'email' => 'edited@test.com',
            'password' => '',
            'config' => 'Y',
            'admins' => 'Y',
            'entries' => 'Y',
            'release' => 'Y',
            'admin_url' => '',
        ];

        $body = buildEditedEmailBody($data);

        $this->assertStringStartsWith("Hallo!\n\n", $body);
    }

    #[Test]
    public function buildEditedEmailBodyContainsEditorName(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'EditedAdmin',
            'email' => 'edited@test.com',
            'password' => '',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildEditedEmailBody($data);

        $this->assertStringContainsString('SuperAdmin hat Ihr Profil im AdminCenter', $body);
    }

    #[Test]
    public function buildEditedEmailBodyContainsNewCredentials(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'EditedAdmin',
            'email' => 'edited@test.com',
            'password' => '',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildEditedEmailBody($data);

        $this->assertStringContainsString('Ihr (neuer) Name: EditedAdmin', $body);
        $this->assertStringContainsString('Ihre (neue) E-Mail: edited@test.com', $body);
    }

    #[Test]
    public function buildEditedEmailBodyWithPassword(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'EditedAdmin',
            'email' => 'edited@test.com',
            'password' => 'newpass456',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildEditedEmailBody($data);

        $this->assertStringContainsString('Ihr (neues) Passwort: newpass456', $body);
    }

    #[Test]
    public function buildEditedEmailBodyWithoutPassword(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'EditedAdmin',
            'email' => 'edited@test.com',
            'password' => '',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildEditedEmailBody($data);

        $this->assertStringNotContainsString('Passwort:', $body);
    }

    #[Test]
    public function buildEditedEmailBodyWithAdminUrl(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'EditedAdmin',
            'email' => 'edited@test.com',
            'password' => '',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => 'https://example.com/admin',
        ];

        $body = buildEditedEmailBody($data);

        $this->assertStringContainsString('Die URL zum AdminCenter ist: https://example.com/admin', $body);
    }

    #[Test]
    public function buildEditedEmailBodyWithoutAdminUrl(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'EditedAdmin',
            'email' => 'edited@test.com',
            'password' => '',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildEditedEmailBody($data);

        $this->assertStringNotContainsString('Die URL zum AdminCenter ist:', $body);
    }

    #[Test]
    public function buildEditedEmailBodyContainsSecurityWarning(): void
    {
        $data = [
            'by' => 'SuperAdmin',
            'name' => 'EditedAdmin',
            'email' => 'edited@test.com',
            'password' => '',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'N',
            'release' => 'N',
            'admin_url' => '',
        ];

        $body = buildEditedEmailBody($data);

        $this->assertStringContainsString('Geben Sie diese Informationen niemals weiter!', $body);
    }

    // ========================================
    // Tests for buildDeletedEmailBody()
    // ========================================

    #[Test]
    public function buildDeletedEmailBodyContainsGreeting(): void
    {
        $data = [
            'by' => 'SuperAdmin',
        ];

        $body = buildDeletedEmailBody($data);

        $this->assertStringStartsWith("Hallo!\n\n", $body);
    }

    #[Test]
    public function buildDeletedEmailBodyContainsDeleterName(): void
    {
        $data = [
            'by' => 'SuperAdmin',
        ];

        $body = buildDeletedEmailBody($data);

        $this->assertStringContainsString('SuperAdmin hat Sie aus der Admin-Datenbank', $body);
    }

    #[Test]
    public function buildDeletedEmailBodyContainsAccessRevocation(): void
    {
        $data = [
            'by' => 'SuperAdmin',
        ];

        $body = buildDeletedEmailBody($data);

        $this->assertStringContainsString('Sie sind nicht mehr berechtigt, mit PowerBook zu arbeiten.', $body);
    }

    #[Test]
    public function buildDeletedEmailBodyDoesNotContainPermissions(): void
    {
        $data = [
            'by' => 'SuperAdmin',
        ];

        $body = buildDeletedEmailBody($data);

        $this->assertStringNotContainsString('Konfiguration:', $body);
        $this->assertStringNotContainsString('Passwort:', $body);
    }

    // ========================================
    // Tests for formatStatement()
    // ========================================

    #[Test]
    public function formatStatementEscapesHtml(): void
    {
        $result = formatStatement('<script>alert("xss")</script>');

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringContainsString('&lt;/script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function formatStatementConvertsNewlinesToBr(): void
    {
        $result = formatStatement("line1\nline2");

        $this->assertStringContainsString('line1<br>line2', $result);
    }

    #[Test]
    public function formatStatementProcessesBoldBBCode(): void
    {
        $result = formatStatement('[b]bold text[/b]');

        $this->assertStringContainsString('<b>bold text</b>', $result);
    }

    #[Test]
    public function formatStatementProcessesItalicBBCode(): void
    {
        $result = formatStatement('[i]italic text[/i]');

        $this->assertStringContainsString('<i>italic text</i>', $result);
    }

    #[Test]
    public function formatStatementProcessesUnderlineBBCode(): void
    {
        $result = formatStatement('[u]underline text[/u]');

        $this->assertStringContainsString('<u>underline text</u>', $result);
    }

    #[Test]
    public function formatStatementProcessesSmallBBCode(): void
    {
        $result = formatStatement('[small]small text[/small]');

        $this->assertStringContainsString('<small>small text</small>', $result);
    }

    #[Test]
    public function formatStatementBBCodeIsCaseInsensitive(): void
    {
        $result = formatStatement('[B]bold[/B] [I]italic[/I] [U]under[/U] [SMALL]sm[/SMALL]');

        $this->assertStringContainsString('<b>bold</b>', $result);
        $this->assertStringContainsString('<i>italic</i>', $result);
        $this->assertStringContainsString('<u>under</u>', $result);
        $this->assertStringContainsString('<small>sm</small>', $result);
    }

    #[Test]
    public function formatStatementAutoLinksHttpUrls(): void
    {
        $result = formatStatement('Visit https://example.com for more');

        $this->assertStringContainsString('<a href="https://example.com"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
        $this->assertStringContainsString('rel="noopener"', $result);
    }

    #[Test]
    public function formatStatementAutoLinksHttpsUrls(): void
    {
        $result = formatStatement('See http://example.com/path?q=1 here');

        $this->assertStringContainsString('<a href="http://example.com/path?q=1"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
    }

    #[Test]
    public function formatStatementAutoLinksWwwUrls(): void
    {
        $result = formatStatement('Visit www.example.com for info');

        $this->assertStringContainsString('<a href="http://www.example.com"', $result);
        $this->assertStringContainsString('>www.example.com</a>', $result);
    }

    #[Test]
    public function formatStatementReplacesHappySmiley(): void
    {
        $result = formatStatement('Hello :)');

        $this->assertStringContainsString('<img src="../smilies/happy1.gif"', $result);
        $this->assertStringContainsString('alt=":happy:"', $result);
    }

    #[Test]
    public function formatStatementReplacesSadSmiley(): void
    {
        $result = formatStatement('Oh no :(');

        $this->assertStringContainsString('<img src="../smilies/sad2.gif"', $result);
    }

    #[Test]
    public function formatStatementReplacesWinkSmiley(): void
    {
        $result = formatStatement('Wink ;)');

        $this->assertStringContainsString('<img src="../smilies/happy3.gif"', $result);
    }

    #[Test]
    public function formatStatementReplacesGrinSmiley(): void
    {
        $result = formatStatement('Haha :D');

        $this->assertStringContainsString('<img src="../smilies/happy4.gif"', $result);
    }

    #[Test]
    public function formatStatementReplacesTongueSmiley(): void
    {
        $result = formatStatement('Tongue :P');

        $this->assertStringContainsString('<img src="../smilies/happy2.gif"', $result);
    }

    #[Test]
    public function formatStatementReplacesConfusedSmiley(): void
    {
        $result = formatStatement('Hmm ?:)');

        $this->assertStringContainsString('<img src="../smilies/confused.gif"', $result);
    }

    #[Test]
    public function formatStatementReplacesShockSmiley(): void
    {
        $result = formatStatement('Wow !:)');

        $this->assertStringContainsString('<img src="../smilies/shock.gif"', $result);
    }

    #[Test]
    public function formatStatementReturnsEmptyForEmptyInput(): void
    {
        $result = formatStatement('');

        $this->assertSame('', $result);
    }

    #[Test]
    public function formatStatementHandlesPlainTextUnchanged(): void
    {
        $result = formatStatement('Just plain text');

        $this->assertSame('Just plain text', $result);
    }

    #[Test]
    public function formatStatementEscapesQuotes(): void
    {
        $result = formatStatement('He said "hello" & \'goodbye\'');

        $this->assertStringContainsString('&quot;hello&quot;', $result);
        $this->assertStringContainsString('&#039;goodbye&#039;', $result);
        $this->assertStringContainsString('&amp;', $result);
    }

    #[Test]
    public function formatStatementCombinesMultipleFeatures(): void
    {
        $result = formatStatement("[b]Bold[/b] and a link https://example.com :)");

        $this->assertStringContainsString('<b>Bold</b>', $result);
        $this->assertStringContainsString('<a href="https://example.com"', $result);
        $this->assertStringContainsString('<img src="../smilies/happy1.gif"', $result);
    }

    #[Test]
    public function formatStatementReplacesCryingSmiley(): void
    {
        $result = formatStatement('Crying ;(');

        $this->assertStringContainsString('<img src="../smilies/sad1.gif"', $result);
    }

    #[Test]
    public function formatStatementReplacesSadThreeSmiley(): void
    {
        $result = formatStatement('Upset :X');

        $this->assertStringContainsString('<img src="../smilies/sad3.gif"', $result);
    }

    #[Test]
    public function formatStatementReplacesClownSmiley(): void
    {
        $result = formatStatement('Funny ;o)');

        $this->assertStringContainsString('<img src="../smilies/happy5.gif"', $result);
    }
}
