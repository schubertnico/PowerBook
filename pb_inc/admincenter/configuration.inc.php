<?php
/**
 * PowerBook - PHP Guestbook System
 * Konfiguration ändern
 *
 * Vier Abschnitte (Allgemein, Einträge, E-Mails, Darstellung). Jedes Feld
 * wird geprüft; bei Fehlern bleiben alle Eingaben stehen und das Feld ist
 * markiert. Nach dem Speichern zeigt die Seite wieder das Formular.
 * Akzentfarbe und Sprache stehen nicht mehr in der Oberfläche (die Spalten
 * bleiben und werden hier nicht verändert).
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';
require_once __DIR__ . '/admin_email_helpers.inc.php';

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_config */
/** @var array<string, mixed> $admin_session */

// Check permission
if (($admin_session['config'] ?? 'N') !== 'Y') {
    pb_admin_card_open('Konfiguration');
    echo pb_admin_inline_message('Sie haben keine Berechtigung, die Konfiguration zu ändern.', 'danger');
    pb_admin_card_close();

    return;
}

$message = '';
$messageType = 'danger';
/** @var array<string, string> $errors Fehlermeldung je Feld */
$errors = [];

$defaults = [
    'title' => 'Gästebuch',
    'release' => 'U',
    'send_email' => 'N',
    'email' => '',
    'mail_from' => '',
    'date' => 'd.m.Y',
    'time' => 'H:i',
    'spam_check' => '30',
    'show_entries' => '10',
    'guestbook_name' => 'pbook.php',
    'admin_url' => '',
    'text_format' => 'Y',
    'icons' => 'Y',
    'smilies' => 'Y',
    'pages' => 'D',
    'use_thanks' => 'N',
    'design' => '',
    'thanks_title' => '',
    'thanks' => '',
    'statements' => 'Y',
];

/**
 * Werte aus dem Formular lesen (Texte getrimmt, Schalter auf erlaubte Werte).
 *
 * @return array<string, string>
 */
$readPost = static function (): array {
    $text = static fn (string $key): string => trim((string) ($_POST['change_' . $key] ?? ''));
    $yesNo = static fn (string $key, string $fallback): string => in_array($_POST['change_' . $key] ?? '', ['Y', 'N'], true)
        ? (string) $_POST['change_' . $key]
        : $fallback;

    return [
        'title' => $text('title'),
        'release' => ($_POST['change_release'] ?? '') === 'R' ? 'R' : 'U',
        'send_email' => $yesNo('send_email', 'N'),
        'email' => $text('email'),
        'mail_from' => $text('mail_from'),
        'date' => $text('date'),
        'time' => $text('time'),
        'spam_check' => $text('spam_check'),
        'show_entries' => $text('show_entries'),
        'guestbook_name' => $text('guestbook_name'),
        'admin_url' => $text('admin_url'),
        'text_format' => $yesNo('text_format', 'Y'),
        'icons' => $yesNo('icons', 'Y'),
        'smilies' => $yesNo('smilies', 'Y'),
        'pages' => ($_POST['change_pages'] ?? '') === 'L' ? 'L' : 'D',
        'use_thanks' => $yesNo('use_thanks', 'N'),
        // Design und Danke-Text nicht trimmen (Einrückung gehört dazu), nur Zeilenenden vereinheitlichen.
        'design' => str_replace("\r\n", "\n", (string) ($_POST['change_design'] ?? '')),
        'thanks_title' => $text('thanks_title'),
        'thanks' => str_replace("\r\n", "\n", (string) ($_POST['change_thanks'] ?? '')),
        'statements' => $yesNo('statements', 'Y'),
    ];
};

/**
 * Prüft alle Felder.
 *
 * @param array<string, string> $v
 *
 * @return array<string, string>
 */
