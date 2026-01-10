<?php
/**
 * PowerBook - PHP Guestbook System
 * Configuration Loader
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Start session for CSRF and authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/mysql.inc.php';
require_once __DIR__ . '/mysql-connect.inc.php';
require_once __DIR__ . '/csrf.inc.php';

// Load configuration from database
try {
    $stmt = $pdo->query("SELECT * FROM {$pb_config} LIMIT 1");
    $configRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($configRow) {
        $config_release       = $configRow['release'] ?? 'R';
        $config_send_email    = $configRow['send_email'] ?? 'N';
        $config_email         = $configRow['email'] ?? '';
        $config_date          = $configRow['date'] ?? 'd.m.Y';
        $config_time          = $configRow['time'] ?? 'H:i';
        $config_spam_check    = (int) ($configRow['spam_check'] ?? 60);
        $config_color         = $configRow['color'] ?? '#FF0000';
        $config_show_entries  = (int) ($configRow['show_entries'] ?? 10);
        $config_guestbook_name = $configRow['guestbook_name'] ?? 'pbook.php';
        $config_admin_url     = $configRow['admin_url'] ?? '';
        $config_text_format   = $configRow['text_format'] ?? 'Y';
        $config_icons         = $configRow['icons'] ?? 'Y';
        $config_smilies       = $configRow['smilies'] ?? 'Y';
        $config_icq           = $configRow['icq'] ?? 'N';
        $config_pages         = $configRow['pages'] ?? 'Y';
        $config_use_thanks    = $configRow['use_thanks'] ?? 'N';
        $config_language      = $configRow['language'] ?? 'D';
        $config_design        = $configRow['design'] ?? '';
        $config_thanks_title  = $configRow['thanks_title'] ?? '';
        $config_thanks        = $configRow['thanks'] ?? '';
        $config_statements    = $configRow['statements'] ?? 'Y';
    } else {
        // Default values if no configuration exists
        $config_release       = 'R';
        $config_send_email    = 'N';
        $config_email         = '';
        $config_date          = 'd.m.Y';
        $config_time          = 'H:i';
        $config_spam_check    = 60;
        $config_color         = '#FF0000';
        $config_show_entries  = 10;
        $config_guestbook_name = 'pbook.php';
        $config_admin_url     = '';
        $config_text_format   = 'Y';
        $config_icons         = 'Y';
        $config_smilies       = 'Y';
        $config_icq           = 'N';
        $config_pages         = 'Y';
        $config_use_thanks    = 'N';
        $config_language      = 'D';
        $config_design        = '';
        $config_thanks_title  = '';
        $config_thanks        = '';
        $config_statements    = 'Y';
    }
} catch (PDOException $e) {
    // Configuration table might not exist yet (during installation)
    $config_release       = 'R';
    $config_send_email    = 'N';
    $config_email         = '';
    $config_date          = 'd.m.Y';
    $config_time          = 'H:i';
    $config_spam_check    = 60;
    $config_color         = '#FF0000';
    $config_show_entries  = 10;
    $config_guestbook_name = 'pbook.php';
    $config_admin_url     = '';
    $config_text_format   = 'Y';
    $config_icons         = 'Y';
    $config_smilies       = 'Y';
    $config_icq           = 'N';
    $config_pages         = 'Y';
    $config_use_thanks    = 'N';
    $config_language      = 'D';
    $config_design        = '';
    $config_thanks_title  = '';
    $config_thanks        = '';
    $config_statements    = 'Y';
}
