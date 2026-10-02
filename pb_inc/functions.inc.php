<?php

/**
 * PowerBook - PHP Guestbook System
 * Hilfsfunktionen für Gästebuch und AdminCenter
 *
 * Eine Quelle für Smileys, Icons, Datumsausgabe, Textdarstellung (BBCode,
 * Links, Smileys), Design-Vorlage und Blätterlinks. Das Gästebuch und das
 * AdminCenter nutzen dieselben Funktionen.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

/**
 * Smiley-Codes => [Bilddatei, Bedeutung]. Längere Codes stehen vorn, damit
 * „;o)“ nicht als „;)“ erkannt wird. Varianten mit Nase („:-)“) werden
 * ebenfalls erkannt, in der Hilfe aber nur als Hinweis genannt.
 */
const PB_SMILEYS = [
    '?:)' => ['confused.gif', 'Verwirrt'],
    '!:)' => ['shock.gif', 'Erschrocken'],
    ';o)' => ['happy5.gif', 'Cool'],
    ':-)' => ['happy1.gif', 'Lächeln'],
    ':-D' => ['happy4.gif', 'Grinsen'],
    ':-P' => ['happy2.gif', 'Zunge zeigen'],
    ';-)' => ['happy3.gif', 'Zwinkern'],
    ':-(' => ['sad1.gif', 'Traurig'],
    ':)' => ['happy1.gif', 'Lächeln'],
    ':D' => ['happy4.gif', 'Grinsen'],
    ':P' => ['happy2.gif', 'Zunge zeigen'],
    ';)' => ['happy3.gif', 'Zwinkern'],
    ':(' => ['sad1.gif', 'Traurig'],
    ';(' => ['sad2.gif', 'Bedrückt'],
    ':X' => ['sad3.gif', 'Verärgert'],
];

/**
 * Icons, die Besucher für ihren Eintrag wählen können (Wert => Bedeutung).
 * „no“ steht für „Kein Icon“.
 */
const PB_ICONS = [
    'text' => 'Notiz',
    'question' => 'Frage',
    'mark' => 'Achtung',
    'shock' => 'Erstaunt',
    'sad2' => 'Traurig',
    'happy1' => 'Lächeln',
    'happy5' => 'Cool',
];

