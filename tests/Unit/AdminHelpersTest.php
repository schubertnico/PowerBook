<?php

/**
 * PowerBook - PHPUnit Tests
 * Admin Helper Functions Tests (Mailtexte der Admin-Verwaltung)
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
#[CoversFunction('buildPasswordLinkEmailBody')]
class AdminHelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/admincenter/layout.inc.php';
        require_once POWERBOOK_ROOT . '/pb_inc/admincenter/admin_email_helpers.inc.php';
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
        $result = formatAdminPermissions(['config' => 'Y', 'admins' => 'Y', 'entries' => 'Y', 'release' => 'Y']);

        $this->assertStringContainsString('- Einträge freischalten: Ja', $result);
        $this->assertStringContainsString('- Einträge bearbeiten und löschen: Ja', $result);
        $this->assertStringContainsString('- Konfiguration ändern: Ja', $result);
        $this->assertStringContainsString('- Admins verwalten: Ja', $result);
    }

    #[Test]
    public function formatAdminPermissionsAllNo(): void
    {
        $result = formatAdminPermissions(['config' => 'N', 'admins' => 'N', 'entries' => 'N', 'release' => 'N']);

        $this->assertStringNotContainsString(': Ja', $result);
        $this->assertSame(4, substr_count($result, ': Nein'));
    }

    #[Test]
    public function formatAdminPermissionsMissingKeysCountAsNo(): void
    {
        $result = formatAdminPermissions(['release' => 'Y']);

        $this->assertStringContainsString('- Einträge freischalten: Ja', $result);
        $this->assertStringContainsString('- Admins verwalten: Nein', $result);
    }

    #[Test]
    public function formatAdminPermissionsUsesGlossaryOrder(): void
    {
        $lines = explode("\n", trim(formatAdminPermissions(['config' => 'Y', 'admins' => 'Y', 'entries' => 'Y', 'release' => 'Y'])));

        $this->assertCount(4, $lines);
        $this->assertStringStartsWith('- Einträge freischalten', $lines[0]);
        $this->assertStringStartsWith('- Admins verwalten', $lines[3]);
    }

    // ========================================
    // Tests for getEmailFooter()
    // ========================================

    #[Test]
    public function getEmailFooterUsesSignatureSeparator(): void
    {
        $this->assertStringStartsWith("\n-- \n", getEmailFooter());
    }

    #[Test]
    public function getEmailFooterContainsBrandingWithUmlaut(): void
    {
        $footer = getEmailFooter();

        $this->assertStringContainsString('PowerBook – Gästebuch', $footer);
        $this->assertStringContainsString('https://www.powerscripts.org', $footer);
        $this->assertStringNotContainsString('Gaestebuch', $footer);
        $this->assertStringNotContainsString('AUTOMATISCH', $footer);
    }

    #[Test]
    public function buildAddedEmailBodyHasLinkInsteadOfPassword(): void
    {
        $body = buildAddedEmailBody($this->data());

        $this->assertStringStartsWith("Hallo Ole,\n\n", $body);
        $this->assertStringContainsString('Anke hat für Sie einen Zugang zum AdminCenter von „Testgästebuch“ angelegt.', $body);
        $this->assertStringContainsString("token=abc\n", $body);
        $this->assertStringContainsString('48 Stunden', $body);
        $this->assertStringNotContainsString('Ihr Passwort:', $body);
    }

    #[Test]
    public function buildEditedEmailBodyListsDataAndKeepsPassword(): void
    {
        $body = buildEditedEmailBody($this->data());

        $this->assertStringContainsString('Anke hat Ihren Zugang zum AdminCenter von „Testgästebuch“ geändert.', $body);
        $this->assertStringContainsString('E-Mail-Adresse: ole@example.org', $body);
        $this->assertStringContainsString('Ihr Passwort bleibt unverändert.', $body);
        $this->assertStringContainsString('https://example.com/pb_inc/admincenter/', $body);
    }

    #[Test]
    public function buildPasswordLinkEmailBodyContainsLink(): void
    {
        $body = buildPasswordLinkEmailBody($this->data());

        $this->assertStringContainsString('Anke hat Ihnen einen Link geschickt', $body);
        $this->assertStringContainsString('token=abc', $body);
        $this->assertStringContainsString('Ihr bisheriges Passwort gilt weiter', $body);
    }

    #[Test]
    public function buildDeletedEmailBodyRevokesAccess(): void
    {
        $body = buildDeletedEmailBody($this->data());

        $this->assertStringStartsWith("Hallo Ole,\n\n", $body);
        $this->assertStringContainsString('Anke hat Ihren Zugang zum AdminCenter von „Testgästebuch“ entfernt.', $body);
        $this->assertStringContainsString('Sie können sich dort nicht mehr anmelden.', $body);
        $this->assertStringNotContainsString('Passwort', $body);
    }

    // ========================================
    // Tests for the mail bodies
    // ========================================

    /**
     * @return array<string, string>
     */
    private function data(): array
    {
        return [
            'by' => 'Anke',
            'name' => 'Ole',
            'email' => 'ole@example.org',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'Y',
            'release' => 'Y',
            'admin_url' => 'https://example.com/pb_inc/admincenter/',
            'link' => 'https://example.com/pb_inc/admincenter/index.php?page=password&token=abc',
            'title' => 'Testgästebuch',
            'old_email' => 'alt@example.org',
            'new_email' => 'ole@example.org',
        ];
    }
}
