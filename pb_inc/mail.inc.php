<?php

/**
 * PowerBook - PHP Guestbook System
 * Mailversand (seit 3.1)
 *
 * Alle Mails von PowerBook laufen über pb_mail(): UTF-8 mit MIME-Kopf,
 * kodierter Betreff, Absender "{Gästebuchtitel} <{Absenderadresse}>".
 * Ohne gültige Absenderadresse in der Konfiguration wird
 * noreply@<Domain des Gästebuchs> verwendet.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/version.inc.php';

/**
 * Absenderadresse: konfigurierte Adresse oder noreply@<Host>.
 */
function pb_mail_from(): string
{
    $from = trim((string) ($GLOBALS['config_mail_from'] ?? ''));
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false) {
        return $from;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/[^A-Za-z0-9.\-]/', '', explode(':', $host)[0]) ?: 'localhost';
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    return 'noreply@' . $host;
}

/**
 * Verschickt eine Klartext-Mail.
 *
 * @param string $to       Empfänger (eine Adresse)
 * @param string $subject  Betreff (UTF-8, wird kodiert)
 * @param string $body     Text (UTF-8)
 * @param string $replyTo  optionale Antwortadresse (z. B. die des Gastes)
 */
function pb_mail(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    $to = trim(str_replace(["\r", "\n"], '', $to));
    if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $name = trim((string) ($GLOBALS['config_title'] ?? ''));
    $name = str_replace(["\r", "\n"], '', $name !== '' ? $name : 'Gästebuch');

    $headers = 'From: ' . mb_encode_mimeheader($name, 'UTF-8', 'Q') . ' <' . pb_mail_from() . ">\r\n";
    $replyTo = trim(str_replace(["\r", "\n"], '', $replyTo));
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
        $headers .= 'Reply-To: ' . $replyTo . "\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . 'X-Mailer: PowerBook ' . PB_VERSION;

    $subject = str_replace(["\r", "\n"], ' ', $subject);
    $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'Q'), str_replace("\r\n", "\n", $body), $headers);
    if (!$ok && function_exists('logEmailError')) {
        logEmailError('Failed to send to: ' . $to, $subject);
    }

    return $ok;
}
