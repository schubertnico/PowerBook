<?php

/**
 * PowerBook - PHP Guestbook System
 * Entry Display Template
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// This file is included from guestbook.inc.php with $entry array available

// Icon display
$show_icon = '';
if (!empty($entry['icon']) && $entry['icon'] !== 'no' && $entry['icon'] !== '0' && ($config_icons ?? 'N') === 'Y') {
    $iconFile = basename($entry['icon']); // Security: Only filename, no path traversal
    $show_icon = '<img src="pb_inc/smilies/' . e($iconFile) . '.gif" border="0"> &nbsp;';
}

// Format entry text (escape and apply formatting)
$entryText = e($entry['text'] ?? '');
$entryText = preg_replace("/\n/", '<br>', $entryText) ?? $entryText;

if (($config_text_format ?? 'N') === 'Y') {
    $entryText = preg_replace('/\[b\]/i', '<b>', $entryText) ?? $entryText;
    $entryText = preg_replace('/\[\/b\]/i', '</b>', $entryText) ?? $entryText;
    $entryText = preg_replace('/\[u\]/i', '<u>', $entryText) ?? $entryText;
    $entryText = preg_replace('/\[\/u\]/i', '</u>', $entryText) ?? $entryText;
    $entryText = preg_replace('/\[i\]/i', '<i>', $entryText) ?? $entryText;
    $entryText = preg_replace('/\[\/i\]/i', '</i>', $entryText) ?? $entryText;
    $entryText = preg_replace('/\[small\]/i', '<small>', $entryText) ?? $entryText;
    $entryText = preg_replace('/\[\/small\]/i', '</small>', $entryText) ?? $entryText;

    // Auto-link URLs (safe: already escaped)
    $entryText = preg_replace(
        '/(https?:\/\/[-~a-z_A-Z0-9\/.+%&amp;?|=:]+)([^-~a-z_A-Z0-9\/.+%&amp;?|=:]|$)/i',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>$2',
        $entryText
    ) ?? $entryText;

    $entryText = preg_replace(
        '/(ftp:\/\/[-~a-z_A-Z0-9\/.+%&amp;?|=:]+)([^-~a-z_A-Z0-9\/.+%&amp;?|=:]|$)/i',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>$2',
        $entryText
    ) ?? $entryText;
}

// Smilies
if (($entry['smilies'] ?? 'N') === 'Y' && ($config_smilies ?? 'N') === 'Y') {
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
        $entryText = str_replace(e($code), '<img src="pb_inc/smilies/' . $image . '" alt="smiley">', $entryText);
    }
}

// Homepage URL
$url = '<small>Keine Homepage</small>';
if (!empty($entry['homepage']) && strlen($entry['homepage']) > 1) {
    $homepage = $entry['homepage'];
    // Add http:// if missing
    if (!preg_match('/^https?:\/\//i', $homepage)) {
        $homepage = 'http://' . $homepage;
    }
    $url = '<small><a href="' . e($homepage) . '" target="_blank" rel="noopener noreferrer">Homepage</a></small>';
}

// Email and name
$email_name = e($entry['name'] ?? 'Anonym');
if (!empty($entry['email']) && strlen($entry['email']) > 1) {
    $email_name = '<a href="mailto:' . e($entry['email']) . '">' . e($entry['name'] ?? 'Anonym') . '</a>';
}

// ICQ (legacy, but preserved)
$show_icq = '';
if (($config_icq ?? 'N') === 'Y') {
    if (!empty($entry['icq']) && strlen($entry['icq']) > 1) {
        $icqNum = e($entry['icq']);
        $show_icq = '<small>ICQ: ' . $icqNum . '</small>';
    } else {
        $show_icq = '<small>Keine ICQ#</small>';
    }
}

// Date and time
$entryDate = (int) ($entry['date'] ?? 0);
$date = germandate(date($config_date ?? 'd.m.Y', $entryDate));
$time = date($config_time ?? 'H:i', $entryDate);

// Statement (admin reply)
if (($config_statements ?? 'N') === 'Y' && !empty($entry['statement']) && strlen($entry['statement']) > 1) {
    $statement = e($entry['statement']);
    $statement = preg_replace("/\n/", '<br>', $statement) ?? $statement;

    // Format statement text
    $statement = preg_replace('/\[b\]/i', '<b>', $statement) ?? $statement;
    $statement = preg_replace('/\[\/b\]/i', '</b>', $statement) ?? $statement;
    $statement = preg_replace('/\[u\]/i', '<u>', $statement) ?? $statement;
    $statement = preg_replace('/\[\/u\]/i', '</u>', $statement) ?? $statement;
    $statement = preg_replace('/\[i\]/i', '<i>', $statement) ?? $statement;
    $statement = preg_replace('/\[\/i\]/i', '</i>', $statement) ?? $statement;
    $statement = preg_replace('/\[small\]/i', '<small>', $statement) ?? $statement;
    $statement = preg_replace('/\[\/small\]/i', '</small>', $statement) ?? $statement;

    // Auto-link URLs in statement
    $statement = preg_replace(
        '/(https?:\/\/[-~a-z_A-Z0-9\/.+%&amp;?|=:]+)([^-~a-z_A-Z0-9\/.+%&amp;?|=:]|$)/i',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>$2',
        $statement
    ) ?? $statement;

    // Smilies in statement
    foreach ($smilies ?? [] as $code => $image) {
        $statement = str_replace(e($code), '<img src="pb_inc/smilies/' . $image . '" alt="smiley">', $statement);
    }

    $statementBy = e($entry['statement_by'] ?? 'Admin');
    $entryText .= '<br><br><hr noshade><i><b>' . $statementBy . '</b>\'s Statement:<br><br>' . $statement . '</i>';
}

// Apply design template
$design = $config_design ?? '';
$design = preg_replace('/\(#ICON#\)/', $show_icon, $design) ?? $design;
$design = preg_replace('/\(#DATE#\)/', $date, $design) ?? $design;
$design = preg_replace('/\(#TIME#\)/', $time, $design) ?? $design;
$design = preg_replace('/\(#EMAIL_NAME#\)/', $email_name, $design) ?? $design;
$design = preg_replace('/\(#TEXT#\)/', $entryText, $design) ?? $design;
$design = preg_replace('/\(#URL#\)/', $url, $design) ?? $design;
$design = preg_replace('/\(#ICQ#\)/', $show_icq, $design) ?? $design;

echo $design;
