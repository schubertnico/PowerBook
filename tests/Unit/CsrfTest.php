<?php

/**
 * PowerBook - PHPUnit Tests
 * CSRF Protection Functions Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversFunction('generateCsrfToken')]
#[CoversFunction('validateCsrfToken')]
#[CoversFunction('csrfField')]
#[CoversFunction('regenerateCsrfToken')]
class CsrfTest extends TestCase
{
    // ========================================
    // Tests for generateCsrfToken() function
    // ========================================

    #[Test]
    public function generateCsrfTokenCreatesToken(): void
    {
        $token = generateCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    #[Test]
    public function generateCsrfTokenReturns64CharHexString(): void
    {
        $token = generateCsrfToken();

        // 32 bytes = 64 hex characters
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    #[Test]
    public function generateCsrfTokenReturnsSameTokenOnSubsequentCalls(): void
    {
        $token1 = generateCsrfToken();
        $token2 = generateCsrfToken();

        $this->assertSame($token1, $token2);
    }

    #[Test]
    public function generateCsrfTokenStoresInSession(): void
    {
        $token = generateCsrfToken();

        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    // ========================================
    // Tests for validateCsrfToken() function
    // ========================================

    #[Test]
    public function validateCsrfTokenReturnsTrueForValidToken(): void
    {
        $token = generateCsrfToken();

        $this->assertTrue(validateCsrfToken($token));
    }

    #[Test]
    public function validateCsrfTokenReturnsFalseForInvalidToken(): void
    {
        generateCsrfToken();

        $this->assertFalse(validateCsrfToken('wrong_token'));
    }

    #[Test]
    public function validateCsrfTokenReturnsFalseForNull(): void
    {
        generateCsrfToken();

        $this->assertFalse(validateCsrfToken(null));
    }

    #[Test]
    public function validateCsrfTokenReturnsFalseForEmptyString(): void
    {
        generateCsrfToken();

        $this->assertFalse(validateCsrfToken(''));
    }

    #[Test]
    public function validateCsrfTokenReturnsFalseWhenNoSessionToken(): void
    {
        // No token generated
        $this->assertFalse(validateCsrfToken('any_token'));
    }

    #[Test]
    public function validateCsrfTokenIsCaseSensitive(): void
    {
        $token = generateCsrfToken();
        $upperToken = strtoupper($token);

        // If token has any letters, uppercase version should fail
        if ($token !== $upperToken) {
            $this->assertFalse(validateCsrfToken($upperToken));
        } else {
            // Token is all numbers, so it's the same
            $this->assertTrue(validateCsrfToken($upperToken));
        }
    }

    #[Test]
    public function validateCsrfTokenUsesTimingSafeComparison(): void
    {
        $token = generateCsrfToken();

        // This test ensures the function works correctly
        // The actual timing-safe comparison is done by hash_equals internally
        $this->assertTrue(validateCsrfToken($token));
        $this->assertFalse(validateCsrfToken($token . 'x'));
        $this->assertFalse(validateCsrfToken('x' . $token));
    }

    // ========================================
    // Tests for csrfField() function
    // ========================================

    #[Test]
    public function csrfFieldGeneratesHiddenInput(): void
    {
        $field = csrfField();

        $this->assertStringContainsString('<input type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    #[Test]
    public function csrfFieldContainsValidToken(): void
    {
        $field = csrfField();
        $token = generateCsrfToken();

        $this->assertStringContainsString('value="' . $token . '"', $field);
    }

    #[Test]
    public function csrfFieldEscapesOutput(): void
    {
        $field = csrfField();

        // The output should be properly escaped HTML
        $this->assertStringNotContainsString('<script>', $field);

        // Field should be valid HTML
        $this->assertStringStartsWith('<input', $field);
        $this->assertStringEndsWith('>', $field);
    }

    // ========================================
    // Tests for regenerateCsrfToken() function
    // ========================================

    #[Test]
    public function regenerateCsrfTokenCreatesNewToken(): void
    {
        $token1 = generateCsrfToken();
        $token2 = regenerateCsrfToken();

        $this->assertNotSame($token1, $token2);
    }

    #[Test]
    public function regenerateCsrfTokenUpdatesSession(): void
    {
        generateCsrfToken();
        $newToken = regenerateCsrfToken();

        $this->assertSame($newToken, $_SESSION['csrf_token']);
    }

    #[Test]
    public function regenerateCsrfTokenReturnsValidHexString(): void
    {
        generateCsrfToken();
        $newToken = regenerateCsrfToken();

        $this->assertSame(64, strlen($newToken));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $newToken);
    }

    #[Test]
    public function oldTokenInvalidAfterRegeneration(): void
    {
        $oldToken = generateCsrfToken();
        regenerateCsrfToken();

        $this->assertFalse(validateCsrfToken($oldToken));
    }

    #[Test]
    public function newTokenValidAfterRegeneration(): void
    {
        generateCsrfToken();
        $newToken = regenerateCsrfToken();

        $this->assertTrue(validateCsrfToken($newToken));
    }

    #[Test]
    public function regenerateCsrfTokenWorksWithoutPriorGeneration(): void
    {
        // No prior generateCsrfToken() call
        $token = regenerateCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token));
        $this->assertTrue(validateCsrfToken($token));
    }

    protected function setUp(): void
    {
        // Clear session before each test
        $_SESSION = [];
        require_once POWERBOOK_ROOT . '/pb_inc/csrf.inc.php';
    }

    protected function tearDown(): void
    {
        // Clear session after each test
        $_SESSION = [];
    }
}
