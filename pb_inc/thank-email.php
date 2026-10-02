<?php

/**
 * PowerBook - PHP Guestbook System
 * Danke-Mail an den Gast
 *
 * Definiert nur Funktionen; guestbook.inc.php ruft sie nach dem Speichern
 * auf. Schutz gegen Missbrauch: nur nach gespeichertem Eintrag, höchstens
 * eine Danke-Mail je Adresse in 24 Stunden, Name ohne Links und
 * Zeilenumbrüche, Absender ist die Absenderadresse des Gästebuchs.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.inc.php';
require_once __DIR__ . '/mail.inc.php';

/** Mindestabstand zwischen zwei Danke-Mails an dieselbe Adresse (Sekunden). */
if (!defined('PB_THANKS_INTERVAL')) {
    define('PB_THANKS_INTERVAL', 86400);
}

if (!function_exists('pb_thanks_safe_name')) {
    /**
     * Name für die Anrede: einzeilig, ohne Internetadressen.
     */
    function pb_thanks_safe_name(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $name) ?? '';
        $name = preg_replace('~(?:(?:https?|ftp)://|www\.)\S*~i', '', $name) ?? '';
        $name = trim(preg_replace('/\s{2,}/u', ' ', $name) ?? '');

        return $name !== '' ? $name : 'Gast';
    }
}

if (!function_exists('pb_thanks_mail_text')) {
    /**
     * Setzt die Platzhalter der Danke-Vorlage in einem Durchgang ein
     * (Werte werden nicht erneut ersetzt, $ und \ bleiben erhalten).
     *
     * Platzhalter: (#NAME#), (#EMAIL#), (#TEXT#), (#URL#), (#TIME#), (#IP#)
     *
     * @param array{name: string, email: string, url: string, text: string} $entry Rohwerte des Eintrags
     */
    function pb_thanks_mail_text(string $template, array $entry, int $timestamp, string $ip): string
    {
        $template = str_replace(["\r\n", "\r"], "\n", $template);
        $homepage = $entry['url'] !== '' ? pb_normalize_url($entry['url']) : '';

        return strtr($template, [
            '(#NAME#)' => pb_thanks_safe_name($entry['name']),
            '(#EMAIL#)' => $entry['email'],
            '(#TEXT#)' => $entry['text'],
            '(#URL#)' => $homepage,
            '(#TIME#)' => date('d.m.Y, H:i', $timestamp),
            '(#IP#)' => $ip,
            '(#ICQ#)' => '',
        ]);
    }
}

if (!function_exists('pb_send_thanks_mail')) {
    /**
     * Schickt die Danke-Mail, wenn sie eingeschaltet ist, der Gast eine gültige
     * Adresse angegeben hat und an diese Adresse in den letzten 24 Stunden
     * keine Danke-Mail ging (anderer Eintrag mit derselben Adresse).
     *
     * @param array{name: string, email: string, url: string, text: string} $entry Rohwerte des Eintrags
     */
    function pb_send_thanks_mail(PDO $pdo, string $table, array $entry, int $entryId, int $timestamp, string $ip): bool
    {
        if (($GLOBALS['config_use_thanks'] ?? 'N') !== 'Y') {
            return false;
        }
        $to = $entry['email'];
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE LOWER(email) = LOWER(:email) AND id <> :id AND date > :since");
            $stmt->execute([':email' => $to, ':id' => $entryId, ':since' => $timestamp - PB_THANKS_INTERVAL]);
            if ((int) $stmt->fetchColumn() > 0) {
                return false;
            }
        } catch (PDOException $e) {
            if (function_exists('logDbError')) {
                logDbError('Danke-Mail: ' . $e->getMessage());
            }

            return false;
        }

        $template = (string) ($GLOBALS['config_thanks'] ?? '');
        if (trim($template) === '') {
            $template = "Hallo (#NAME#),\n\nvielen Dank für Ihren Eintrag in unserem Gästebuch!\n\nMit freundlichen Grüßen";
        }
        $subject = trim((string) ($GLOBALS['config_thanks_title'] ?? ''));

        return pb_mail(
            $to,
            $subject !== '' ? $subject : 'Danke für Ihren Eintrag',
            pb_thanks_mail_text($template, $entry, $timestamp, $ip)
        );
    }
}
