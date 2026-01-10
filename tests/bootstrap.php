<?php
/**
 * PowerBook - PHPUnit Test Bootstrap
 *
 * @license MIT
 */

declare(strict_types=1);

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Start session for CSRF tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define test constants
define('POWERBOOK_TEST_MODE', true);
define('POWERBOOK_ROOT', dirname(__DIR__));
