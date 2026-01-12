<?php

/**
 * PowerBook - PHP Guestbook System
 * Helper Functions
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

/**
 * Convert English date/time names to German
 */
function germandate(string $sub_date): string
{
    $sub_date = strtolower($sub_date);

    // Convert days to German
    $sub_date = str_replace('monday', 'Montag', $sub_date);
    $sub_date = str_replace('tuesday', 'Dienstag', $sub_date);
    $sub_date = str_replace('wednesday', 'Mittwoch', $sub_date);
    $sub_date = str_replace('thursday', 'Donnerstag', $sub_date);
    $sub_date = str_replace('friday', 'Freitag', $sub_date);
    $sub_date = str_replace('saturday', 'Samstag', $sub_date);
    $sub_date = str_replace('sunday', 'Sonntag', $sub_date);

    $sub_date = str_replace('mon', 'Mo', $sub_date);
    $sub_date = str_replace('tue', 'Di', $sub_date);
    $sub_date = str_replace('wed', 'Mi', $sub_date);
    $sub_date = str_replace('thu', 'Do', $sub_date);
    $sub_date = str_replace('fri', 'Fr', $sub_date);
    $sub_date = str_replace('sat', 'Sa', $sub_date);
    $sub_date = str_replace('sun', 'So', $sub_date);

    // Convert months to German
    $sub_date = str_replace('january', 'Januar', $sub_date);
    $sub_date = str_replace('february', 'Februar', $sub_date);
    $sub_date = str_replace('march', 'März', $sub_date);
    $sub_date = str_replace('april', 'April', $sub_date);
    $sub_date = str_replace('may', 'Mai', $sub_date);
    $sub_date = str_replace('june', 'Juni', $sub_date);
    $sub_date = str_replace('july', 'Juli', $sub_date);
    $sub_date = str_replace('august', 'August', $sub_date);
    $sub_date = str_replace('september', 'September', $sub_date);
    $sub_date = str_replace('october', 'Oktober', $sub_date);
    $sub_date = str_replace('november', 'November', $sub_date);
    $sub_date = str_replace('december', 'Dezember', $sub_date);

    $sub_date = str_replace('jan', 'Jan', $sub_date);
    $sub_date = str_replace('feb', 'Feb', $sub_date);
    $sub_date = str_replace('mar', 'Mär', $sub_date);
    $sub_date = str_replace('apr', 'Apr', $sub_date);
    $sub_date = str_replace('jun', 'Jun', $sub_date);
    $sub_date = str_replace('jul', 'Jul', $sub_date);
    $sub_date = str_replace('aug', 'Aug', $sub_date);
    $sub_date = str_replace('sep', 'Sep', $sub_date);
    $sub_date = str_replace('oct', 'Okt', $sub_date);
    $sub_date = str_replace('nov', 'Nov', $sub_date);

    return str_replace('dec', 'Dez', $sub_date);
}

/**
 * Format text with BBCode-style tags (safely)
 */
function formatText(string $text, bool $enableSmilies = true): string
{
    global $config_text_format, $config_smilies;

    // Convert newlines to <br>
    $text = nl2br($text, false);

    // BBCode formatting (if enabled)
    if ($config_text_format === 'Y') {
        $text = preg_replace('/\[b\]/i', '<b>', $text) ?? $text;
        $text = preg_replace('/\[\/b\]/i', '</b>', $text) ?? $text;
        $text = preg_replace('/\[u\]/i', '<u>', $text) ?? $text;
        $text = preg_replace('/\[\/u\]/i', '</u>', $text) ?? $text;
        $text = preg_replace('/\[i\]/i', '<i>', $text) ?? $text;
        $text = preg_replace('/\[\/i\]/i', '</i>', $text) ?? $text;
        $text = preg_replace('/\[small\]/i', '<small>', $text) ?? $text;
        $text = preg_replace('/\[\/small\]/i', '</small>', $text) ?? $text;

        // Auto-link URLs
        $text = preg_replace(
            '/(https?:\/\/[-~a-z_A-Z0-9\/.+%&?|=:]+)([^-~a-z_A-Z0-9\/.+%&?|=:]|$)/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>$2',
            $text
        ) ?? $text;

        $text = preg_replace(
            '/(ftp:\/\/[-~a-z_A-Z0-9\/.+%&?|=:]+)([^-~a-z_A-Z0-9\/.+%&?|=:]|$)/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>$2',
            $text
        ) ?? $text;
    }

    // Smilies (if enabled)
    if ($enableSmilies && $config_smilies === 'Y') {
        $smilies = [
            '?:)' => 'confused.gif',
            '!:)' => 'shock.gif',
            ';(' => 'sad1.gif',
            ':(' => 'sad2.gif',
            ':X' => 'sad3.gif',
            ':)' => 'happy1.gif',
            ':P' => 'happy2.gif',
            ';)' => 'happy3.gif',
            ':D' => 'happy4.gif',
            ';o)' => 'happy5.gif',
        ];

        foreach ($smilies as $code => $image) {
            $text = str_replace($code, '<img src="pb_inc/smilies/' . $image . '" alt="' . e($code) . '">', $text);
        }
    }

    return $text;
}

/**
 * Get visitor IP address safely
 */
function getVisitorIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Validate IP format
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }

    return '0.0.0.0';
}
