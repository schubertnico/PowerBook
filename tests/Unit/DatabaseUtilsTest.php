<?php

/**
 * PowerBook - PHPUnit Tests
 * Database Utility Functions Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversFunction('e')]
#[CoversFunction('sanitizeEmailHeader')]
#[CoversFunction('verifyAndMigratePassword')]
class DatabaseUtilsTest extends TestCase
{
    // ========================================
    // Tests for e() function
    // ========================================

    #[Test]
    public function eEscapesHtmlSpecialChars(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', e('<script>alert("xss")</script>'));
    }

    #[Test]
    public function eEscapesAmpersand(): void
    {
        $this->assertSame('foo &amp; bar', e('foo & bar'));
    }

    #[Test]
    public function eEscapesDoubleQuotes(): void
    {
        $this->assertSame('&quot;quoted&quot;', e('"quoted"'));
    }

    #[Test]
    public function eEscapesSingleQuotes(): void
    {
        $this->assertSame('&#039;single&#039;', e("'single'"));
    }

    #[Test]
    public function eReturnsEmptyStringForNull(): void
    {
        $this->assertSame('', e(null));
    }

    #[Test]
    public function eConvertsIntegerToString(): void
    {
        $this->assertSame('123', e(123));
    }

    #[Test]
    public function eConvertsFloatToString(): void
    {
        $this->assertSame('1.5', e(1.5));
    }

    #[Test]
    public function eReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', e(''));
    }

    #[Test]
    public function eHandlesUnicodeCorrectly(): void
    {
        $this->assertSame('Umlaute: äöü', e('Umlaute: äöü'));
    }

    #[Test]
    public function ePreservesGermanUmlauts(): void
    {
        $this->assertSame('Grüße aus der Möwenstraße', e('Grüße aus der Möwenstraße'));
    }

    // ========================================
    // Tests for sanitizeEmailHeader() function
    // ========================================

    #[Test]
    public function sanitizeEmailHeaderRemovesNewlines(): void
    {
        $this->assertSame('test@example.com', sanitizeEmailHeader("test@example.com\n"));
    }

    #[Test]
    public function sanitizeEmailHeaderRemovesCarriageReturn(): void
    {
        $this->assertSame('test@example.com', sanitizeEmailHeader("test@example.com\r"));
    }

    #[Test]
    public function sanitizeEmailHeaderRemovesCrLf(): void
    {
        $this->assertSame('test@example.com', sanitizeEmailHeader("test@example.com\r\n"));
    }

    #[Test]
    public function sanitizeEmailHeaderPreventsHeaderInjection(): void
    {
        $malicious = "victim@example.com\r\nBcc: attacker@evil.com";
        $result = sanitizeEmailHeader($malicious);

        // The function removes newlines, preventing header injection
        // The Bcc: text remains but without newlines it's not a valid header
        $this->assertStringNotContainsString("\r", $result);
        $this->assertStringNotContainsString("\n", $result);
        // Result should be the concatenated string without newlines
        $this->assertSame('victim@example.comBcc: attacker@evil.com', $result);
    }

    #[Test]
    public function sanitizeEmailHeaderPreservesValidEmail(): void
    {
        $this->assertSame('valid@email.com', sanitizeEmailHeader('valid@email.com'));
    }

    #[Test]
    public function sanitizeEmailHeaderHandlesEmptyString(): void
    {
        $this->assertSame('', sanitizeEmailHeader(''));
    }

    #[Test]
    public function sanitizeEmailHeaderRemovesMultipleNewlines(): void
    {
        $input = "test\n\n\r\r\n@example.com";
        $result = sanitizeEmailHeader($input);

        $this->assertStringNotContainsString("\n", $result);
        $this->assertStringNotContainsString("\r", $result);
    }

    // ========================================
    // Tests for verifyAndMigratePassword()
    // ========================================

    #[Test]
    public function verifyAndMigratePasswordAcceptsHashes(): void
    {
        $hash = password_hash('Moewenblick2026', PASSWORD_DEFAULT);

        $this->assertTrue(verifyAndMigratePassword('Moewenblick2026', $hash, 1));
        $this->assertFalse(verifyAndMigratePassword('falsch', $hash, 1));
        $this->assertFalse(verifyAndMigratePassword('', $hash, 1));
        $this->assertFalse(verifyAndMigratePassword('', '', 1));
    }

    #[Test]
    public function verifyAndMigratePasswordConvertsBase64InTheConfiguredTable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE gb_admins_test (id INTEGER PRIMARY KEY, password TEXT NOT NULL)');
        $pdo->exec("INSERT INTO gb_admins_test (id, password) VALUES (7, '" . base64_encode('powerbook') . "')");
        $previous = $GLOBALS['pb_admin'] ?? null;
        $previousPdo = $GLOBALS['pdo'] ?? null;
        $GLOBALS['pb_admin'] = 'gb_admins_test';
        $GLOBALS['pdo'] = $pdo;

        try {
            $this->assertFalse(verifyAndMigratePassword('PowerBook', base64_encode('powerbook'), 7));
            $this->assertTrue(verifyAndMigratePassword('powerbook', base64_encode('powerbook'), 7));
            $stored = (string) $pdo->query('SELECT password FROM gb_admins_test WHERE id = 7')->fetchColumn();
            $this->assertStringStartsWith('$2y$', $stored);
            $this->assertTrue(password_verify('powerbook', $stored));
        } finally {
            $GLOBALS['pb_admin'] = $previous;
            $GLOBALS['pdo'] = $previousPdo;
        }
    }

    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/database.inc.php';
    }
}