$validate = static function (array $v): array {
    $errors = [];
    $len = static fn (string $s): int => mb_strlen($s, 'UTF-8');

    if ($v['title'] === '') {
        $errors['title'] = 'Bitte geben Sie einen Titel ein.';
    } elseif ($len($v['title']) > 150) {
        $errors['title'] = 'Der Titel darf höchstens 150 Zeichen lang sein.';
    }

    $file = $v['guestbook_name'];
    if ($file === '') {
        $errors['guestbook_name'] = 'Bitte geben Sie den Dateinamen des Gästebuchs ein, z. B. pbook.php.';
    } elseif (preg_match('~[/\\\\:]~', $file) === 1) {
        $errors['guestbook_name'] = 'Bitte nur den Dateinamen eintragen, ohne Ordner und ohne https://, z. B. pbook.php.';
    } elseif (preg_match('/^[A-Za-z0-9._-]{1,246}\.php$/', $file) !== 1) {
        $errors['guestbook_name'] = 'Der Dateiname muss auf .php enden und darf nur Buchstaben, Ziffern, Punkt, Binde- und Unterstrich enthalten.';
    } elseif (!is_file(dirname(__DIR__, 2) . '/' . $file)) {
        $errors['guestbook_name'] = 'Die Datei ' . $file . ' gibt es im PowerBook-Ordner nicht. Ab Werk heißt sie pbook.php.';
    }

    if ($v['admin_url'] !== '') {
        $url = pb_admin_normalize_admin_url($v['admin_url']);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
            $errors['admin_url'] = 'Bitte die vollständige Adresse mit https:// oder http:// eintragen, z. B. https://www.example.org/pb_inc/admincenter/.';
        } elseif ($len($url) > 250) {
            $errors['admin_url'] = 'Die Adresse darf höchstens 250 Zeichen lang sein.';
        }
    }

    if (preg_match('/^\d{1,6}$/', $v['spam_check']) !== 1 || (int) $v['spam_check'] > 86400) {
        $errors['spam_check'] = 'Bitte eine ganze Zahl von 0 bis 86400 eingeben (Sekunden).';
    }
    if (preg_match('/^\d{1,3}$/', $v['show_entries']) !== 1 || (int) $v['show_entries'] < 1 || (int) $v['show_entries'] > 100) {
        $errors['show_entries'] = 'Bitte eine ganze Zahl von 1 bis 100 eingeben.';
    }

    if ($v['send_email'] === 'Y' && $v['email'] === '') {
        $errors['email'] = 'Für Benachrichtigungen brauchen wir eine E-Mail-Adresse.';
    } elseif ($v['email'] !== '' && ($len($v['email']) > 250 || filter_var($v['email'], FILTER_VALIDATE_EMAIL) === false)) {
        $errors['email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    }
    if ($v['mail_from'] !== '' && ($len($v['mail_from']) > 250 || filter_var($v['mail_from'], FILTER_VALIDATE_EMAIL) === false)) {
        $errors['mail_from'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein oder lassen Sie das Feld leer.';
    }

    if ($v['use_thanks'] === 'Y' && $v['thanks_title'] === '') {
        $errors['thanks_title'] = 'Bitte geben Sie einen Betreff für die Danke-Mail ein.';
    } elseif ($len($v['thanks_title']) > 250) {
        $errors['thanks_title'] = 'Der Betreff darf höchstens 250 Zeichen lang sein.';
    }
    if ($v['use_thanks'] === 'Y' && trim($v['thanks']) === '') {
        $errors['thanks'] = 'Bitte geben Sie einen Text für die Danke-Mail ein.';
    }

    foreach (['date' => 'Datumsformat', 'time' => 'Zeitformat'] as $key => $label) {
        if ($v[$key] === '') {
            $errors[$key] = 'Bitte geben Sie ein ' . $label . ' ein.';
        } elseif ($len($v[$key]) > 20) {
            $errors[$key] = 'Das ' . $label . ' darf höchstens 20 Zeichen lang sein.';
        }
    }

    if (trim($v['design']) === '') {
        $errors['design'] = 'Bitte geben Sie ein Eintrags-Design ein.';
    } elseif ($len($v['design']) > 60000) {
        $errors['design'] = 'Das Design ist zu lang (höchstens 60 000 Zeichen).';
    }

    return $errors;
};

// Gespeicherte Werte laden
$values = $defaults;

try {
    $stmt = $pdo->query("SELECT * FROM {$pb_config} LIMIT 1");
    $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if (is_array($row)) {
        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $values[$key] = (string) $row[$key];
            }
        }
    }
} catch (PDOException $e) {
    logDbError('Configuration load: ' . $e->getMessage());
    $message = 'Die Konfiguration konnte wegen eines Datenbankfehlers nicht geladen werden.';
}

