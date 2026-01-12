<?php

/**
 * PowerBook - PHP Guestbook System
 * Admin Entry Display Helper
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Variables from parent scope
/** @var array<string, mixed> $entry */
/** @var string $config_icons */
/** @var string $config_text_format */
/** @var string $config_smilies */
/** @var string $config_icq */
/** @var string $db_statement */

// IP address
$ip = !empty($entry['ip']) ? e($entry['ip']) : 'unknown';

// Icon display
$show_icon = '';
if (!empty($entry['icon']) && $entry['icon'] !== 'no' && ($config_icons ?? 'N') === 'Y') {
    $iconFile = e($entry['icon']);
    $show_icon = "<img src=\"../smilies/{$iconFile}.gif\" border=\"0\" alt=\"\"> &nbsp;";
}

// Text processing - escape first, then apply formatting
$entryText = htmlspecialchars($entry['text'] ?? '', ENT_QUOTES, 'UTF-8');
$entryText = str_replace("\n", '<br>', $entryText);

// BBCode formatting
if (($config_text_format ?? 'N') === 'Y') {
    $entryText = (string) preg_replace('/\[b\]/i', '<b>', $entryText);
    $entryText = (string) preg_replace('/\[\/b\]/i', '</b>', $entryText);
    $entryText = (string) preg_replace('/\[u\]/i', '<u>', $entryText);
    $entryText = (string) preg_replace('/\[\/u\]/i', '</u>', $entryText);
    $entryText = (string) preg_replace('/\[i\]/i', '<i>', $entryText);
    $entryText = (string) preg_replace('/\[\/i\]/i', '</i>', $entryText);
    $entryText = (string) preg_replace('/\[small\]/i', '<small>', $entryText);
    $entryText = (string) preg_replace('/\[\/small\]/i', '</small>', $entryText);
    // Auto-link URLs
    $entryText = (string) preg_replace('/(https?:\/\/[-~a-zA-Z0-9\/\.\+%&\?|=:]+)([^-~a-zA-Z0-9\/\.\+%&\?|=:]|$)/i', '<a href="$1" target="_blank" rel="noopener">$1</a>$2', $entryText);
    $entryText = (string) preg_replace('/(ftp:\/\/[-~a-zA-Z0-9\/\.\+%&\?|=:]+)([^-~a-zA-Z0-9\/\.\+%&\?|=:]|$)/i', '<a href="$1" target="_blank" rel="noopener">$1</a>$2', $entryText);
}

// Smilies
if (($entry['smilies'] ?? 'N') === 'Y' && ($config_smilies ?? 'N') === 'Y') {
    $smilieReplacements = [
        '?:)' => '<img src="../smilies/confused.gif" alt=":confused:">',
        '!:)' => '<img src="../smilies/shock.gif" alt=":shock:">',
        ';(' => '<img src="../smilies/sad1.gif" alt=":sad:">',
        ':(' => '<img src="../smilies/sad2.gif" alt=":sad:">',
        ':X' => '<img src="../smilies/sad3.gif" alt=":sad:">',
        ':)' => '<img src="../smilies/happy1.gif" alt=":happy:">',
        ':P' => '<img src="../smilies/happy2.gif" alt=":tongue:">',
        ';)' => '<img src="../smilies/happy3.gif" alt=":wink:">',
        ':D' => '<img src="../smilies/happy4.gif" alt=":grin:">',
        ';o)' => '<img src="../smilies/happy5.gif" alt=":happy:">',
    ];
    $entryText = str_replace(array_keys($smilieReplacements), array_values($smilieReplacements), $entryText);
}

// Homepage URL
if (!empty($entry['homepage']) && strlen($entry['homepage']) > 1) {
    $homepage = $entry['homepage'];
    // Add http:// if not present
    if (!preg_match('/^https?:\/\//i', $homepage)) {
        $homepage = 'http://' . $homepage;
    }
    $url = '<small><a href="' . e($homepage) . '" target="_blank" rel="noopener">Homepage</a></small>';
} else {
    $url = '<small>Keine Homepage</small>';
}

// Email and name
$entryName = e($entry['name'] ?? '');
if (!empty($entry['email']) && strlen($entry['email']) > 1) {
    $email_name = '<a href="mailto:' . e($entry['email']) . '">' . $entryName . '</a>';
} else {
    $email_name = $entryName;
}

// ICQ (legacy feature)
$show_icq = '';
if (($config_icq ?? 'N') === 'Y') {
    if (!empty($entry['icq']) && strlen($entry['icq']) > 1) {
        $icqNumber = e($entry['icq']);
        $show_icq = '<small>ICQ: ' . $icqNumber . '</small>';
    } else {
        $show_icq = '<small>Keine ICQ#</small>';
    }
}

// Date and time
$timestamp = (int) ($entry['date'] ?? 0);
$date = $timestamp > 0 ? date('l, jS F Y', $timestamp) : '';
$time = $timestamp > 0 ? date('H:i\h', $timestamp) : '';

// Statement (admin response)
if (!empty($entry['statement']) && strlen($entry['statement']) > 1 && ($db_statement ?? 'N') !== 'N') {
    $statementText = htmlspecialchars($entry['statement'], ENT_QUOTES, 'UTF-8');
    $statementText = str_replace("\n", '<br>', $statementText);

    // BBCode for statement
    $statementText = (string) preg_replace('/\[b\]/i', '<b>', $statementText);
    $statementText = (string) preg_replace('/\[\/b\]/i', '</b>', $statementText);
    $statementText = (string) preg_replace('/\[u\]/i', '<u>', $statementText);
    $statementText = (string) preg_replace('/\[\/u\]/i', '</u>', $statementText);
    $statementText = (string) preg_replace('/\[i\]/i', '<i>', $statementText);
    $statementText = (string) preg_replace('/\[\/i\]/i', '</i>', $statementText);
    $statementText = (string) preg_replace('/\[small\]/i', '<small>', $statementText);
    $statementText = (string) preg_replace('/\[\/small\]/i', '</small>', $statementText);
    $statementText = (string) preg_replace('/(https?:\/\/[-~a-zA-Z0-9\/\.\+%&\?|=:]+)([^-~a-zA-Z0-9\/\.\+%&\?|=:]|$)/i', '<a href="$1" target="_blank" rel="noopener">$1</a>$2', $statementText);
    $statementText = (string) preg_replace('/(www\.[-~a-zA-Z0-9\/\.\+%&\?|=:]+)([^-~a-zA-Z0-9\/\.\+%&\?|=:]|$)/i', '<a href="http://$1" target="_blank" rel="noopener">$1</a>$2', $statementText);

    // Smilies for statement
    if (isset($smilieReplacements)) {
        /** @var array<string, string> $smilieReplacements */
        $statementText = str_replace(array_keys($smilieReplacements), array_values($smilieReplacements), $statementText);
    }

    $statementBy = e($entry['statement_by'] ?? 'Admin');
    $entryText .= "<br><br><hr noshade><i><b>{$statementBy}</b>'s Statement:<br><br>{$statementText}</i>";
}

// Store processed text back
$entry['text'] = $entryText;