const PB_WEEKDAYS = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
const PB_WEEKDAYS_SHORT = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
const PB_MONTHS = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const PB_MONTHS_SHORT = [1 => 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

/**
 * Standard-Vorlage einer Eintragskarte (entspricht powerbook.sql).
 */
const PB_DEFAULT_DESIGN = '<article class="card pb-entry-card shadow-sm">'
    . '<header class="card-header d-flex flex-wrap justify-content-between align-items-center">'
    . '<span>(#ICON#)<b>(#DATE#)</b>, <small class="text-body-secondary">(#TIME#) Uhr</small></span>'
    . '<span>(#EMAIL_NAME#)</span>'
    . '</header>'
    . '<div class="card-body">(#TEXT#)</div>'
    . '<footer class="card-footer d-flex flex-wrap justify-content-end gap-3 align-items-center text-end">'
    . '<span>(#URL#)</span>'
    . '</footer>'
    . '</article>';

/**
 * Bedeutung eines Icons, null für unbekannte Werte (auch „no“).
 *
 * Zugriff über eine Funktion statt PB_ICONS[$icon]: PHPMD (pdepend) kann
 * Zugriffe auf Array-Konstanten nicht lesen.
 */
function pb_icon_label(string $icon): ?string
{
    $icons = PB_ICONS;

    return $icons[$icon] ?? null;
}

/**
 * Bilddatei und Bedeutung eines Smiley-Codes, null für unbekannte Codes.
 *
 * @return array{0: string, 1: string}|null
 */
function pb_smiley(string $code): ?array
{
    $smileys = PB_SMILEYS;

    return $smileys[$code] ?? null;
}

/**
 * HTML-Ausgabe maskieren (auch bei ungültigem UTF-8 nie leer).
 */
function pb_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Datum wie date(), aber mit deutschen Wochentags- und Monatsnamen.
 *
 * Ersetzt die Formatzeichen l (Montag), D (Mo), F (Januar) und M (Jan)
 * durch die deutschen Namen; S (englische Ordnungsendung „th“) wird zum
 * Punkt („j S F“ → „4. Oktober“). Mit \ maskierte Zeichen bleiben wörtlich.
 */
function pb_format_date(string $format, int $timestamp): string
{
    $weekday = (int) date('w', $timestamp);
    $month = (int) date('n', $timestamp);
    $chars = mb_str_split($format);
    $count = count($chars);
    $result = '';

    for ($i = 0; $i < $count; $i++) {
        $char = $chars[$i];
        if ($char === '\\') {
            $i++;
            if ($i < $count) {
                $result .= '\\' . $chars[$i];
            }
            continue;
        }

        $german = match ($char) {
            'l' => PB_WEEKDAYS[$weekday],
            'D' => PB_WEEKDAYS_SHORT[$weekday],
            'F' => PB_MONTHS[$month],
            'M' => PB_MONTHS_SHORT[$month],
            'S' => '.',
            default => null,
        };

        if ($german === null) {
            $result .= $char;
            continue;
        }

        foreach (mb_str_split($german) as $letter) {
            $result .= '\\' . $letter;
        }
    }

    return date($result, $timestamp);
}

/**
 * Text eines Eintrags oder einer Antwort als HTML.
 *
 * Arbeitet auf dem Rohtext: Links und Smileys werden zuerst erkannt und
 * durch Marken ersetzt, danach wird der Text maskiert, BBCode nur paarweise
 * umgesetzt ([b] [i] [u] [small], verschachtelt erlaubt, offene Tags bleiben
 * Text), Zeilenumbrüche werden zu <br> und zuletzt die Marken zu HTML.
 *
 * @param bool   $bbcode     BBCode und automatische Links
 * @param bool   $smileys    Smiley-Codes als Bilder
 * @param string $smileyBase Pfad zu den Smiley-Bildern (mit / am Ende)
 */
function pb_render_text(string $text, bool $bbcode, bool $smileys, string $smileyBase = 'pb_inc/smilies/'): string
{
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Steuerzeichen (außer Tab und Zeilenumbruch) entfernen – sie dienen unten als Marken.
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? '';

    $tokens = [];
    $pattern = pb_text_token_pattern($bbcode, $smileys);
    if ($pattern !== '') {
        $text = preg_replace_callback(
            $pattern,
            static function (array $match) use (&$tokens, $smileyBase): string {
                $token = pb_text_token($match, $smileyBase);
                if ($token === null) {
                    return $match[0];
                }
                $key = "\x01" . count($tokens) . "\x02";
                $tokens[$key] = $token[0];

                return $key . $token[1];
            },
            $text,
            flags: PREG_UNMATCHED_AS_NULL
        ) ?? $text;
    }

    $html = pb_h($text);

    if ($bbcode) {
        $html = pb_text_bbcode($html);
    }

    $html = str_replace("\n", "<br>\n", $html);

    // Ohne Marken gibt strtr() den Text unverändert zurück.
    return strtr($html, $tokens);
}

/**
 * Suchmuster für pb_render_text(): Adressen (mit BBCode) und Smiley-Codes
 * (mit Smileys). '' wenn beides aus ist.
 */
function pb_text_token_pattern(bool $bbcode, bool $smileys): string
{
    $alternatives = [];
    if ($bbcode) {
        $alternatives[] = '(?<url>(?:https?|ftp)://[^\s<>"\'\[\]{}|\\\\^`]+)';
    }
    if ($smileys) {
        $codes = array_map(static fn (string $code): string => preg_quote($code, '~'), array_keys(PB_SMILEYS));
        // Smiley nur mit Leerraum (oder Textanfang bzw. „]“) davor und Leerraum,
        // Satzzeichen oder Textende dahinter – nie mitten in einem Wort oder Link.
        $alternatives[] = '(?<![^\s\]])(?<smiley>' . implode('|', $codes) . ')(?=$|[\s.,!?;:\[\)])';
    }

    return $alternatives === [] ? '' : '~' . implode('|', $alternatives) . '~u';
}

/**
 * HTML für einen Treffer des Musters aus pb_text_token_pattern().
 *
 * @param array<int|string, string|null> $match      Treffer (PREG_UNMATCHED_AS_NULL)
 * @param string                         $smileyBase Pfad zu den Smiley-Bildern (mit / am Ende)
 *
 * @return array{0: string, 1: string}|null HTML und Text dahinter, null = Treffer bleibt Text
 */
function pb_text_token(array $match, string $smileyBase): ?array
{
    $url = (string) ($match['url'] ?? '');
    if ($url !== '') {
        return pb_text_link($url);
    }

    $code = (string) ($match['smiley'] ?? '');
    $smiley = pb_smiley($code);
    if ($smiley === null) {
        return null;
    }
    [$file, $label] = $smiley;

    return ['<img src="' . pb_h($smileyBase . $file) . '" alt="' . pb_h($code) . '" title="' . pb_h($label) . '" class="pb-smiley">', ''];
}

/**
 * Link für eine erkannte Adresse. Satzzeichen am Ende gehören nicht dazu,
 * eine schließende Klammer nur, wenn die Adresse die öffnende enthält.
 *
 * @return array{0: string, 1: string}|null HTML des Links und abgetrennte Satzzeichen, null ohne gültigen Host
 */
function pb_text_link(string $url): ?array
{
    $trail = '';
    while ($url !== '' && preg_match('~[.,;:!?)]$~', $url) === 1) {
        $last = substr($url, -1);
        if ($last === ')' && substr_count($url, '(') >= substr_count($url, ')')) {
            break;
        }
        $trail = $last . $trail;
        $url = substr($url, 0, -1);
    }
    if (preg_match('~^(?:https?|ftp)://[^/?#.]+(?:\.[^/?#.]+)*~i', $url) !== 1) {
        return null;
    }

    return ['<a href="' . pb_h($url) . '" target="_blank" rel="noopener noreferrer nofollow ugc">' . pb_h($url) . '</a>', $trail];
}

/**
 * BBCode in maskiertem Text umsetzen: nur innerste vollständige Paare
 * ersetzen, bis sich nichts mehr ändert.
 */
function pb_text_bbcode(string $html): string
{
    $pattern = '~\[(b|i|u|small)\]((?:(?!\[/?(?:b|i|u|small)\]).)*?)\[/\1\]~isu';
    do {
        $before = $html;
        $html = preg_replace_callback(
            $pattern,
            static function (array $match): string {
                $tag = strtolower($match[1]);

                return '<' . $tag . '>' . $match[2] . '</' . $tag . '>';
            },
            $html
        ) ?? $before;
    } while ($html !== $before);

    return $html;
}

/**
 * Hilfe zu Formatierung und Smileys (Inhalt des aufklappbaren Bereichs).
 *
 * @param string $smileyBase Pfad zu den Smiley-Bildern (mit / am Ende)
 */
function pb_render_help(bool $bbcode, bool $smileys, string $smileyBase = 'pb_inc/smilies/'): string
{
    $html = '<div class="row g-4">';

    if ($bbcode) {
        $examples = [
            ['[b]fett[/b]', '<b>fett</b>'],
            ['[i]kursiv[/i]', '<i>kursiv</i>'],
            ['[u]unterstrichen[/u]', '<u>unterstrichen</u>'],
            ['[small]klein[/small]', '<small>klein</small>'],
        ];
        $html .= '<div class="col-md-6"><h3 class="h6">Formatierung</h3>'
            . '<table class="table table-sm pb-help-table mb-2"><thead><tr><th scope="col">Eingabe</th><th scope="col">Ergebnis</th></tr></thead><tbody>';
        foreach ($examples as [$code, $result]) {
            $html .= '<tr><td><code>' . pb_h($code) . '</code></td><td>' . $result . '</td></tr>';
        }
        $html .= '</tbody></table>'
            . '<p class="small text-body-secondary mb-0">Jedes Tag braucht sein Ende, zum Beispiel <code>[b]</code> … <code>[/b]</code>. '
            . 'Adressen, die mit <code>https://</code> oder <code>http://</code> beginnen, werden automatisch zu Links.</p></div>';
    }

    if ($smileys) {
        $html .= '<div class="col-md-6"><h3 class="h6">Smileys</h3>'
            . '<table class="table table-sm pb-help-table mb-2"><thead><tr><th scope="col">Code</th><th scope="col">Bild</th><th scope="col">Bedeutung</th></tr></thead><tbody>';
        $smileyList = PB_SMILEYS;
        foreach ([':)', ':D', ';)', ':P', ';o)', '?:)', '!:)', ':(', ';(', ':X'] as $code) {
            [$file, $label] = $smileyList[$code];
            $html .= '<tr><td><code>' . pb_h($code) . '</code></td>'
                . '<td><img src="' . pb_h($smileyBase . $file) . '" alt="' . pb_h($code) . '" class="pb-smiley"></td>'
                . '<td>' . pb_h($label) . '</td></tr>';
        }
        $html .= '</tbody></table>'
            . '<p class="small text-body-secondary mb-0">Vor einem Smiley steht ein Leerzeichen. '
            . 'Die Schreibweise mit Nase wie <code>:-)</code> funktioniert auch.</p></div>';
    }

    return $html . '</div>';
}

/**
 * Homepage-Adresse vereinheitlichen: Fehlt das Schema, wird https://
 * ergänzt. Gibt '' zurück, wenn keine gültige http(s)-Adresse entsteht.
 */
function pb_normalize_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || preg_match('/[\x00-\x20\x7F"\'<>`{}|\\\\^]/', $url) === 1) {
        return '';
    }
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url) !== 1) {
        $url = 'https://' . ltrim($url, '/');
    }
    if (preg_match('~^https?://~i', $url) !== 1) {
        return '';
    }
    // Umlaute in Domain und Pfad nur für die Prüfung in ASCII umschreiben.
    $check = preg_replace_callback(
        '~^(https?://)([^/?#:]+)(.*)$~is',
        static function (array $parts): string {
            $host = function_exists('idn_to_ascii') ? idn_to_ascii($parts[2]) : false;
            if ($host === false) {
                $host = preg_replace('/[^\x00-\x7F]+/', 'x', $parts[2]) ?? $parts[2];
            }
            $rest = preg_replace_callback('/[^\x00-\x7F]+/', static fn (array $n): string => rawurlencode($n[0]), $parts[3]) ?? $parts[3];

            return $parts[1] . $host . $rest;
        },
        $url
    ) ?? $url;
    if (filter_var($check, FILTER_VALIDATE_URL) === false || !str_contains((string) parse_url($check, PHP_URL_HOST), '.')) {
        return '';
    }

    return $url;
}

