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

// Set up SQLite test database before any file tries to connect to MySQL
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Create all required tables
$pdo->exec('CREATE TABLE pb_config (
    id INTEGER PRIMARY KEY,
    "release" TEXT DEFAULT "R",
    send_email TEXT DEFAULT "N",
    email TEXT DEFAULT "admin@test.com",
    date TEXT DEFAULT "d.m.Y",
    time TEXT DEFAULT "H:i",
    spam_check INTEGER DEFAULT 60,
    color TEXT DEFAULT "#FF0000",
    show_entries INTEGER DEFAULT 10,
    guestbook_name TEXT DEFAULT "pbook.php",
    admin_url TEXT DEFAULT "",
    text_format TEXT DEFAULT "Y",
    icons TEXT DEFAULT "Y",
    smilies TEXT DEFAULT "Y",
    icq TEXT DEFAULT "N",
    pages TEXT DEFAULT "D",
    use_thanks TEXT DEFAULT "N",
    language TEXT DEFAULT "D",
    design TEXT DEFAULT "(#ICON#)(#DATE#)(#TIME#)(#EMAIL_NAME#)(#TEXT#)(#URL#)(#ICQ#)",
    thanks_title TEXT DEFAULT "",
    thanks TEXT DEFAULT "",
    statements TEXT DEFAULT "Y"
)');

$pdo->exec('CREATE TABLE pb_admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    password TEXT NOT NULL,
    config TEXT DEFAULT "N",
    admins TEXT DEFAULT "N",
    entries TEXT DEFAULT "N",
    "release" TEXT DEFAULT "N"
)');

$pdo->exec('CREATE TABLE pb_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT DEFAULT "",
    text TEXT NOT NULL,
    date INTEGER DEFAULT 0,
    homepage TEXT DEFAULT "",
    icq TEXT DEFAULT "",
    ip TEXT DEFAULT "",
    status TEXT DEFAULT "R",
    icon TEXT DEFAULT "",
    smilies TEXT DEFAULT "N",
    statement TEXT DEFAULT "",
    statement_by TEXT DEFAULT ""
)');

// Insert default config
$pdo->exec('INSERT INTO pb_config (id) VALUES (1)');

// Insert test admin
$pdo->exec("INSERT INTO pb_admins (id, name, email, password, config, admins, entries, \"release\")
    VALUES (1, 'SuperAdmin', 'admin@test.com', '" . password_hash('test123', PASSWORD_DEFAULT) . "', 'Y', 'Y', 'Y', 'Y')");

// Set table name variables
$pb_config = 'pb_config';
$pb_admin = 'pb_admins';
$pb_entries = 'pb_entries';

// Set config variables (same as config.inc.php would set)
$config_sql_server = 'localhost';
$config_sql_user = 'test';
$config_sql_password = 'test';
$config_sql_database = 'test';

// Load core function files
require_once POWERBOOK_ROOT . '/pb_inc/database.inc.php';
require_once POWERBOOK_ROOT . '/pb_inc/csrf.inc.php';
require_once POWERBOOK_ROOT . '/pb_inc/error-handler.inc.php';
require_once POWERBOOK_ROOT . '/pb_inc/validation.inc.php';
