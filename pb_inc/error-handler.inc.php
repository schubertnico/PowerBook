<?php
/**
 * PowerBook - PHP Guestbook System
 * Error Handler Functions
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

/**
 * Execute a database operation safely with error handling
 *
 * @param callable $operation The database operation to execute
 * @param string $errorMessage User-friendly error message on failure
 * @return mixed The result of the operation
 * @throws RuntimeException If the operation fails and should be re-thrown
 */
function safeDbOperation(callable $operation, string $errorMessage = 'Datenbankfehler'): mixed
{
    try {
        return $operation();
    } catch (PDOException $e) {
        logDbError($e->getMessage());

        // Check if it's a "table not found" error (installation required)
        if (str_contains($e->getMessage(), 'doesn\'t exist') ||
            str_contains($e->getMessage(), 'Base table or view not found')) {
            throw new RuntimeException('Installation erforderlich: Datenbanktabellen nicht gefunden.');
        }

        throw new RuntimeException($errorMessage);
    }
}

/**
 * Execute a database operation within a transaction
 *
 * @param PDO $pdo The PDO connection
 * @param callable $operation The database operation to execute
 * @param string $errorMessage User-friendly error message on failure
 * @return mixed The result of the operation
 */
function safeDbTransaction(PDO $pdo, callable $operation, string $errorMessage = 'Datenbankfehler'): mixed
{
    try {
        $pdo->beginTransaction();
        $result = $operation();
        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDbError($e->getMessage());
        throw new RuntimeException($errorMessage);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Log a database error to the error log
 */
function logDbError(string $message): void
{
    $logEntry = sprintf(
        "[%s] PowerBook DB Error: %s\n",
        date('Y-m-d H:i:s'),
        $message
    );
    error_log($logEntry, 3, dirname(__DIR__) . '/logs/error.log');
}

/**
 * Log a security-related event
 */
function logSecurityEvent(string $event, array $context = []): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = sprintf(
        "[%s] Security: %s | IP: %s | Context: %s\n",
        date('Y-m-d H:i:s'),
        $event,
        $ip,
        json_encode($context)
    );
    error_log($logEntry, 3, dirname(__DIR__) . '/logs/error.log');
}

/**
 * Log a form submission event
 */
function logFormSubmission(string $formName, bool $success, array $data = []): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Remove sensitive data
    unset($data['password'], $data['password1'], $data['password2'], $data['csrf_token']);

    $logEntry = sprintf(
        "[%s] Form: %s | Success: %s | IP: %s | Data: %s\n",
        date('Y-m-d H:i:s'),
        $formName,
        $success ? 'YES' : 'NO',
        $ip,
        json_encode($data)
    );
    error_log($logEntry, 3, dirname(__DIR__) . '/logs/forms.log');
}

/**
 * Display a user-friendly error message in admin style
 */
function displayAdminError(string $message): void
{
    echo '<tr bgcolor="#001329"><td>';
    echo '<div align="center" style="padding: 20px;">';
    echo '<p style="color: #FF6666;"><b>Fehler:</b> ' . e($message) . '</p>';
    echo '<p><a href="javascript:history.back()">Zurück</a></p>';
    echo '</div>';
    echo '</td></tr>';
}

/**
 * Handle an exception gracefully in admin context
 */
function handleAdminException(Throwable $e, string $context = ''): void
{
    logDbError($context . ': ' . $e->getMessage());
    displayAdminError('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
}

/**
 * Send email with logging
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body
 * @param string $headers Email headers
 * @param string $context Context for logging (e.g., 'Password Recovery', 'Admin Added')
 * @return bool True if email was sent successfully
 */
function sendEmail(string $to, string $subject, string $body, string $headers = '', string $context = ''): bool
{
    // Sanitize recipient
    $to = sanitizeEmailHeader($to);

    if (empty($to)) {
        logEmailError('Empty recipient', $context);
        return false;
    }

    $result = @mail($to, $subject, $body, $headers);

    if (!$result) {
        logEmailError("Failed to send to: {$to}", $context);
    }

    return $result;
}

/**
 * Log email sending errors
 */
function logEmailError(string $message, string $context = ''): void
{
    $logEntry = sprintf(
        "[%s] Email Error%s: %s\n",
        date('Y-m-d H:i:s'),
        !empty($context) ? " ({$context})" : '',
        $message
    );
    error_log($logEntry, 3, dirname(__DIR__) . '/logs/error.log');
}