/**
 * Prüft, ob eine Design-Vorlage mindestens einen Platzhalter enthält.
 */
function pb_design_has_placeholder(string $design): bool
{
    return preg_match('/\(#(ICON|DATE|TIME|EMAIL_NAME|TEXT|URL|ICQ)#\)/', $design) === 1;
}

/**
 * Design-Vorlage aus der Konfiguration für die Ausgabe vorbereiten:
 * ohne Platzhalter (leer oder sehr altes Design) gilt die Standardvorlage;
 * Skripte, Ereignis-Attribute (on…) und javascript:-Adressen werden entfernt.
 */
function pb_prepare_design(string $design): string
{
    static $cache = [];
    if (isset($cache[$design])) {
        return $cache[$design];
    }
    $result = pb_design_has_placeholder($design) ? pb_sanitize_design($design) : PB_DEFAULT_DESIGN;
    if (count($cache) > 4) {
        $cache = [];
    }

    return $cache[$design] = $result;
}

/**
 * Entfernt ausführbare Inhalte aus HTML, das ein Admin eingegeben hat.
 */
function pb_sanitize_design(string $html): string
{
    $removeElements = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'base', 'meta', 'link', 'svg', 'math', 'noscript', 'template'];

    if (class_exists(\Dom\HTMLDocument::class)) {
        return pb_sanitize_design_dom($html, $removeElements);
    }

    // Ersatzweg ohne DOM-Erweiterung: Elemente und Attribute per Muster entfernen.
    $names = implode('|', $removeElements);
    $html = preg_replace('~<!--.*?(?:-->|$)~s', '', $html) ?? '';
    do {
        $before = $html;
        $html = preg_replace('~<(' . $names . ')\b.*?(?:</\1\s*>|$)~is', '', $html) ?? '';
    } while ($html !== $before);
    $html = preg_replace('~</?(?:' . $names . ')\b[^>]*>~i', '', $html) ?? '';
    $html = preg_replace('~(?<=[\s"\'/])on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)~i', '', $html) ?? '';
    $html = preg_replace('~(?<=[\s"\'/])srcdoc\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)~i', '', $html) ?? '';

    return preg_replace('~(?:java|vb)script\s*:~i', 'blocked:', $html) ?? '';
}

