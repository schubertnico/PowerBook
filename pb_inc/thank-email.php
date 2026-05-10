<?php

/**
 * PowerBook - PHP Guestbook System
 * Thank You Email to Entry Author
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// This file is included from guestbook.inc.php when a new entry is added
// Required variables: $email2, $name2, $text2, $url2, $time, $ip, $config_thanks, $config_thanks_title, $config_email

// Format time for email
$emailTime = date('d.m.Y, H:i', (int) $time);

// Get template content
$content = $config_thanks ?? '';

// Replace placeholders with actual values (using preg_replace instead of deprecated ereg_replace)
$content = preg_replace('/\(#NAME#\)/', sanitizeEmailHeader($name2 ?? ''), $content) ?? $content;
$content = preg_replace('/\(#EMAIL#\)/', sanitizeEmailHeader($email2 ?? ''), $content) ?? $content;
$content = preg_replace('/\(#TEXT#\)/', $text2 ?? '', $content) ?? $content;
$content = preg_replace('/\(#URL#\)/', 'http://' . ($url2 ?? ''), $content) ?? $content;
// (#ICQ#)-Platzhalter wird durch leeren String ersetzt — Legacy-Feld entfernt,
// aber alte Templates aus bestehenden DB-Konfigurationen sollen nicht crashen.
$content = preg_replace('/\(#ICQ#\)/', '', $content) ?? $content;
$content = preg_replace('/\(#TIME#\)/', $emailTime, $content) ?? $content;
$content = preg_replace('/\(#IP#\)/', $ip ?? '', $content) ?? $content;

// Sanitize recipient email
$toEmail = sanitizeEmailHeader($email2 ?? '');
$fromEmail = sanitizeEmailHeader($config_email ?? 'noreply@powerbook.local');
$subject = sanitizeEmailHeader($config_thanks_title ?? 'Danke für Ihren Eintrag');

if (!empty($toEmail) && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    $headers = [
        'From: ' . $fromEmail,
        'X-Mailer: PowerBook/2.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    sendEmail($toEmail, $subject, $content, implode("\r\n", $headers), 'Thank You Email');
}
