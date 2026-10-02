<?php

/**
 * PowerBook - PHPUnit Tests
 * Error Handler Functions Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversFunction('pb_log_dir')]
#[CoversFunction('pb_log_write')]
#[CoversFunction('pb_log_shorten_name')]
#[CoversFunction('logDbError')]
#[CoversFunction('logSecurityEvent')]
#[CoversFunction('logCsrfFailure')]
#[CoversFunction('logFailedLogin')]
#[CoversFunction('logSuccessfulLogin')]
#[CoversFunction('logEmailError')]
#[CoversFunction('rotateLogIfNeeded')]
class ErrorHandlerTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalServer = [];

    // ========================================
    // Tests for logDbError()
    // ========================================

    #[Test]
    public function logDbErrorDoesNotThrow(): void
    {
        logDbError('Test database error message');

        // If we reach here, no exception was thrown
        $this->assertTrue(true);
    }

    #[Test]
    public function logDbErrorHandlesEmptyMessage(): void
    {
        logDbError('');

        $this->assertTrue(true);
    }

    #[Test]
    public function logDbErrorHandlesSpecialCharacters(): void
    {
        logDbError('Error with "quotes" and <tags> & ampersands');

        $this->assertTrue(true);
    }

    #[Test]
    public function logDbErrorHandlesLongMessage(): void
    {
        logDbError(str_repeat('A', 10000));

        $this->assertTrue(true);
    }

    #[Test]
    public function logDbErrorHandlesUnicodeMessage(): void
    {
        logDbError('Datenbankfehler: Tabelle nicht gefunden');

        $this->assertTrue(true);
    }

    // ========================================
    // Tests for logSecurityEvent()
    // ========================================

    #[Test]
    public function logSecurityEventDoesNotThrow(): void
    {
        logSecurityEvent('TEST_EVENT', ['key' => 'value']);

        $this->assertTrue(true);
    }

    #[Test]
    public function logSecurityEventWithEmptyContext(): void
    {
        logSecurityEvent('TEST_EVENT');

        $this->assertTrue(true);
    }

    #[Test]
    public function logSecurityEventWithComplexContext(): void
    {
        logSecurityEvent('COMPLEX_EVENT', [
            'username' => 'admin',
            'action' => 'delete',
            'target_id' => 123,
            'nested' => ['a' => 'b'],
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function logSecurityEventWithMissingServerVars(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        logSecurityEvent('NO_SERVER_VARS', ['test' => true]);

        // Restore for tearDown
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test Agent';

        $this->assertTrue(true);
    }

    #[Test]
    public function logSecurityEventWithLongUserAgent(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = str_repeat('X', 500);

        logSecurityEvent('LONG_UA_EVENT');

        $this->assertTrue(true);
    }

    // ========================================
    // Tests for logCsrfFailure()
    // ========================================

    #[Test]
    public function logCsrfFailureDoesNotThrow(): void
    {
        logCsrfFailure('login_form');

        $this->assertTrue(true);
    }

    #[Test]
    public function logCsrfFailureWithEmptyFormName(): void
    {
        logCsrfFailure('');

        $this->assertTrue(true);
    }

    #[Test]
    public function logCsrfFailureWithMissingReferer(): void
    {
        unset($_SERVER['HTTP_REFERER']);

        logCsrfFailure('test_form');

        $_SERVER['HTTP_REFERER'] = 'http://localhost/test';

        $this->assertTrue(true);
    }

    #[Test]
    public function logCsrfFailureWithSpecialFormName(): void
    {
        logCsrfFailure('form<with>special&chars');

        $this->assertTrue(true);
    }

    // ========================================
    // Tests for logFailedLogin()
    // ========================================

    #[Test]
    public function logFailedLoginDoesNotThrow(): void
    {
        logFailedLogin('admin');

        $this->assertTrue(true);
    }

    #[Test]
    public function logFailedLoginWithEmptyUsername(): void
    {
        logFailedLogin('');

        $this->assertTrue(true);
    }

    #[Test]
    public function logFailedLoginWithSpecialCharsUsername(): void
    {
        logFailedLogin("admin'; DROP TABLE users; --");

        $this->assertTrue(true);
    }

    // ========================================
    // Tests for logSuccessfulLogin()
    // ========================================

    #[Test]
    public function logSuccessfulLoginDoesNotThrow(): void
    {
        logSuccessfulLogin('admin');

        $this->assertTrue(true);
    }

    #[Test]
    public function logSuccessfulLoginWithEmptyUsername(): void
    {
        logSuccessfulLogin('');

        $this->assertTrue(true);
    }

    #[Test]
    public function logSuccessfulLoginWithSpecialCharsUsername(): void
    {
        logSuccessfulLogin('user@domain.com');

        $this->assertTrue(true);
    }

    // ========================================
    // Tests for logEmailError()
    // ========================================

    #[Test]
    public function logEmailErrorDoesNotThrow(): void
    {
        logEmailError('Failed to send email', 'Password Recovery');

        $this->assertTrue(true);
    }

    #[Test]
    public function logEmailErrorWithEmptyContext(): void
    {
        logEmailError('Failed to send email');

        $this->assertTrue(true);
    }

    #[Test]
    public function logEmailErrorWithEmptyMessage(): void
    {
        logEmailError('', 'admin');

        $this->assertTrue(true);
    }

    #[Test]
    public function logEmailErrorWithSpecialCharacters(): void
    {
        logEmailError('Error: "connection" <refused> & timeout', 'SMTP');

        $this->assertTrue(true);
    }

    // ========================================
    // Tests for rotateLogIfNeeded()
    // ========================================

    #[Test]
    public function rotateLogIfNeededDoesNothingForNonexistentFile(): void
    {
        $tempFile = sys_get_temp_dir() . '/powerbook_test_nonexistent_' . uniqid() . '.log';

        rotateLogIfNeeded($tempFile);

        $this->assertFileDoesNotExist($tempFile);
    }

    #[Test]
    public function rotateLogIfNeededDoesNothingForSmallFile(): void
    {
        $tempFile = sys_get_temp_dir() . '/powerbook_test_small_' . uniqid() . '.log';
        file_put_contents($tempFile, 'Small log content');

        rotateLogIfNeeded($tempFile);

        // File should still exist and not be rotated
        $this->assertFileExists($tempFile);
        $this->assertSame('Small log content', file_get_contents($tempFile));

        @unlink($tempFile);
    }

    #[Test]
    public function rotateLogIfNeededRotatesLargeFile(): void
    {
        $tempFile = sys_get_temp_dir() . '/powerbook_test_large_' . uniqid() . '.log';
        // Create a file larger than the threshold (use small threshold for test)
        file_put_contents($tempFile, str_repeat('X', 200));

        rotateLogIfNeeded($tempFile, 100, 3);

        // Original file should be renamed to .1
        $this->assertFileDoesNotExist($tempFile);
        $this->assertFileExists($tempFile . '.1');

        @unlink($tempFile . '.1');
    }

    #[Test]
    public function rotateLogIfNeededShiftsExistingRotatedFiles(): void
    {
        $tempFile = sys_get_temp_dir() . '/powerbook_test_shift_' . uniqid() . '.log';
        file_put_contents($tempFile, str_repeat('A', 200));
        file_put_contents($tempFile . '.1', 'old_rotation_1');
        file_put_contents($tempFile . '.2', 'old_rotation_2');

        rotateLogIfNeeded($tempFile, 100, 5);

        // .1 should now be the freshly rotated file
        $this->assertFileExists($tempFile . '.1');
        // Old .1 should have moved to .2
        $this->assertFileExists($tempFile . '.2');
        // Old .2 should have moved to .3
        $this->assertFileExists($tempFile . '.3');
        $this->assertSame('old_rotation_1', file_get_contents($tempFile . '.2'));
        $this->assertSame('old_rotation_2', file_get_contents($tempFile . '.3'));

        @unlink($tempFile);
        @unlink($tempFile . '.1');
        @unlink($tempFile . '.2');
        @unlink($tempFile . '.3');
    }

    #[Test]
    public function rotateLogIfNeededRespectsKeepFilesLimit(): void
    {
        $tempFile = sys_get_temp_dir() . '/powerbook_test_keep_' . uniqid() . '.log';
        file_put_contents($tempFile, str_repeat('B', 200));

        // Create existing rotated files up to the limit
        for ($i = 1; $i <= 3; $i++) {
            file_put_contents($tempFile . '.' . $i, "rotation_{$i}");
        }

        rotateLogIfNeeded($tempFile, 100, 3);

        // After rotation with keepFiles=3, only .1 through .3 should exist
        $this->assertFileExists($tempFile . '.1');
        $this->assertFileExists($tempFile . '.2');
        $this->assertFileExists($tempFile . '.3');
        // .4 should not exist (exceeds keepFiles)
        $this->assertFileDoesNotExist($tempFile . '.4');

        @unlink($tempFile);
        for ($i = 1; $i <= 4; $i++) {
            @unlink($tempFile . '.' . $i);
        }
    }

    #[Test]
    public function rotateLogIfNeededWithExactThresholdDoesRotate(): void
    {
        $tempFile = sys_get_temp_dir() . '/powerbook_test_exact_' . uniqid() . '.log';
        // File size exactly at maxSize triggers rotation (>= check: $size < $maxSize is false)
        file_put_contents($tempFile, str_repeat('C', 100));

        rotateLogIfNeeded($tempFile, 100, 3);

        $this->assertFileDoesNotExist($tempFile);
        $this->assertFileExists($tempFile . '.1');

        @unlink($tempFile . '.1');
    }

    #[Test]
    public function rotateLogIfNeededDoesNotRotateBelowThreshold(): void
    {
        $tempFile = sys_get_temp_dir() . '/powerbook_test_below_' . uniqid() . '.log';
        // File size below maxSize should NOT trigger rotation
        file_put_contents($tempFile, str_repeat('C', 99));

        rotateLogIfNeeded($tempFile, 100, 3);

        $this->assertFileExists($tempFile);
        $this->assertFileDoesNotExist($tempFile . '.1');

        @unlink($tempFile);
    }

    // ========================================
    // Tests for pb_log_write() / pb_log_shorten_name() (A31)
    // ========================================

    #[Test]
    public function pbLogWriteAppendsLineAndReplacesNewlines(): void
    {
        $file = 'test_write_' . uniqid() . '.log';
        $path = pb_log_dir() . '/' . $file;

        pb_log_write($file, "Zeile eins\nGefälschte Zeile\r\nEnde");

        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertSame("Zeile eins Gefälschte Zeile Ende\n", $content);

        @unlink($path);
    }

    #[Test]
    public function pbLogWriteStripsDirectoryFromFileName(): void
    {
        $file = 'test_base_' . uniqid() . '.log';

        pb_log_write('../' . $file, 'x');

        $this->assertFileExists(pb_log_dir() . '/' . $file);
        $this->assertFileDoesNotExist(dirname(pb_log_dir()) . '/' . $file);

        @unlink(pb_log_dir() . '/' . $file);
    }

    #[Test]
    public function pbLogWriteRotatesEveryLog(): void
    {
        $file = 'test_rotate_' . uniqid() . '.log';
        $path = pb_log_dir() . '/' . $file;
        file_put_contents($path, str_repeat('X', 5242880));

        pb_log_write($file, 'neu');

        $this->assertFileExists($path . '.1');
        $this->assertSame("neu\n", file_get_contents($path));

        @unlink($path);
        @unlink($path . '.1');
    }

    #[Test]
    public function pbLogShortenNameKeepsThreeCharactersAndHash(): void
    {
        $short = pb_log_shorten_name('Moewenblick2026');

        $this->assertStringStartsWith('Moe…', $short);
        $this->assertStringNotContainsString('wenblick', $short);
        $this->assertMatchesRegularExpression('/ #[a-f0-9]{8}$/', $short);
    }

    #[Test]
    public function pbLogShortenNameIsStableAndCaseInsensitive(): void
    {
        $this->assertSame(
            substr(pb_log_shorten_name('Anke'), -9),
            substr(pb_log_shorten_name('ANKE'), -9)
        );
        $this->assertSame('', pb_log_shorten_name('   '));
        $this->assertStringStartsWith('Ole #', pb_log_shorten_name('Ole'));
    }

    #[Test]
    public function logFailedLoginDoesNotWriteFullName(): void
    {
        $path = pb_log_dir() . '/security.log';
        $before = is_file($path) ? (int) filesize($path) : 0;

        logFailedLogin('GeheimesPasswort123');

        clearstatcache();
        $added = (string) file_get_contents($path, false, null, $before);
        $this->assertStringContainsString('LOGIN_FAILED', $added);
        $this->assertStringNotContainsString('GeheimesPasswort123', $added);
        $this->assertStringContainsString('Geh…', $added);
    }

    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/database.inc.php';
        require_once POWERBOOK_ROOT . '/pb_inc/error-handler.inc.php';

        // Save original $_SERVER values
        $this->originalServer = [
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? null,
        ];

        // Set default test values
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test Agent';
        $_SERVER['HTTP_REFERER'] = 'http://localhost/test';
    }

    protected function tearDown(): void
    {
        // Restore original $_SERVER values
        foreach ($this->originalServer as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
    }
}