/**
 * pb_sanitize_design() mit dem HTML-Parser von PHP (Dom\HTMLDocument).
 *
 * @param list<string> $removeElements Elemente, die samt Inhalt entfallen
 */
function pb_sanitize_design_dom(string $html, array $removeElements): string
{
    $doc = \Dom\HTMLDocument::createFromString(
        '<!DOCTYPE html><html><head></head><body>' . $html . '</body></html>',
        LIBXML_NOERROR,
        'UTF-8'
    );
    $body = $doc->body;
    if ($body === null) {
        return '';
    }
    foreach (iterator_to_array($body->getElementsByTagName('*')) as $element) {
        if ($element->parentNode === null) {
            continue;
        }
        if (in_array(strtolower($element->localName), $removeElements, true)) {
            $element->remove();
            continue;
        }
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (pb_design_attribute_is_dangerous($attribute->name, $attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }
    }

    return $body->innerHTML;
}

/**
 * true, wenn ein Attribut aus einer Design-Vorlage entfallen muss:
 * Ereignis-Attribute (on…), srcdoc, Stile mit Skript und Adressen, die
 * Skript ausführen würden.
 */
function pb_design_attribute_is_dangerous(string $name, string $value): bool
{
    $urlAttributes = ['href', 'src', 'action', 'formaction', 'xlink:href', 'background', 'poster', 'data', 'cite', 'lowsrc', 'dynsrc', 'ping', 'srcset', 'longdesc', 'usemap', 'manifest'];
    $name = strtolower($name);

    if (str_starts_with($name, 'on') || $name === 'srcdoc' || $name === 'style' && preg_match('/expression\s*\(|javascript:|behavior\s*:/i', $value) === 1) {
        return true;
    }

    return in_array($name, $urlAttributes, true) && pb_is_dangerous_url($value);
}

/**
 * true, wenn eine Adresse Skript ausführen würde (javascript:, vbscript:, data: außer Bildern).
 */
function pb_is_dangerous_url(string $value): bool
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strtolower(preg_replace('/[\x00-\x20\x7F]+/', '', $value) ?? '');

    if (str_starts_with($value, 'javascript:') || str_starts_with($value, 'vbscript:')) {
        return true;
    }

    return str_starts_with($value, 'data:') && preg_match('~^data:image/(?:png|gif|jpe?g|webp);~', $value) !== 1;
}

