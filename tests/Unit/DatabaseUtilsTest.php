<?php
/**
 * PowerBook - PHPUnit Tests
 * Database Utility Functions Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversFunction('e')]
#[CoversFunction('sanitizeEmailHeader')]
class DatabaseUtilsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/database.inc.php';
    }

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
        $this->assertSame('Umlaute: aeoeue', e('Umlaute: aeoeue'));
    }

    #[Test]
    public function ePreservesGermanUmlauts(): void
    {
        $this->assertSame('Gruesse', e('Gruesse'));
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
}
