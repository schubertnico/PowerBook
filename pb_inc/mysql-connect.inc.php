<?php
/**
 * PowerBook - PHP Guestbook System
 * Database Connection (Legacy Compatibility Layer)
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Include the new PDO database handler
require_once __DIR__ . '/database.inc.php';

// Get PDO connection for use in legacy code
// The old $sql_conn variable is replaced by the getDatabase() function
try {
    $pdo = getDatabase();
} catch (RuntimeException $e) {
    die($e->getMessage());
}
