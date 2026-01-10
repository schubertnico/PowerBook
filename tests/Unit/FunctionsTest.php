<?php
/**
 * PowerBook - PHPUnit Tests
 * Helper Functions Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversFunction('germandate')]
#[CoversFunction('formatText')]
#[CoversFunction('getVisitorIp')]
class FunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        // functions.inc.php is autoloaded via composer
        // Reset globals for formatText tests
        $GLOBALS['config_text_format'] = 'N';
        $GLOBALS['config_smilies'] = 'N';
    }

    protected function tearDown(): void
    {
        // Reset globals
        $GLOBALS['config_text_format'] = 'N';
        $GLOBALS['config_smilies'] = 'N';
        unset($_SERVER['REMOTE_ADDR']);
    }

    // ========================================
    // Tests for germandate() function
    // ========================================

    #[Test]
    public function germandateConvertsMonday(): void
    {
        $this->assertSame('Montag', germandate('Monday'));
    }

    #[Test]
    public function germandateConvertsTuesday(): void
    {
        $this->assertSame('Dienstag', germandate('Tuesday'));
    }

    #[Test]
    public function germandateConvertsWednesday(): void
    {
        $this->assertSame('Mittwoch', germandate('Wednesday'));
    }

    #[Test]
    public function germandateConvertsThursday(): void
    {
        $this->assertSame('Donnerstag', germandate('Thursday'));
    }

    #[Test]
    public function germandateConvertsFriday(): void
    {
        $this->assertSame('Freitag', germandate('Friday'));
    }

    #[Test]
    public function germandateConvertsSaturday(): void
    {
        $this->assertSame('Samstag', germandate('Saturday'));
    }

    #[Test]
    public function germandateConvertsSunday(): void
    {
        $this->assertSame('Sonntag', germandate('Sunday'));
    }

    #[Test]
    public function germandateConvertsJanuary(): void
    {
        $this->assertSame('Januar', germandate('January'));
    }

    #[Test]
    public function germandateConvertsMarch(): void
    {
        $this->assertSame('März', germandate('March'));
    }

    #[Test]
    public function germandateConvertsOctober(): void
    {
        $this->assertSame('Oktober', germandate('October'));
    }

    #[Test]
    public function germandateConvertsDecember(): void
    {
        $this->assertSame('Dezember', germandate('December'));
    }

    #[Test]
    public function germandateConvertsAbbreviatedMon(): void
    {
        $this->assertSame('Mo', germandate('Mon'));
    }

    #[Test]
    public function germandateConvertsAbbreviatedOct(): void
    {
        $this->assertSame('Okt', germandate('Oct'));
    }

    #[Test]
    public function germandateConvertsAbbreviatedDec(): void
    {
        $this->assertSame('Dez', germandate('Dec'));
    }

    #[Test]
    public function germandateHandlesMixedCase(): void
    {
        $this->assertSame('Montag', germandate('MONDAY'));
    }

    #[Test]
    public function germandatePreservesUnknownText(): void
    {
        $this->assertSame('hello world', germandate('Hello World'));
    }

    // ========================================
    // Tests for formatText() function
    // ========================================

    #[Test]
    public function formatTextConvertsNewlinesToBr(): void
    {
        $result = formatText("Line 1\nLine 2", false);
        $this->assertStringContainsString('<br>', $result);
    }

    #[Test]
    public function formatTextWithBBCodeDisabled(): void
    {
        $GLOBALS['config_text_format'] = 'N';
        $result = formatText('[b]bold[/b]', false);
        $this->assertStringContainsString('[b]', $result);
        $this->assertStringNotContainsString('<b>', $result);
    }

    #[Test]
    public function formatTextBBCodeBold(): void
    {
        $GLOBALS['config_text_format'] = 'Y';
        $result = formatText('[b]bold text[/b]', false);
        $this->assertStringContainsString('<b>bold text</b>', $result);
    }

    #[Test]
    public function formatTextBBCodeItalic(): void
    {
        $GLOBALS['config_text_format'] = 'Y';
        $result = formatText('[i]italic text[/i]', false);
        $this->assertStringContainsString('<i>italic text</i>', $result);
    }

    #[Test]
    public function formatTextBBCodeUnderline(): void
    {
        $GLOBALS['config_text_format'] = 'Y';
        $result = formatText('[u]underline text[/u]', false);
        $this->assertStringContainsString('<u>underline text</u>', $result);
    }

    #[Test]
    public function formatTextBBCodeSmall(): void
    {
        $GLOBALS['config_text_format'] = 'Y';
        $result = formatText('[small]small text[/small]', false);
        $this->assertStringContainsString('<small>small text</small>', $result);
    }

    #[Test]
    public function formatTextAutoLinksHttpUrls(): void
    {
        $GLOBALS['config_text_format'] = 'Y';
        $result = formatText('Visit https://example.com today', false);
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('target="_blank"', $result);
        $this->assertStringContainsString('rel="noopener noreferrer"', $result);
    }

    #[Test]
    public function formatTextAutoLinksFtpUrls(): void
    {
        $GLOBALS['config_text_format'] = 'Y';
        $result = formatText('Download from ftp://files.example.com/file.zip here', false);
        $this->assertStringContainsString('href="ftp://files.example.com/file.zip"', $result);
    }

    #[Test]
    public function formatTextSmiliesDisabledByDefault(): void
    {
        $GLOBALS['config_smilies'] = 'N';
        $result = formatText(':)', true);
        $this->assertStringNotContainsString('happy1.gif', $result);
        $this->assertStringContainsString(':)', $result);
    }

    #[Test]
    public function formatTextSmiliesWhenEnabled(): void
    {
        $GLOBALS['config_smilies'] = 'Y';
        $result = formatText(':)', true);
        $this->assertStringContainsString('happy1.gif', $result);
    }

    #[Test]
    public function formatTextSmiliesSadFace(): void
    {
        $GLOBALS['config_smilies'] = 'Y';
        $result = formatText(':(', true);
        $this->assertStringContainsString('sad2.gif', $result);
    }

    #[Test]
    public function formatTextSmiliesWink(): void
    {
        $GLOBALS['config_smilies'] = 'Y';
        $result = formatText(';)', true);
        $this->assertStringContainsString('happy3.gif', $result);
    }

    #[Test]
    public function formatTextSmiliesDisabledByParameter(): void
    {
        $GLOBALS['config_smilies'] = 'Y';
        $result = formatText(':)', false);
        $this->assertStringNotContainsString('happy1.gif', $result);
    }

    // ========================================
    // Tests for getVisitorIp() function
    // ========================================

    #[Test]
    public function getVisitorIpReturnsServerRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $this->assertSame('192.168.1.100', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpReturnsDefaultForMissingAddr(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $this->assertSame('0.0.0.0', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpReturnsDefaultForInvalidIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'invalid-ip-address';
        $this->assertSame('0.0.0.0', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpHandlesIpv6(): void
    {
        $_SERVER['REMOTE_ADDR'] = '::1';
        $this->assertSame('::1', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpHandlesFullIpv6(): void
    {
        $_SERVER['REMOTE_ADDR'] = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        $this->assertSame('2001:0db8:85a3:0000:0000:8a2e:0370:7334', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpHandlesLocalhost(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertSame('127.0.0.1', getVisitorIp());
    }

    #[Test]
    public function getVisitorIpRejectsEmptyString(): void
    {
        $_SERVER['REMOTE_ADDR'] = '';
        $this->assertSame('0.0.0.0', getVisitorIp());
    }
}