/**
 * HTML einer Eintragskarte.
 *
 * @param array<string, mixed> $entry  Zeile aus pb_entries (Rohwerte)
 * @param array<string, mixed> $config Schlüssel: design, date, time, icons,
 *                                     smilies, text_format, statements,
 *                                     smiley_base, id_prefix
 */
function pb_render_entry(array $entry, array $config): string
{
    $smileyBase = (string) ($config['smiley_base'] ?? 'pb_inc/smilies/');
    $textFormat = ($config['text_format'] ?? 'N') === 'Y';
    $smileysOn = ($config['smilies'] ?? 'N') === 'Y';

    $icon = (string) ($entry['icon'] ?? '');
    $iconLabel = pb_icon_label($icon);
    $iconHtml = '';
    if (($config['icons'] ?? 'N') === 'Y' && $iconLabel !== null) {
        $iconHtml = '<img src="' . pb_h($smileyBase . $icon . '.gif') . '" alt="' . pb_h($iconLabel) . '" title="' . pb_h($iconLabel) . '" class="pb-entry-icon me-2">';
    }

    $timestamp = (int) ($entry['date'] ?? 0);
    $dateText = pb_format_date((string) ($config['date'] ?? 'd.m.Y'), $timestamp);
    $timeText = pb_format_date((string) ($config['time'] ?? 'H:i'), $timestamp);

    $name = trim((string) ($entry['name'] ?? ''));
    $nameHtml = '<span class="pb-entry-name">' . pb_h($name !== '' ? $name : 'Gast') . '</span>';

    $textHtml = pb_render_text(
        (string) ($entry['text'] ?? ''),
        $textFormat,
        $smileysOn && ($entry['smilies'] ?? 'N') === 'Y',
        $smileyBase
    );

    $id = (int) ($entry['id'] ?? 0);
    if (($config['statements'] ?? 'N') === 'Y') {
        $textHtml .= pb_render_entry_statement($entry, $id, $smileysOn, $smileyBase);
    }

    $homepage = pb_normalize_url((string) ($entry['homepage'] ?? ''));
    $homepageHtml = $homepage !== ''
        ? '<small><a class="pb-entry-homepage" href="' . pb_h($homepage) . '" target="_blank" rel="noopener noreferrer nofollow ugc">Homepage</a></small>'
        : '<small class="text-body-secondary">Keine Homepage</small>';

    $html = strtr(pb_prepare_design((string) ($config['design'] ?? '')), [
        '(#ICON#)' => $iconHtml,
        '(#DATE#)' => pb_h($dateText),
        '(#TIME#)' => pb_h($timeText),
        '(#EMAIL_NAME#)' => $nameHtml,
        '(#TEXT#)' => $textHtml,
        '(#URL#)' => $homepageHtml,
        '(#ICQ#)' => '',
    ]);

    if ($id > 0) {
        $prefix = (string) ($config['id_prefix'] ?? 'pbEntry');
        $attributes = 'id="' . pb_h($prefix . $id) . '" data-pb-entry-id="' . $id . '"';
        if (preg_match('~^\s*<article\b(?![^>]*\sid\s*=)~i', $html) === 1) {
            $html = preg_replace('~^(\s*<article)\b~i', '$1 ' . $attributes, $html, 1) ?? $html;
        } else {
            $html = '<div ' . $attributes . ' class="pb-entry">' . $html . '</div>';
        }
    }

    return $html;
}

