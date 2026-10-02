<?php

/**
 * PowerBook - PHP Guestbook System
 * Configuration Loader
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/csrf.inc.php';

// Ohne date.timezone in der php.ini rechnet PHP in UTC – Uhrzeiten im
// Gästebuch lägen dann zwei Stunden daneben.
if (ini_get('date.timezone') === '' || ini_get('date.timezone') === false) {
    date_default_timezone_set('Europe/Berlin');
}

// Start session for CSRF and authentication (HttpOnly, SameSite, Secure bei HTTPS)
if (function_exists('pb_session_start')) {
    pb_session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/mysql-connect.inc.php';
require_once __DIR__ . '/version.inc.php';
require_once __DIR__ . '/mail.inc.php';

// Vorgaben, falls die Konfigurationstabelle fehlt oder eine Spalte (noch)
// nicht kennt – etwa vor update.php bei einer älteren Datenbank.
$pbConfigDefaults = [
    'title' => 'Gästebuch',
    'release' => 'U',
    'send_email' => 'N',
    'email' => '',
    'mail_from' => '',
    'date' => 'd.m.Y',
    'time' => 'H:i',
    'spam_check' => 30,
    'color' => '#FF0000',
    'show_entries' => 10,
    'guestbook_name' => 'pbook.php',
    'admin_url' => '',
    'text_format' => 'Y',
    'icons' => 'Y',
    'smilies' => 'Y',
    'pages' => 'D',
    'use_thanks' => 'N',
    'language' => 'ger1',
    'design' => '',
    'thanks_title' => '',
    'thanks' => '',
    'statements' => 'Y',
];

// Load configuration from database
try {
    $stmt = $pdo->query("SELECT * FROM {$pb_config} LIMIT 1");
    $configRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    // Configuration table might not exist yet (during installation)
    $configRow = [];
}

$configRow = array_merge($pbConfigDefaults, array_filter($configRow, static fn ($value) => $value !== null));

$config_title = (string) $configRow['title'];
$config_release = (string) $configRow['release'];
$config_send_email = (string) $configRow['send_email'];
$config_email = (string) $configRow['email'];
$config_mail_from = (string) $configRow['mail_from'];
$config_date = (string) $configRow['date'];
$config_time = (string) $configRow['time'];
$config_spam_check = (int) $configRow['spam_check'];
$config_color = (string) $configRow['color'];
$config_show_entries = max(1, (int) $configRow['show_entries']);
$config_guestbook_name = (string) $configRow['guestbook_name'];
$config_admin_url = (string) $configRow['admin_url'];
$config_text_format = (string) $configRow['text_format'];
$config_icons = (string) $configRow['icons'];
$config_smilies = (string) $configRow['smilies'];
$config_pages = (string) $configRow['pages'];
$config_use_thanks = (string) $configRow['use_thanks'];
$config_language = (string) $configRow['language'];
$config_design = (string) $configRow['design'];
$config_thanks_title = (string) $configRow['thanks_title'];
$config_thanks = (string) $configRow['thanks'];
$config_statements = (string) $configRow['statements'];
