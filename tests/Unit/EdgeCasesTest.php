<?php
/**
 * PowerBook - PHPUnit Tests
 * Edge Cases and Boundary Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

class EdgeCasesTest extends TestCase
{
    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/database.inc.php';
        require_once POWERBOOK_ROOT . '/pb_inc/validation.inc.php';
        require_once POWERBOOK_ROOT . '/pb_inc/csrf.inc.php';
    }

    // ========================================
    // Email Validation Edge Cases
    // ========================================

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function validateEmailRejectsInvalidFormats(string $email): void
    {
        $errors = validateEmail($email);

        $this->assertArrayHasKey('email', $errors);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidEmailProvider(): array
    {
        return [
            'missing @' => ['userexample.com'],
            'missing domain' => ['user@'],
            'missing local part' => ['@example.com'],
            'double @' => ['user@@example.com'],
            'spaces in email' => ['user @example.com'],
            'special chars only' => ['@@@'],
            'dot at start' => ['.user@example.com'],
            'dot at end of local' => ['user.@example.com'],
            'double dots' => ['user..name@example.com'],
        ];
    }

    #[Test]
    #[DataProvider('validEmailProvider')]
    public function validateEmailAcceptsValidFormats(string $email): void
    {
        $errors = validateEmail($email);

        $this->assertEmpty($errors);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validEmailProvider(): array
    {
        return [
            'simple email' => ['user@example.com'],
            'with subdomain' => ['user@mail.example.com'],
            'with plus' => ['user+tag@example.com'],
            'with dots' => ['first.last@example.com'],
            'with numbers' => ['user123@example.com'],
            'short domain' => ['user@ex.co'],
            'long tld' => ['user@example.museum'],
        ];
    }

    // ========================================
    // Password Validation Edge Cases
    // ========================================

    #[Test]
    public function validatePasswordWithExactlyMinLength(): void
    {
        // 8 characters exactly
        $errors = validatePassword('12345678', 8);
        $this->assertEmpty($errors);
    }

    #[Test]
    public function validatePasswordWithOneCharacterShort(): void
    {
        // 7 characters (1 short)
        $errors = validatePassword('1234567', 8);
        $this->assertArrayHasKey('password', $errors);
    }

    #[Test]
    public function validatePasswordWithUnicodeCharacters(): void
    {
        // Unicode password (should count bytes, not characters)
        $errors = validatePassword('passwörd', 8);
        // 'passwörd' is 9 bytes in UTF-8
        $this->assertEmpty($errors);
    }

    #[Test]
    public function validatePasswordWithOnlySpaces(): void
    {
        $errors = validatePassword('        ', 8);
        // 8 spaces should technically pass length check
        $this->assertEmpty($errors);
    }

    #[Test]
    public function validatePasswordWithZeroMinLength(): void
    {
        $errors = validatePassword('a', 0);
        $this->assertEmpty($errors);
    }

    // ========================================
    // CSRF Token Edge Cases
    // ========================================

    #[Test]
    public function validateCsrfTokenRejectsNull(): void
    {
        $result = validateCsrfToken('');
        $this->assertFalse($result);
    }

    #[Test]
    public function validateCsrfTokenRejectsWhitespace(): void
    {
        $result = validateCsrfToken('   ');
        $this->assertFalse($result);
    }

    #[Test]
    public function validateCsrfTokenRejectsRandomString(): void
    {
        $result = validateCsrfToken('not_a_valid_token_12345');
        $this->assertFalse($result);
    }

    #[Test]
    public function csrfTokenIsRegeneratedOnRequest(): void
    {
        $token1 = generateCsrfToken();
        regenerateCsrfToken();
        $token2 = generateCsrfToken();

        // Old token should be invalid after regeneration
        $this->assertFalse(validateCsrfToken($token1));
        $this->assertTrue(validateCsrfToken($token2));
    }

    // ========================================
    // HTML Escaping Edge Cases
    // ========================================

    #[Test]
    public function eEscapesHtmlEntities(): void
    {
        $input = '<script>alert("xss")</script>';
        $result = e($input);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function eEscapesQuotes(): void
    {
        $input = 'He said "Hello" & \'World\'';
        $result = e($input);

        $this->assertStringContainsString('&quot;', $result);
        $this->assertStringContainsString('&#039;', $result);
        $this->assertStringContainsString('&amp;', $result);
    }

    #[Test]
    public function eHandlesEmptyString(): void
    {
        $result = e('');
        $this->assertSame('', $result);
    }

    #[Test]
    public function eHandlesNumericInput(): void
    {
        $result = e(12345);
        $this->assertSame('12345', $result);
    }

    // ========================================
    // Email Header Sanitization Edge Cases
    // ========================================

    #[Test]
    public function sanitizeEmailHeaderRemovesNewlines(): void
    {
        $input = "user@example.com\r\nBcc: attacker@evil.com";
        $result = sanitizeEmailHeader($input);

        $this->assertStringNotContainsString("\r", $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    #[Test]
    public function sanitizeEmailHeaderRemovesCarriageReturns(): void
    {
        $input = "user@example.com\rCC: hacker@bad.com";
        $result = sanitizeEmailHeader($input);

        $this->assertStringNotContainsString("\r", $result);
    }

    #[Test]
    public function sanitizeEmailHeaderPreservesValidEmail(): void
    {
        $input = 'valid.email+tag@example.com';
        $result = sanitizeEmailHeader($input);

        $this->assertSame($input, $result);
    }

    // ========================================
    // Guestbook Entry Validation Edge Cases
    // ========================================

    #[Test]
    public function validateGuestbookEntryWithWhitespaceOnlyName(): void
    {
        $errors = validateGuestbookEntry('   ', 'Valid text', '');

        $this->assertArrayHasKey('name', $errors);
    }

    #[Test]
    public function validateGuestbookEntryWithWhitespaceOnlyText(): void
    {
        $errors = validateGuestbookEntry('Valid Name', '   ', '');

        $this->assertArrayHasKey('text', $errors);
    }

    #[Test]
    public function validateGuestbookEntryWithTabsOnly(): void
    {
        $errors = validateGuestbookEntry("\t\t", "\t\t", '');

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('text', $errors);
    }

    #[Test]
    public function validateGuestbookEntryWithNewlinesOnly(): void
    {
        $errors = validateGuestbookEntry("\n\n", "\n\n", '');

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('text', $errors);
    }

    // ========================================
    // Admin Data Validation Edge Cases
    // ========================================

    #[Test]
    public function validateAdminDataWithEmptyPasswordsWhenRequired(): void
    {
        $errors = validateAdminData('Admin', 'admin@example.com', '', '', true);

        $this->assertArrayHasKey('password', $errors);
    }

    #[Test]
    public function validateAdminDataWithNullPasswordsWhenRequired(): void
    {
        $errors = validateAdminData('Admin', 'admin@example.com', null, null, true);

        $this->assertArrayHasKey('password', $errors);
    }

    #[Test]
    public function validateAdminDataWithMismatchedPasswords(): void
    {
        $errors = validateAdminData('Admin', 'admin@example.com', 'password1', 'password2');

        $this->assertArrayHasKey('password', $errors);
    }

    #[Test]
    public function validateAdminDataAcceptsValidDataWithoutPassword(): void
    {
        $errors = validateAdminData('Admin', 'admin@example.com');

        $this->assertEmpty($errors);
    }
}
