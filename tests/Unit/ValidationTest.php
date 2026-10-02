<?php

/**
 * PowerBook - PHPUnit Tests
 * Validation Functions Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversFunction('pb_validate_entry')]
#[CoversFunction('pb_normalize_entry')]
#[CoversFunction('validateAdminLogin')]
#[CoversFunction('validateEmail')]
#[CoversFunction('validateAdminData')]
#[CoversFunction('validatePassword')]
#[CoversFunction('validatePasswordConfirmation')]
class ValidationTest extends TestCase
{
    // ========================================
    // Tests for pb_normalize_entry() / pb_validate_entry()
    // ========================================

    #[Test]
    public function validateEntryReturnsEmptyArrayForValidData(): void
    {
        $errors = pb_validate_entry($this->entry());

        $this->assertSame([], $errors);
    }

    #[Test]
    public function validateEntryRequiresName(): void
    {
        $errors = pb_validate_entry($this->entry(['name' => '']));

        $this->assertSame('Bitte geben Sie Ihren Namen ein.', $errors['name']);
    }

    #[Test]
    public function validateEntryRequiresText(): void
    {
        $errors = pb_validate_entry($this->entry(['text' => '']));

        $this->assertSame('Bitte schreiben Sie einen Text.', $errors['text']);
    }

    #[Test]
    public function validateEntryRejectsInvalidEmail(): void
    {
        foreach (['invalid-email', 'invalidemail.com', 'invalid@emailcom', 'x@example.org?subject=Spam'] as $email) {
            $errors = pb_validate_entry($this->entry(['email' => $email]));
            $this->assertArrayHasKey('email', $errors, $email);
            $this->assertStringContainsString('E-Mail-Adresse', $errors['email']);
        }
    }

    #[Test]
    public function validateEntryAcceptsEmptyEmailAndUrl(): void
    {
        $errors = pb_validate_entry($this->entry(['email' => '', 'url' => '']));

        $this->assertArrayNotHasKey('email', $errors);
        $this->assertArrayNotHasKey('url', $errors);
    }

    #[Test]
    public function validateEntryChecksUrl(): void
    {
        $this->assertArrayNotHasKey('url', pb_validate_entry($this->entry(['url' => 'www.example.org'])));
        $this->assertArrayNotHasKey('url', pb_validate_entry($this->entry(['url' => 'https://www.example.org/pfad?x=1'])));
        foreach (['kein link', 'javascript:alert(1)', 'data:text/html,x', 'ftp://example.org', 'https://example.org/" onmouseover="x', 'https://localhost'] as $url) {
            $this->assertArrayHasKey('url', pb_validate_entry($this->entry(['url' => $url])), $url);
        }
        $errors = pb_validate_entry($this->entry(['url' => 'https://example.org/' . str_repeat('a', 190)]));
        $this->assertStringContainsString('200 Zeichen', $errors['url']);
    }

    #[Test]
    public function validateEntryChecksLengths(): void
    {
        $this->assertArrayNotHasKey('name', pb_validate_entry($this->entry(['name' => str_repeat('Ö', 100)])));
        $this->assertArrayHasKey('name', pb_validate_entry($this->entry(['name' => str_repeat('Ö', 101)])));
        $this->assertArrayNotHasKey('text', pb_validate_entry($this->entry(['text' => str_repeat('😀', 5000)])));
        $this->assertArrayHasKey('text', pb_validate_entry($this->entry(['text' => str_repeat('x', 5001)])));
        $this->assertArrayHasKey('email', pb_validate_entry($this->entry(['email' => str_repeat('a', 240) . '@example.org'])));
    }

    #[Test]
    public function validateEntryChecksIconWhitelist(): void
    {
        $this->assertArrayNotHasKey('icon', pb_validate_entry($this->entry(['icon' => 'happy1'])));
        $this->assertArrayNotHasKey('icon', pb_validate_entry($this->entry(['icon' => 'no'])));
        $this->assertArrayHasKey('icon', pb_validate_entry($this->entry(['icon' => 'x" onerror="a'])));
        $this->assertArrayHasKey('icon', pb_validate_entry($this->entry(['icon' => '../../etc/passwd'])));
        $this->assertArrayNotHasKey('icon', pb_validate_entry($this->entry(['icon' => 'boese']), false));
    }

    #[Test]
    public function validateEntryReturnsAllErrorsAtOnce(): void
    {
        $errors = pb_validate_entry($this->entry(['name' => '', 'text' => '', 'email' => 'invalid', 'url' => 'kein link']));

        $this->assertSame(['name', 'email', 'url', 'text'], array_keys($errors));
    }

    #[Test]
    public function normalizeEntryCleansInput(): void
    {
        $data = pb_normalize_entry([
            'name' => "  Anke\r\n  & Co  ",
            'email2' => ' anke@example.org ',
            'url' => ' www.example.org ',
            'text' => "  Zeile 1\r\nZeile 2\x00  ",
            'icon' => '',
        ]);

        $this->assertSame('Anke & Co', $data['name']);
        $this->assertSame('anke@example.org', $data['email']);
        $this->assertSame('www.example.org', $data['url']);
        $this->assertSame("Zeile 1\nZeile 2", $data['text']);
        $this->assertSame('no', $data['icon']);
        $this->assertSame('N', $data['smilies']);
        $this->assertSame('Y', pb_normalize_entry(['smilies2' => 'Y'])['smilies']);
    }

    #[Test]
    public function normalizeEntryCountsLineBreakAsOneCharacter(): void
    {
        // 4950 Zeichen + 49 Zeilenumbrüche (CRLF aus dem Browser) = 4999 Zeichen
        $text = implode("\r\n", array_fill(0, 50, str_repeat('x', 99)));
        $data = pb_normalize_entry(['name' => 'X', 'text' => $text]);

        $this->assertSame(4999, mb_strlen($data['text']));
        $this->assertArrayNotHasKey('text', pb_validate_entry($data));
    }

    #[Test]
    public function normalizeEntryIgnoresArrays(): void
    {
        $data = pb_normalize_entry(['name' => ['x'], 'text' => ['y']]);

        $this->assertSame('', $data['name']);
        $this->assertSame('', $data['text']);
    }

    // ========================================
    // Tests for validateAdminLogin()
    // ========================================

    #[Test]
    public function validateAdminLoginReturnsEmptyArrayForValidData(): void
    {
        $errors = validateAdminLogin('admin', 'password123');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateAdminLoginRequiresName(): void
    {
        $errors = validateAdminLogin('', 'password123');

        $this->assertArrayHasKey('name', $errors);
        $this->assertStringContainsString('Name', $errors['name']);
    }

    #[Test]
    public function validateAdminLoginRequiresPassword(): void
    {
        $errors = validateAdminLogin('admin', '');

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('Passwort', $errors['password']);
    }

    #[Test]
    public function validateAdminLoginTrimsName(): void
    {
        $errors = validateAdminLogin('   ', 'password123');

        $this->assertArrayHasKey('name', $errors);
    }

    #[Test]
    public function validateAdminLoginReturnsMultipleErrors(): void
    {
        $errors = validateAdminLogin('', '');

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertCount(2, $errors);
    }

    // ========================================
    // Tests for validateEmail()
    // ========================================

    #[Test]
    public function validateEmailReturnsEmptyArrayForValidEmail(): void
    {
        $errors = validateEmail('test@example.com');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateEmailAcceptsEmptyWhenNotRequired(): void
    {
        $errors = validateEmail('', false);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateEmailRejectsEmptyWhenRequired(): void
    {
        $errors = validateEmail('', true);

        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('erforderlich', $errors['email']);
    }

    #[Test]
    public function validateEmailRejectsInvalidFormat(): void
    {
        $errors = validateEmail('invalid-email');

        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('Ungültige', $errors['email']);
    }

    #[Test]
    public function validateEmailTrimsWhitespace(): void
    {
        $errors = validateEmail('  test@example.com  ');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateEmailAcceptsComplexEmail(): void
    {
        $errors = validateEmail('user.name+tag@sub.example.co.uk');

        $this->assertEmpty($errors);
    }

    // ========================================
    // Tests for validateAdminData()
    // ========================================

    #[Test]
    public function validateAdminDataReturnsEmptyArrayForValidData(): void
    {
        $errors = validateAdminData('Admin', 'admin@example.com');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateAdminDataRequiresName(): void
    {
        $errors = validateAdminData('', 'admin@example.com');

        $this->assertArrayHasKey('name', $errors);
    }

    #[Test]
    public function validateAdminDataRequiresEmail(): void
    {
        $errors = validateAdminData('Admin', '');

        $this->assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function validateAdminDataRejectsInvalidEmail(): void
    {
        $errors = validateAdminData('Admin', 'invalid-email');

        $this->assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function validateAdminDataRequiresPasswordWhenRequired(): void
    {
        $errors = validateAdminData('Admin', 'admin@example.com', '', '', true);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('erforderlich', $errors['password']);
    }

    #[Test]
    public function validateAdminDataRejectsNonMatchingPasswords(): void
    {
        $errors = validateAdminData('Admin', 'admin@example.com', 'pass1', 'pass2');

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('stimmen nicht', $errors['password']);
    }

    #[Test]
    public function validateAdminDataAcceptsMatchingPasswords(): void
    {
        $errors = validateAdminData('Admin', 'admin@example.com', 'password123', 'password123');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateAdminDataAcceptsNullPasswords(): void
    {
        $errors = validateAdminData('Admin', 'admin@example.com', null, null);

        $this->assertEmpty($errors);
    }

    // ========================================
    // Tests for validatePassword()
    // ========================================

    #[Test]
    public function validatePasswordReturnsEmptyArrayForValidPassword(): void
    {
        $errors = validatePassword('securepassword123');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validatePasswordAcceptsEmptyWhenNotRequired(): void
    {
        $errors = validatePassword('', 8, false);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validatePasswordRequiresPasswordWhenRequired(): void
    {
        $errors = validatePassword('', 8, true);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('erforderlich', $errors['password']);
    }

    #[Test]
    public function validatePasswordRejectsShortPassword(): void
    {
        $errors = validatePassword('short', 8);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('mindestens', $errors['password']);
        $this->assertStringContainsString('8', $errors['password']);
    }

    #[Test]
    public function validatePasswordAcceptsExactMinLength(): void
    {
        $errors = validatePassword('12345678', 8);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validatePasswordRespectsCustomMinLength(): void
    {
        $errors = validatePassword('1234', 6);

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('6', $errors['password']);
    }

    #[Test]
    public function validatePasswordAcceptsLongPassword(): void
    {
        $errors = validatePassword('thisIsAVeryLongAndSecurePassword123!@#');

        $this->assertEmpty($errors);
    }

    // ========================================
    // Tests for validatePasswordConfirmation()
    // ========================================

    #[Test]
    public function validatePasswordConfirmationReturnsEmptyForMatchingPasswords(): void
    {
        $errors = validatePasswordConfirmation('password123', 'password123');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validatePasswordConfirmationRejectsNonMatchingPasswords(): void
    {
        $errors = validatePasswordConfirmation('password123', 'password456');

        $this->assertArrayHasKey('password_confirm', $errors);
        $this->assertStringContainsString('stimmen nicht', $errors['password_confirm']);
    }

    #[Test]
    public function validatePasswordConfirmationAcceptsEmptyPasswords(): void
    {
        $errors = validatePasswordConfirmation('', '');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validatePasswordConfirmationRejectsEmptyConfirmation(): void
    {
        $errors = validatePasswordConfirmation('password123', '');

        $this->assertArrayHasKey('password_confirm', $errors);
    }

    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/validation.inc.php';
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array{name: string, email: string, url: string, text: string, icon: string, smilies: string}
     */
    private function entry(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'url' => '',
            'text' => 'Test message',
            'icon' => 'no',
            'smilies' => 'Y',
        ], $overrides);
    }
}
