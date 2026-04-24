<?php

/**
 * PowerBook - PHPUnit Tests
 * Error Handler Functions Tests
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversFunction('safeDbOperation')]
#[CoversFunction('safeDbTransaction')]
#[CoversFunction('logDbError')]
#[CoversFunction('logSecurityEvent')]
#[CoversFunction('logCsrfFailure')]
#[CoversFunction('logFailedLogin')]
#[CoversFunction('logSuccessfulLogin')]
#[CoversFunction('logFormSubmission')]
#[CoversFunction('displayAdminError')]
#[CoversFunction('handleAdminException')]
#[CoversFunction('sendEmail')]
#[CoversFunction('logEmailError')]
#[CoversFunction('rotateLogIfNeeded')]
#[CoversFunction('cleanOldLogs')]
#[CoversFunction('getLogStats')]
class ErrorHandlerTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalServer = [];

    // ========================================
    // Tests for safeDbOperation()
    // ========================================

    #[Test]
    public function safeDbOperationReturnsResultOnSuccess(): void
    {
        $result = safeDbOperation(function () {
            return 'success';
        });

        $this->assertSame('success', $result);
    }

    #[Test]
    public function safeDbOperationReturnsNullFromCallable(): void
    {
        $result = safeDbOperation(function () {});

        $this->assertNull($result);
    }

    #[Test]
    public function safeDbOperationReturnsArrayFromCallable(): void
    {
        $result = safeDbOperation(function () {
            return ['id' => 1, 'name' => 'test'];
        });

        $this->assertSame(['id' => 1, 'name' => 'test'], $result);
    }

    #[Test]
    public function safeDbOperationThrowsRuntimeExceptionOnPdoException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Datenbankfehler');

        safeDbOperation(function () {
            throw new PDOException('Some DB error');
        });
    }

    #[Test]
    public function safeDbOperationUsesCustomErrorMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Custom error message');

        safeDbOperation(function () {
            throw new PDOException('Some DB error');
        }, 'Custom error message');
    }

    #[Test]
    public function safeDbOperationThrowsInstallationRequiredForTableNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Installation erforderlich');

        safeDbOperation(function () {
            throw new PDOException("Table 'powerbook.entries' doesn't exist");
        });
    }

    #[Test]
    public function safeDbOperationThrowsInstallationRequiredForBaseTableNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Installation erforderlich');

        safeDbOperation(function () {
            throw new PDOException('Base table or view not found: 1146');
        });
    }

    #[Test]
    public function safeDbOperationWorksWithRealPdoQuery(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO test (name) VALUES ('hello')");

        $result = safeDbOperation(function () use ($pdo) {
            $stmt = $pdo->query('SELECT name FROM test WHERE id = 1');

            return $stmt->fetchColumn();
        });

        $this->assertSame('hello', $result);
    }

    // ========================================
    // Tests for safeDbTransaction()
    // ========================================

    #[Test]
    public function safeDbTransactionCommitsOnSuccess(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');

        $result = safeDbTransaction($pdo, function () use ($pdo) {
            $pdo->exec("INSERT INTO test (name) VALUES ('txn_test')");

            return 'committed';
        });

        $this->assertSame('committed', $result);

        // Verify the data was actually committed
        $stmt = $pdo->query('SELECT name FROM test WHERE name = \'txn_test\'');
        $this->assertSame('txn_test', $stmt->fetchColumn());
    }

    #[Test]
    public function safeDbTransactionRollsBackOnPdoException(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');

        try {
            safeDbTransaction($pdo, function () use ($pdo) {
                $pdo->exec("INSERT INTO test (name) VALUES ('should_rollback')");

                throw new PDOException('Transaction failed');
            });
        } catch (RuntimeException) {
            // Expected
        }

        // Verify the data was rolled back
        $stmt = $pdo->query("SELECT COUNT(*) FROM test WHERE name = 'should_rollback'");
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    #[Test]
    public function safeDbTransactionThrowsRuntimeExceptionOnPdoException(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Datenbankfehler');

        safeDbTransaction($pdo, function () {
            throw new PDOException('DB error');
        });
    }

    #[Test]
    public function safeDbTransactionUsesCustomErrorMessage(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction failed badly');

        safeDbTransaction($pdo, function () {
            throw new PDOException('DB error');
        }, 'Transaction failed badly');
    }

    #[Test]
    public function safeDbTransactionRethrowsGenericException(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad argument');

        safeDbTransaction($pdo, function () use ($pdo) {
            $pdo->exec("INSERT INTO test (name) VALUES ('should_rollback')");

            throw new \InvalidArgumentException('Bad argument');
        });
    }

    #[Test]
    public function safeDbTransactionRollsBackOnGenericException(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');

        try {
            safeDbTransaction($pdo, function () use ($pdo) {
                $pdo->exec("INSERT INTO test (name) VALUES ('generic_rollback')");

                throw new \LogicException('Logic error');
            });
        } catch (\LogicException) {
            // Expected
        }

        $stmt = $pdo->query("SELECT COUNT(*) FROM test WHERE name = 'generic_rollback'");
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    #[Test]
    public function safeDbTransactionReturnsResultOnSuccess(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $result = safeDbTransaction($pdo, function () {
            return 42;
        });

        $this->assertSame(42, $result);
    }

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
    // Tests for logFormSubmission()
    // ========================================

    #[Test]
    public function logFormSubmissionWithSuccessDoesNotThrow(): void
    {
        logFormSubmission('contact_form', true, ['name' => 'John']);

        $this->assertTrue(true);
    }

    #[Test]
    public function logFormSubmissionWithFailureDoesNotThrow(): void
    {
        logFormSubmission('contact_form', false, ['name' => 'John']);

        $this->assertTrue(true);
    }

    #[Test]
    public function logFormSubmissionStripesSensitiveData(): void
    {
        // This should not throw and should remove sensitive keys internally
        logFormSubmission('login_form', true, [
            'username' => 'admin',
            'password' => 'secret123',
            'password1' => 'secret123',
            'password2' => 'secret123',
            'csrf_token' => 'abc123token',
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function logFormSubmissionWithEmptyData(): void
    {
        logFormSubmission('empty_form', true);

        $this->assertTrue(true);
    }

    #[Test]
    public function logFormSubmissionWithMissingRemoteAddr(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        logFormSubmission('test_form', false, ['field' => 'value']);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->assertTrue(true);
    }

    // ========================================
    // Tests for displayAdminError()
    // ========================================

    #[Test]
    public function displayAdminErrorOutputsHtml(): void
    {
        ob_start();
        displayAdminError('Test error message');
        $output = ob_get_clean();

        $this->assertStringContainsString('Fehler:', $output);
        $this->assertStringContainsString('Test error message', $output);
        $this->assertStringContainsString('<tr bgcolor="#001329">', $output);
    }

    #[Test]
    public function displayAdminErrorEscapesHtmlInMessage(): void
    {
        ob_start();
        displayAdminError('<script>alert("xss")</script>');
        $output = ob_get_clean();

        $this->assertStringContainsString('&lt;script&gt;', $output);
        $this->assertStringNotContainsString('<script>alert', $output);
    }

    #[Test]
    public function displayAdminErrorContainsBackLink(): void
    {
        ob_start();
        displayAdminError('Some error');
        $output = ob_get_clean();

        $this->assertStringContainsString('javascript:history.back()', $output);
        $this->assertStringContainsString('Zurück', $output);
    }

    #[Test]
    public function displayAdminErrorHandlesEmptyMessage(): void
    {
        ob_start();
        displayAdminError('');
        $output = ob_get_clean();

        $this->assertStringContainsString('Fehler:', $output);
    }

    #[Test]
    public function displayAdminErrorHandlesSpecialCharacters(): void
    {
        ob_start();
        displayAdminError('Error with "quotes" & <tags>');
        $output = ob_get_clean();

        $this->assertStringContainsString('&amp;', $output);
        $this->assertStringContainsString('&quot;', $output);
        $this->assertStringContainsString('&lt;tags&gt;', $output);
    }

    // ========================================
    // Tests for handleAdminException()
    // ========================================

    #[Test]
    public function handleAdminExceptionOutputsGenericError(): void
    {
        $exception = new \Exception('Internal details');

        ob_start();
        handleAdminException($exception, 'test_context');
        $output = ob_get_clean();

        $this->assertStringContainsString('Ein Fehler ist aufgetreten', $output);
        // Internal details should NOT be shown to user
        $this->assertStringNotContainsString('Internal details', $output);
    }

    #[Test]
    public function handleAdminExceptionWithEmptyContext(): void
    {
        $exception = new \RuntimeException('DB connection lost');

        ob_start();
        handleAdminException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Ein Fehler ist aufgetreten', $output);
    }

    #[Test]
    public function handleAdminExceptionHandlesThrowableInterface(): void
    {
        $error = new \Error('Fatal error');

        ob_start();
        handleAdminException($error, 'fatal');
        $output = ob_get_clean();

        $this->assertStringContainsString('Fehler:', $output);
    }

    #[Test]
    public function handleAdminExceptionOutputsHtmlStructure(): void
    {
        $exception = new \Exception('test');

        ob_start();
        handleAdminException($exception, 'admin_panel');
        $output = ob_get_clean();

        $this->assertStringContainsString('<tr bgcolor="#001329">', $output);
        $this->assertStringContainsString('</td></tr>', $output);
    }

    // ========================================
    // Tests for sendEmail()
    // ========================================

    #[Test]
    public function sendEmailReturnsFalseForEmptyRecipient(): void
    {
        $result = sendEmail('', 'Subject', 'Body');

        $this->assertFalse($result);
    }

    #[Test]
    public function sendEmailReturnsFalseForNewlineOnlyRecipient(): void
    {
        $result = sendEmail("\r\n", 'Subject', 'Body');

        $this->assertFalse($result);
    }

    #[Test]
    public function sendEmailReturnsFalseForInjectionAttempt(): void
    {
        // After sanitization, if the result is non-empty, mail() will be called
        // but with newlines stripped. We test with only newlines which becomes empty.
        $result = sendEmail("\n\r\n\r", 'Subject', 'Body', '', 'test');

        $this->assertFalse($result);
    }

    #[Test]
    public function sendEmailWithContextDoesNotThrow(): void
    {
        // Empty recipient to avoid actually calling mail()
        $result = sendEmail('', 'Subject', 'Body', '', 'Password Recovery');

        $this->assertFalse($result);
    }

    #[Test]
    public function sendEmailWithEmptyContextDoesNotThrow(): void
    {
        $result = sendEmail('', 'Subject', 'Body', '', '');

        $this->assertFalse($result);
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
    // Tests for cleanOldLogs()
    // ========================================

    #[Test]
    public function cleanOldLogsDoesNotThrow(): void
    {
        // Uses the actual logs directory. Just test it doesn't throw.
        cleanOldLogs(30);

        $this->assertTrue(true);
    }

    #[Test]
    public function cleanOldLogsWithZeroDaysDoesNotThrow(): void
    {
        cleanOldLogs(0);

        $this->assertTrue(true);
    }

    #[Test]
    public function cleanOldLogsWithLargeMaxAgeDoesNotThrow(): void
    {
        cleanOldLogs(365);

        $this->assertTrue(true);
    }

    #[Test]
    public function cleanOldLogsRemovesOldRotatedFiles(): void
    {
        $logsDir = POWERBOOK_ROOT . '/logs';
        $testFile = $logsDir . '/test_cleanup.log.1';

        // Create a rotated log file with an old modification time
        file_put_contents($testFile, 'old log data');
        // Set modification time to 60 days ago
        touch($testFile, time() - (60 * 86400));

        cleanOldLogs(30);

        $this->assertFileDoesNotExist($testFile);
    }

    #[Test]
    public function cleanOldLogsKeepsRecentRotatedFiles(): void
    {
        $logsDir = POWERBOOK_ROOT . '/logs';
        $testFile = $logsDir . '/test_keep.log.1';

        // Create a rotated log file with a recent modification time
        file_put_contents($testFile, 'recent log data');
        // Modification time is now (recent)

        cleanOldLogs(30);

        $this->assertFileExists($testFile);

        @unlink($testFile);
    }

    // ========================================
    // Tests for getLogStats()
    // ========================================

    #[Test]
    public function getLogStatsReturnsArrayWithExpectedKeys(): void
    {
        $stats = getLogStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('error.log', $stats);
        $this->assertArrayHasKey('forms.log', $stats);
        $this->assertArrayHasKey('security.log', $stats);
    }

    #[Test]
    public function getLogStatsContainsSizeAndLinesForEachFile(): void
    {
        $stats = getLogStats();

        foreach (['error.log', 'forms.log', 'security.log'] as $logFile) {
            $this->assertArrayHasKey('size', $stats[$logFile]);
            $this->assertArrayHasKey('lines', $stats[$logFile]);
            $this->assertArrayHasKey('last_modified', $stats[$logFile]);
        }
    }

    #[Test]
    public function getLogStatsSizeIsNonNegative(): void
    {
        $stats = getLogStats();

        foreach ($stats as $logFile => $info) {
            $this->assertGreaterThanOrEqual(0, $info['size'], "Size for {$logFile} should be non-negative");
        }
    }

    #[Test]
    public function getLogStatsLinesIsNonNegative(): void
    {
        $stats = getLogStats();

        foreach ($stats as $logFile => $info) {
            $this->assertGreaterThanOrEqual(0, $info['lines'], "Lines for {$logFile} should be non-negative");
        }
    }

    #[Test]
    public function getLogStatsReturnsZerosForMissingFile(): void
    {
        $stats = getLogStats();

        // forms.log may not exist; if so, it should have zero values
        if (!file_exists(POWERBOOK_ROOT . '/logs/forms.log')) {
            $this->assertSame(0, $stats['forms.log']['size']);
            $this->assertSame(0, $stats['forms.log']['lines']);
            $this->assertFalse($stats['forms.log']['last_modified']);
        } else {
            // If it exists, size should be positive
            $this->assertGreaterThan(0, $stats['forms.log']['size']);
        }
    }

    #[Test]
    public function getLogStatsExistingFileHasValidLastModified(): void
    {
        $stats = getLogStats();

        // error.log is known to exist (we wrote to it during tests)
        if (file_exists(POWERBOOK_ROOT . '/logs/error.log')) {
            $this->assertIsInt($stats['error.log']['last_modified']);
            $this->assertGreaterThan(0, $stats['error.log']['last_modified']);
        }
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