/**
 * Antwort des Betreibers unter einem Eintrag ('' ohne Antwort).
 *
 * @param array<string, mixed> $entry      Zeile aus pb_entries (Rohwerte)
 * @param int                  $id         ID des Eintrags (0 = Vorschau, ohne ID-Attribut)
 * @param string               $smileyBase Pfad zu den Smiley-Bildern (mit / am Ende)
 */
function pb_render_entry_statement(array $entry, int $id, bool $smileysOn, string $smileyBase): string
{
    $statement = trim((string) ($entry['statement'] ?? ''));
    if ($statement === '') {
        return '';
    }
    $by = trim((string) ($entry['statement_by'] ?? ''));
    $label = $by !== '' ? 'Antwort von ' . pb_h($by) . ':' : 'Antwort:';

    return '<div' . ($id > 0 ? ' id="pbEntryAnswer' . $id . '"' : '') . ' class="pb-entry-statement fst-italic border-top mt-3 pt-3">'
        . '<b>' . $label . '</b><br>'
        . pb_render_text($statement, true, $smileysOn, $smileyBase)
        . '</div>';
}

/**
 * Adresse des AdminCenters: aus der Konfiguration, sonst aus der
 * aktuellen Anfrage abgeleitet.
 */
function pb_admin_url(string $configured = ''): string
{
    $configured = trim($configured);
    if ($configured !== '' && preg_match('~^https?://~i', $configured) === 1) {
        return $configured;
    }

    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host) ?: 'localhost';
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/pbook.php')));
    $dir = rtrim($dir === '.' ? '' : $dir, '/');

    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/pb_inc/admincenter/';
}

/**
 * Hängt Parameter an eine Adresse an (mit ? oder &).
 *
 * @param array<string, int|string> $params
 */
function pb_url_with(string $url, array $params): string
{
    $params = array_filter($params, static fn ($value): bool => $value !== '' && $value !== 0);
    if ($params === []) {
        return $url;
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Restliche Wartezeit der Zeitsperre in Sekunden (0 = Eintragen erlaubt).
 * Gezählt wird ab dem letzten Eintrag derselben IP-Adresse, auch wenn er
 * noch auf Freischaltung wartet.
 */
function pb_spam_wait(PDO $pdo, string $table, string $ip, int $spamCheck, int $now, bool $lock = false): int
{
    if ($spamCheck <= 0) {
        return 0;
    }
    $sql = "SELECT date FROM {$table} WHERE ip = :ip ORDER BY date DESC LIMIT 1";
    if ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ip' => $ip]);
    $last = $stmt->fetchColumn();
    if ($last === false) {
        return 0;
    }
    $passed = $now - (int) $last;

    return $passed >= 0 && $passed < $spamCheck ? $spamCheck - $passed : 0;
}

/**
 * Suchbegriff für LIKE … ESCAPE '!' maskieren (% und _ wirken nicht als Platzhalter).
 */
function pb_like_escape(string $value): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
}

/**
 * IP-Adresse des Besuchers (nur REMOTE_ADDR, geprüft).
 */
function getVisitorIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }

    return '0.0.0.0';
}