if (($_POST['action'] ?? '') === 'update') {
    $values = $readPost();

    if (!validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        logCsrfFailure('configuration');
        $message = pb_csrf_failed_message();
    } else {
        $errors = $validate($values);

        if ($errors !== []) {
            $message = 'Nichts gespeichert. Bitte prüfen Sie die markierten Felder.';
        } else {
            $values['admin_url'] = pb_admin_normalize_admin_url($values['admin_url']);
            $values['spam_check'] = (string) (int) $values['spam_check'];
            $values['show_entries'] = (string) (int) $values['show_entries'];

            try {
                $stmt = $pdo->prepare("UPDATE {$pb_config} SET
                    title = ?,
                    `release` = ?,
                    send_email = ?,
                    email = ?,
                    mail_from = ?,
                    date = ?,
                    time = ?,
                    spam_check = ?,
                    show_entries = ?,
                    guestbook_name = ?,
                    admin_url = ?,
                    text_format = ?,
                    icons = ?,
                    smilies = ?,
                    pages = ?,
                    use_thanks = ?,
                    design = ?,
                    thanks_title = ?,
                    thanks = ?,
                    statements = ?");
                $stmt->execute([
                    $values['title'],
                    $values['release'],
                    $values['send_email'],
                    $values['email'],
                    $values['mail_from'],
                    $values['date'],
                    $values['time'],
                    (int) $values['spam_check'],
                    (int) $values['show_entries'],
                    $values['guestbook_name'],
                    $values['admin_url'],
                    $values['text_format'],
                    $values['icons'],
                    $values['smilies'],
                    $values['pages'],
                    $values['use_thanks'],
                    $values['design'],
                    $values['thanks_title'],
                    $values['thanks'],
                    $values['statements'],
                ]);

                // Hinweise zum Design (gespeichert wird trotzdem)
                $notes = [];
                if (preg_match('/\(#(ICON|DATE|TIME|EMAIL_NAME|TEXT|URL)#\)/', $values['design']) !== 1) {
                    $notes[] = 'Das Design enthält keinen Platzhalter, das Gästebuch zeigt die Einträge deshalb mit der Standardvorlage.';
                } elseif (!str_contains($values['design'], '(#TEXT#)')) {
                    $notes[] = 'Im Design fehlt (#TEXT#), die Einträge erscheinen damit ohne Text.';
                }
                if (preg_match('/<\s*script|\son[a-z]+\s*=|javascript\s*:/i', $values['design']) === 1) {
                    $notes[] = 'Das Design enthält Skripte oder Ereignis-Attribute wie onclick. Diese werden im Gästebuch entfernt.';
                }

                $text = 'Die Konfiguration ist gespeichert.' . ($notes !== [] ? ' Hinweis: ' . implode(' ', $notes) : '');
                $type = $notes !== [] ? 'warning' : 'success';
                if (pb_admin_finish_post($text, '?page=configuration', $type)) {
                    return;
                }
                $message = $text;
                $messageType = $type;
            } catch (PDOException $e) {
                logDbError('Configuration update: ' . $e->getMessage());
                $message = 'Die Konfiguration konnte wegen eines Datenbankfehlers nicht gespeichert werden. Ihre Eingaben stehen noch im Formular.';
            }
        }
    }
}

// -------------------------------------------------------------------------
// Hilfen für die Ausgabe
// -------------------------------------------------------------------------
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>'
    : '';

/**
 * Auswahl aus Optionsfeldern.
 *
 * @param array<int, array{0: string, 1: string, 2: string}> $options [ID, Wert, Beschriftung]
 */
$radioGroup = static function (string $legend, string $name, array $options, string $current, string $help = '') use ($errors): string {
    $helpId = $options[0][0] . '_help';
    $html = '<fieldset class="col-md-6"' . ($help !== '' ? ' aria-describedby="' . $helpId . '"' : '') . '>'
        . '<legend class="form-label fs-6 mb-1">' . e($legend) . '</legend>'
        . '<div class="d-flex flex-wrap gap-3">';
    foreach ($options as [$id, $value, $label]) {
        $html .= '<div class="form-check">'
            . '<input id="' . $id . '" class="form-check-input" type="radio" name="change_' . $name . '" value="' . e($value) . '"'
            . ($current === $value ? ' checked' : '') . '>'
            . '<label for="' . $id . '" class="form-check-label">' . e($label) . '</label></div>';
    }
    $html .= '</div>';
    if ($help !== '') {
        $html .= '<div id="' . $helpId . '" class="form-text">' . e($help) . '</div>';
    }
    if (isset($errors[$name])) {
        $html .= '<div class="invalid-feedback d-block">' . e($errors[$name]) . '</div>';
    }

    return $html . '</fieldset>';
};

/** Beispiel für ein Datums- oder Zeitformat mit dem heutigen Datum. */
$example = static function (string $format, bool $isDate): string {
    if ($format === '' || mb_strlen($format, 'UTF-8') > 20) {
        return '';
    }
    $now = time();
    if ($isDate && function_exists('pb_admin_format_date')) {
        return pb_admin_format_date($format, $now);
    }

    return date($format, $now);
};

// Absender, wenn das Feld leer bleibt (noreply@<Domain>)
$savedMailFrom = $GLOBALS['config_mail_from'] ?? null;
$GLOBALS['config_mail_from'] = '';
$fallbackSender = pb_mail_from();
$GLOBALS['config_mail_from'] = $savedMailFrom;

$designPlaceholders = [
    '(#ICON#)' => 'Icon des Eintrags',
    '(#DATE#)' => 'Datum',
    '(#TIME#)' => 'Uhrzeit',
    '(#EMAIL_NAME#)' => 'Name des Gastes',
    '(#TEXT#)' => 'Text samt Ihrer Antwort',
    '(#URL#)' => 'Link zur Homepage des Gastes',
];
$thanksPlaceholders = [
    '(#NAME#)' => 'Name des Gastes',
    '(#EMAIL#)' => 'E-Mail-Adresse des Gastes',
    '(#TEXT#)' => 'Text des Eintrags',
    '(#URL#)' => 'Homepage des Gastes',
    '(#TIME#)' => 'Datum und Uhrzeit',
    '(#IP#)' => 'IP-Adresse',
];
$placeholderTable = static function (array $placeholders): string {
    $html = '<table class="table table-sm table-borderless w-auto mb-0 mt-1 small"><tbody>';
    foreach ($placeholders as $code => $label) {
        $html .= '<tr><td class="py-0 ps-0"><code>' . e($code) . '</code></td><td class="py-0">' . e($label) . '</td></tr>';
    }

    return $html . '</tbody></table>';
};

pb_admin_card_open('Konfiguration', 'pbConfig');

if ($message !== '') {
    echo pb_admin_inline_message($message, $messageType);
}
?>
<form action="?page=configuration" method="post" novalidate>
<?= csrfField() ?>
<input type="hidden" name="action" value="update">

<!-- ALLGEMEIN -->
<section class="card mb-4" id="pbConfigGeneral">
    <header class="card-header"><h3 class="h6 mb-0">Allgemein</h3></header>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="cfg_title" class="form-label">Titel des Gästebuchs <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="cfg_title" type="text" class="form-control<?= $invalid('title') ?>" name="change_title" maxlength="150" value="<?= e($values['title']) ?>" aria-describedby="cfg_title_help">
                <?= $feedback('title') ?>
                <div id="cfg_title_help" class="form-text">Erscheint als Überschrift im Gästebuch, im Browser-Tab und als Absendername der E-Mails.</div>
            </div>

            <div class="col-md-6">
                <label for="cfg_guestbook" class="form-label">Dateiname des Gästebuchs <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="cfg_guestbook" type="text" class="form-control<?= $invalid('guestbook_name') ?>" name="change_guestbook_name" maxlength="250" value="<?= e($values['guestbook_name']) ?>" aria-describedby="cfg_guestbook_help">
                <?= $feedback('guestbook_name') ?>
                <div id="cfg_guestbook_help" class="form-text">Die Datei im PowerBook-Ordner, die das Gästebuch anzeigt, ab Werk <code>pbook.php</code>.
                Nur ändern, wenn Sie die Datei umbenannt haben.</div>
            </div>

            <div class="col-12">
                <label for="cfg_admin_url" class="form-label">Adresse des AdminCenters</label>
                <input id="cfg_admin_url" type="url" class="form-control<?= $invalid('admin_url') ?>" name="change_admin_url" maxlength="250" value="<?= e($values['admin_url']) ?>" placeholder="<?= e(pb_admin_detected_url()) ?>" aria-describedby="cfg_admin_url_help">
                <?= $feedback('admin_url') ?>
                <div id="cfg_admin_url_help" class="form-text">Steht in den E-Mails an Admins und im Link aus „Passwort vergessen?“.
                Vollständig mit https://, zum Beispiel <code><?= e(pb_admin_detected_url()) ?></code>.
                Leer lassen: PowerBook nimmt die Adresse, unter der das AdminCenter gerade aufgerufen wird.</div>
            </div>
        </div>
    </div>
</section>

<!-- EINTRÄGE -->
<section class="card mb-4" id="pbConfigEntries">
    <header class="card-header"><h3 class="h6 mb-0">Einträge</h3></header>
    <div class="card-body">
        <div class="row g-3">
            <?= $radioGroup('Neue Einträge', 'release', [
                ['cfg_release_n', 'U', 'erst nach Freischaltung zeigen'],
                ['cfg_release_y', 'R', 'sofort zeigen'],
            ], $values['release'], 'Empfohlen: erst nach Freischaltung. Außer der Sperrzeit hat PowerBook keinen Spamfilter. Gilt nur für neue Einträge.') ?>

            <div class="col-md-6">
                <label for="cfg_spam" class="form-label">Sperrzeit pro IP-Adresse (Sekunden)</label>
                <input id="cfg_spam" type="number" min="0" max="86400" step="1" class="form-control<?= $invalid('spam_check') ?>" name="change_spam_check" value="<?= e($values['spam_check']) ?>" aria-describedby="cfg_spam_help">
                <?= $feedback('spam_check') ?>
                <div id="cfg_spam_help" class="form-text">So lange nimmt PowerBook von derselben IP-Adresse keinen weiteren Eintrag an.
                0 schaltet die Sperre aus. Empfohlen: 30 bis 120.</div>
            </div>

            <div class="col-md-6">
                <label for="cfg_show" class="form-label">Einträge pro Seite</label>
                <input id="cfg_show" type="number" min="1" max="100" step="1" class="form-control<?= $invalid('show_entries') ?>" name="change_show_entries" value="<?= e($values['show_entries']) ?>" aria-describedby="cfg_show_help">
                <?= $feedback('show_entries') ?>
                <div id="cfg_show_help" class="form-text">Im Gästebuch, 1 bis 100.</div>
            </div>

            <?= $radioGroup('Blättern zwischen den Seiten', 'pages', [
                ['cfg_pages_d', 'D', 'Seitenzahlen (1, 2, 3 …)'],
                ['cfg_pages_l', 'L', 'Vor- und Zurück-Links'],
            ], $values['pages']) ?>

            <?= $radioGroup('Text-Formatierung erlauben', 'text_format', [
                ['cfg_textfmt_y', 'Y', 'Ja'],
                ['cfg_textfmt_n', 'N', 'Nein'],
            ], $values['text_format'], 'Gäste können Codes wie [b]fett[/b] und [i]kursiv[/i] verwenden. Aus: Die Codes erscheinen als Text.') ?>

            <?= $radioGroup('Icons erlauben', 'icons', [
                ['cfg_icons_y', 'Y', 'Ja'],
                ['cfg_icons_n', 'N', 'Nein'],
            ], $values['icons'], 'Gäste wählen beim Schreiben ein kleines Bild für ihren Eintrag.') ?>

            <?= $radioGroup('Smileys anzeigen', 'smilies', [
                ['cfg_smilies_y', 'Y', 'Ja'],
                ['cfg_smilies_n', 'N', 'Nein'],
            ], $values['smilies'], 'Zeichen wie :) erscheinen als Bild.') ?>

            <?= $radioGroup('Antworten anzeigen', 'statements', [
                ['cfg_stmt_y', 'Y', 'Ja'],
                ['cfg_stmt_n', 'N', 'Nein'],
            ], $values['statements'], 'Ihre Antworten auf Einträge erscheinen im Gästebuch. Nein blendet sie nur aus, gelöscht wird nichts.') ?>
        </div>
    </div>
</section>

<!-- E-MAILS -->
<section class="card mb-4" id="pbConfigMail">
    <header class="card-header"><h3 class="h6 mb-0">E-Mails</h3></header>
    <div class="card-body">
        <div class="row g-3">
            <?= $radioGroup('Bei neuen Einträgen benachrichtigen', 'send_email', [
                ['cfg_send_email_y', 'Y', 'Ja'],
                ['cfg_send_email_n', 'N', 'Nein'],
            ], $values['send_email'], 'Bei jedem neuen Eintrag, auch bei Spam, geht eine E-Mail an die Adresse rechts.') ?>

            <div class="col-md-6">
                <label for="cfg_email" class="form-label">E-Mail-Adresse für Benachrichtigungen</label>
                <input id="cfg_email" type="email" class="form-control<?= $invalid('email') ?>" name="change_email" maxlength="250" value="<?= e($values['email']) ?>" aria-describedby="cfg_email_help">
                <?= $feedback('email') ?>
                <div id="cfg_email_help" class="form-text">Hierhin schickt PowerBook die Benachrichtigung über neue Einträge.</div>
            </div>

            <div class="col-md-6">
                <label for="cfg_mail_from" class="form-label">Absenderadresse</label>
                <input id="cfg_mail_from" type="email" class="form-control<?= $invalid('mail_from') ?>" name="change_mail_from" maxlength="250" value="<?= e($values['mail_from']) ?>" placeholder="<?= e($fallbackSender) ?>" aria-describedby="cfg_mail_from_help">
                <?= $feedback('mail_from') ?>
                <div id="cfg_mail_from_help" class="form-text">Adresse Ihrer eigenen Domain, sonst landen Mails im Spam.
                Von ihr kommen alle Mails des Gästebuchs. Leer lassen bedeutet <code><?= e($fallbackSender) ?></code>.</div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <?= $radioGroup('Danke-Mail an Gäste', 'use_thanks', [
                ['cfg_thanks_y', 'Y', 'Ja'],
                ['cfg_thanks_n', 'N', 'Nein'],
            ], $values['use_thanks'], 'Gäste, die eine E-Mail-Adresse angeben, bekommen nach dem Eintragen diese Mail.') ?>

            <div class="col-md-6">
                <label for="cfg_thanks_title" class="form-label">Betreff der Danke-Mail</label>
                <input id="cfg_thanks_title" type="text" class="form-control<?= $invalid('thanks_title') ?>" name="change_thanks_title" maxlength="250" value="<?= e($values['thanks_title']) ?>">
                <?= $feedback('thanks_title') ?>
            </div>

            <div class="col-12">
                <label for="cfg_thanks" class="form-label">Text der Danke-Mail</label>
                <textarea id="cfg_thanks" name="change_thanks" class="form-control<?= $invalid('thanks') ?>" rows="7" aria-describedby="cfg_thanks_help"><?= e($values['thanks']) ?></textarea>
                <?= $feedback('thanks') ?>
                <div id="cfg_thanks_help" class="form-text">Reiner Text ohne HTML. Platzhalter:
                    <?= $placeholderTable($thanksPlaceholders) ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- DARSTELLUNG -->
<section class="card mb-4" id="pbConfigDesign">
    <header class="card-header"><h3 class="h6 mb-0">Darstellung</h3></header>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="cfg_date" class="form-label">Datumsformat <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="cfg_date" type="text" class="form-control font-monospace<?= $invalid('date') ?>" name="change_date" maxlength="20" value="<?= e($values['date']) ?>" aria-describedby="cfg_date_help">
                <?= $feedback('date') ?>
                <div id="cfg_date_help" class="form-text">
                    <?php if ($example($values['date'], true) !== '') { ?>
                    Heute sähe das so aus: <b id="pbConfigDateExample"><?= e($example($values['date'], true)) ?></b>.
                    <?php } ?>
                    Üblich sind <code>d.m.Y</code> (02.10.2026) und <code>l, j. F Y</code> (Freitag, 2. Oktober 2026).
                    <a id="pbConfigHelpDate" href="date-help.php?section=date" target="_blank" rel="noopener noreferrer">Alle Zeichen erklärt</a>
                </div>
            </div>

            <div class="col-md-6">
                <label for="cfg_time" class="form-label">Zeitformat <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="cfg_time" type="text" class="form-control font-monospace<?= $invalid('time') ?>" name="change_time" maxlength="20" value="<?= e($values['time']) ?>" aria-describedby="cfg_time_help">
                <?= $feedback('time') ?>
                <div id="cfg_time_help" class="form-text">
                    <?php if ($example($values['time'], false) !== '') { ?>
                    Jetzt sähe das so aus: <b id="pbConfigTimeExample"><?= e($example($values['time'], false)) ?></b>.
                    <?php } ?>
                    Üblich ist <code>H:i</code> (21:48). Das Wort „Uhr“ steht im Eintrags-Design.
                    <a id="pbConfigHelpTime" href="date-help.php?section=time" target="_blank" rel="noopener noreferrer">Alle Zeichen erklärt</a>
                </div>
            </div>

            <div class="col-12">
                <label for="cfg_design" class="form-label">Eintrags-Design (HTML-Vorlage für jeden Eintrag) <span class="text-danger" aria-hidden="true">*</span></label>
                <textarea id="cfg_design" name="change_design" class="form-control font-monospace<?= $invalid('design') ?>" rows="9" aria-describedby="cfg_design_help"><?= e($values['design']) ?></textarea>
                <?= $feedback('design') ?>
                <div id="cfg_design_help" class="form-text">
                    Erlaubt sind HTML und die Platzhalter unten, Groß geschrieben und mit Klammern.
                    Skripte (&lt;script&gt;, onclick= und Ähnliches) werden entfernt.
                    Ohne Platzhalter zeigt das Gästebuch die Standardvorlage.
                    <?= $placeholderTable($designPlaceholders) ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="d-flex flex-wrap gap-2 justify-content-center">
    <button id="pbConfigSubmit" type="submit" class="btn btn-primary">Speichern</button>
    <a id="pbConfigCancel" href="?page=configuration" class="btn btn-outline-secondary">Änderungen verwerfen</a>
</div>
</form>
<?php
pb_admin_card_close();
