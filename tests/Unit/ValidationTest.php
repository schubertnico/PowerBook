<?php
/**
 * PowerBook - PHPUnit Tests
 * Validation Functions Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversFunction('validateGuestbookEntry')]
#[CoversFunction('validateAdminLogin')]
#[CoversFunction('validateEmail')]
#[CoversFunction('validateAdminData')]
#[CoversFunction('validateUrl')]
#[CoversFunction('validatePassword')]
#[CoversFunction('validatePasswordConfirmation')]
class ValidationTest extends TestCase
{
    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/validation.inc.php';
    }

    // ========================================
    // Tests for validateGuestbookEntry()
    // ========================================

    #[Test]
    public function validateGuestbookEntryReturnsEmptyArrayForValidData(): void
    {
        $errors = validateGuestbookEntry('Test User', 'Test message', 'test@example.com');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateGuestbookEntryRequiresName(): void
    {
        $errors = validateGuestbookEntry('', 'Test message', '');

        $this->assertArrayHasKey('name', $errors);
        $this->assertStringContainsString('Name', $errors['name']);
    }

    #[Test]
    public function validateGuestbookEntryRequiresText(): void
    {
        $errors = validateGuestbookEntry('Test User', '', '');

        $this->assertArrayHasKey('text', $errors);
        $this->assertStringContainsString('Text', $errors['text']);
    }

    #[Test]
    public function validateGuestbookEntryRejectsInvalidEmail(): void
    {
        $errors = validateGuestbookEntry('Test User', 'Test message', 'invalid-email');

        $this->assertArrayHasKey('email', $errors);
        $this->assertStringContainsString('eMail', $errors['email']);
    }

    #[Test]
    public function validateGuestbookEntryAcceptsEmptyEmail(): void
    {
        $errors = validateGuestbookEntry('Test User', 'Test message', '');

        $this->assertArrayNotHasKey('email', $errors);
    }

    #[Test]
    public function validateGuestbookEntryRejectsEmailWithoutAtSign(): void
    {
        $errors = validateGuestbookEntry('Test User', 'Test message', 'invalidemail.com');

        $this->assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function validateGuestbookEntryRejectsEmailWithoutDot(): void
    {
        $errors = validateGuestbookEntry('Test User', 'Test message', 'invalid@emailcom');

        $this->assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function validateGuestbookEntryTrimsWhitespaceFromName(): void
    {
        $errors = validateGuestbookEntry('   ', 'Test message', '');

        $this->assertArrayHasKey('name', $errors);
    }

    #[Test]
    public function validateGuestbookEntryTrimsWhitespaceFromText(): void
    {
        $errors = validateGuestbookEntry('Test User', '   ', '');

        $this->assertArrayHasKey('text', $errors);
    }

    #[Test]
    public function validateGuestbookEntryReturnsMultipleErrors(): void
    {
        $errors = validateGuestbookEntry('', '', 'invalid');

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('text', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertCount(3, $errors);
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
        $this->assertStringContainsString('Ungueltige', $errors['email']);
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
    // Tests for validateUrl()
    // ========================================

    #[Test]
    public function validateUrlReturnsEmptyArrayForValidUrl(): void
    {
        $errors = validateUrl('https://example.com');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateUrlAcceptsEmptyString(): void
    {
        $errors = validateUrl('');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateUrlAcceptsHttpUrl(): void
    {
        $errors = validateUrl('http://example.com');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateUrlAcceptsUrlWithPath(): void
    {
        $errors = validateUrl('https://example.com/path/to/page.html');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateUrlAcceptsUrlWithQueryString(): void
    {
        $errors = validateUrl('https://example.com/search?q=test&page=1');

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validateUrlRejectsInvalidUrl(): void
    {
        $errors = validateUrl('not-a-valid-url');

        $this->assertArrayHasKey('url', $errors);
        $this->assertStringContainsString('URL', $errors['url']);
    }

    #[Test]
    public function validateUrlRejectsUrlWithoutProtocol(): void
    {
        $errors = validateUrl('example.com');

        $this->assertArrayHasKey('url', $errors);
    }

    #[Test]
    public function validateUrlTrimsWhitespace(): void
    {
        $errors = validateUrl('  https://example.com  ');

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
}
