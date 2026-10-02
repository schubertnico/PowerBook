<?php

/**
 * PowerBook - PHP Guestbook System
 * Benachrichtigung an den Gästebuch-Betreiber über einen neuen Eintrag
 *
 * Definiert nur Funktionen; guestbook.inc.php ruft sie nach dem Speichern
 * auf. Eigene Variablen, damit nichts im Gästebuch überschrieben wird.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.inc.php';
require_once __DIR__ . '/mail.inc.php';

if (!function_exists('pb_admin_notification_excerpt')) {
    /**
     * Eintragstext für die Benachrichtigung, gekürzt auf 1000 Zeichen.
     */
    function pb_admin_notification_excerpt(string $text): string
    {
        if (mb_strlen($text) <= 1000) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, 1000)) . ' …';
    }
}

if (!function_exists('pb_admin_notification_text')) {
    /**
     * Betreff und Text der Benachrichtigung (Klartext, UTF-8).
     *
     * @param array{name: string, email: string, url: string, text: string} $entry Rohwerte des Eintrags
     * @param string $status   'U' = wartet auf Freischaltung, 'R' = sofort sichtbar
     * @param string $title    Titel des Gästebuchs
     * @param string $adminUrl Adresse des AdminCenters
     *
     * @return array{subject: string, body: string}
     */
    function pb_admin_notification_text(array $entry, string $status, string $title, string $adminUrl, int $timestamp): array
    {
        $waiting = $status !== 'R';
        $text = pb_admin_notification_excerpt($entry['text']);
        $homepage = $entry['url'] !== '' ? pb_normalize_url($entry['url']) : '';

        $lines = [
            'Hallo,',
            '',
            'im Gästebuch „' . $title . '“ ist ein neuer Eintrag eingegangen.',
            '',
            'Name:     ' . $entry['name'],
            'E-Mail:   ' . ($entry['email'] !== '' ? $entry['email'] : 'nicht angegeben'),
            'Homepage: ' . ($homepage !== '' ? $homepage : 'nicht angegeben'),
            'Datum:    ' . date('d.m.Y, H:i', $timestamp) . ' Uhr',
            'Status:   ' . ($waiting ? 'wartet auf Freischaltung' : 'freigeschaltet, sofort sichtbar'),
            '',
            'Text:',
            $text,
            '',
            $waiting
                ? 'Freischalten oder löschen können Sie den Eintrag im AdminCenter:'
                : 'Bearbeiten, beantworten oder löschen können Sie den Eintrag im AdminCenter:',
            pb_url_with($adminUrl, ['page' => $waiting ? 'release' : 'entries']),
            '',
            '-- ',
            'Diese Nachricht hat Ihr Gästebuch automatisch verschickt.',
            'Abschalten können Sie sie im AdminCenter unter „Konfiguration“.',
        ];

        return [
            'subject' => $waiting ? 'Neuer Eintrag wartet auf Freischaltung' : 'Neuer Eintrag im Gästebuch',
            'body' => implode("\n", $lines) . "\n",
        ];
    }
}

if (!function_exists('pb_send_admin_notification')) {
    /**
     * Schickt die Benachrichtigung an die Adresse aus der Konfiguration.
     * Antworten gehen an den Gast, wenn er eine Adresse angegeben hat.
     *
     * @param array{name: string, email: string, url: string, text: string} $entry Rohwerte des Eintrags
     */
    function pb_send_admin_notification(array $entry, string $status, int $timestamp): bool
    {
        $to = trim((string) ($GLOBALS['config_email'] ?? ''));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $title = trim((string) ($GLOBALS['config_title'] ?? ''));
        $mail = pb_admin_notification_text(
            $entry,
            $status,
            $title !== '' ? $title : 'Gästebuch',
            pb_admin_url((string) ($GLOBALS['config_admin_url'] ?? '')),
            $timestamp
        );

        return pb_mail($to, $mail['subject'], $mail['body'], $entry['email']);
    }
}
