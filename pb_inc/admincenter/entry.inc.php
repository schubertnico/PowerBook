<?php

/**
 * PowerBook - PHP Guestbook System
 * Aufbereitung eines Eintrags für das AdminCenter
 *
 * Wird je Eintrag eingebunden und setzt fertige HTML-Bausteine. Datum und
 * Uhrzeit folgen der Konfiguration, Text und Antwort sehen aus wie im
 * Gästebuch (pb_render_text()). Die E-Mail-Adresse des Gastes erscheint nur
 * hier im AdminCenter.
 *
 * Gesetzt werden: $entryId, $isPending, $ip, $show_icon, $date, $time,
 * $entryText, $entryName, $email_name, $entryEmailLink, $homepage_link
 * (Alias $url), $answerText, $answerBy, $answerHtml, $statusBadge,
 * $show_icq (immer leer) und $entry['text'] (Text samt Antwort).
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

// Variables from parent scope
/** @var array<string, mixed> $entry */
/** @var string $config_icons */
/** @var string $config_text_format */
/** @var string $config_smilies */
/** @var string $config_date */
/** @var string $config_time */
$entryId = (int) ($entry['id'] ?? 0);
$isPending = ($entry['status'] ?? 'R') === 'U';
$smileyBase = '../smilies/';
$smileysOn = ($config_smilies ?? 'N') === 'Y';

// IP-Adresse
$ip = trim((string) ($entry['ip'] ?? '')) !== '' ? e($entry['ip']) : 'unbekannt';

// Icon
$show_icon = '';
$entryIcon = (string) ($entry['icon'] ?? '');
$entryIconLabel = pb_icon_label($entryIcon);
if (($config_icons ?? 'N') === 'Y' && $entryIconLabel !== null) {
    $show_icon = '<img src="' . $smileyBase . e($entryIcon) . '.gif" alt="' . e($entryIconLabel) . '" title="' . e($entryIconLabel) . '" class="pb-entry-icon me-2">';
}

// Datum und Uhrzeit nach der Konfiguration
$timestamp = (int) ($entry['date'] ?? 0);
$date = e(pb_admin_format_date((string) ($config_date ?? 'd.m.Y'), $timestamp));
$time = e(pb_admin_format_time((string) ($config_time ?? 'H:i'), $timestamp));

// Text wie im Gästebuch
$entryText = pb_render_text(
    (string) ($entry['text'] ?? ''),
    ($config_text_format ?? 'N') === 'Y',
    $smileysOn && ($entry['smilies'] ?? 'N') === 'Y',
    $smileyBase
);

// Name und E-Mail-Adresse (mailto nur im AdminCenter)
$entryName = e(trim((string) ($entry['name'] ?? '')));
$entryEmail = trim((string) ($entry['email'] ?? ''));
$entryEmailLink = '';
if ($entryEmail !== '') {
    $email_name = '<a href="mailto:' . e($entryEmail) . '">' . $entryName . '</a>';
    $entryEmailLink = '<a class="pb-entry-email small" href="mailto:' . e($entryEmail) . '">' . e($entryEmail) . '</a>';
} else {
    $email_name = $entryName;
}

// Homepage – BUG-001: eigene Variable $homepage_link, $url bleibt als Alias.
$homepage = pb_normalize_url((string) ($entry['homepage'] ?? ''));
if ($homepage !== '') {
    $homepageHost = (string) (parse_url($homepage, PHP_URL_HOST) ?? '');
    $homepage_link = '<a class="pb-entry-homepage" href="' . e($homepage) . '" target="_blank" rel="noopener noreferrer nofollow" title="' . e($homepage) . '">'
        . e($homepageHost !== '' ? $homepageHost : 'Homepage') . '</a>';
} else {
    $homepage_link = '<span class="text-body-secondary">keine Homepage</span>';
}
$url = $homepage_link;

// ICQ gibt es nicht mehr; die Variable bleibt für alte Vorlagen leer.
$show_icq = '';

// Antwort des Betreibers – im AdminCenter immer sichtbar
$answerText = trim((string) ($entry['statement'] ?? ''));
$answerBy = trim((string) ($entry['statement_by'] ?? ''));
$answerHtml = '';
if ($answerText !== '') {
    $answerLabel = $answerBy !== '' ? 'Antwort von ' . e($answerBy) . ':' : 'Antwort:';
    $answerHtml = '<div' . ($entryId > 0 ? ' id="pbEntryAnswerText' . $entryId . '"' : '') . ' class="pb-entry-statement fst-italic border-top mt-3 pt-3">'
        . '<b>' . $answerLabel . '</b><br>'
        . pb_render_text($answerText, true, $smileysOn, $smileyBase)
        . '</div>';
}

// Status
$statusBadge = '<span' . ($entryId > 0 ? ' id="pbEntryStatus' . $entryId . '"' : '') . ' class="badge '
    . ($isPending ? 'text-bg-warning">Wartet auf Freischaltung' : 'text-bg-success">Freigeschaltet')
    . '</span>';

$entry['text'] = $entryText . $answerHtml;
