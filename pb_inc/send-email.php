<?php

/**
 * PowerBook - PHP Guestbook System
 * New Entry Notification Email
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// This file is included from guestbook.inc.php when a new entry is added
// Required variables: $config_email, $config_admin_url, $name2

// Sanitize email addresses to prevent header injection
$toEmail = sanitizeEmailHeader($config_email ?? '');
$fromName = sanitizeEmailHeader($name2 ?? 'Gast');
$adminUrl = $config_admin_url ?? '';

if (!empty($toEmail) && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    $subject = 'PowerBook: Neuer Eintrag!';

    $message = <<<EOT
        Hallo!

        {$fromName} hat gerade einen neuen Eintrag in Ihrem Gaestebuch verfasst. Sollten Eintraege freigeschaltet werden muessen, tun Sie dies bitte im AdminCenter ({$adminUrl}).
        Dort koennen Sie auch diese automatische eMail, welche bei jedem neuen Eintrag an Sie geschickt wird, deaktivieren.

        --------------------------------------------------------

        PowerBook (C) 2002 by Axel Habermaier
        PHP 8.4 Update: 2025

        Link: https://github.com/schubertnico/PowerBook.git


        Diese eMail wurde automatisch generiert.
        EOT;

    $headers = [
        'From: PowerBook Automailer <noreply@powerbook.local>',
        'Reply-To: ' . $toEmail,
        'X-Mailer: PowerBook/2.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    sendEmail($toEmail, $subject, $message, implode("\r\n", $headers), 'New Entry Notification');
}
