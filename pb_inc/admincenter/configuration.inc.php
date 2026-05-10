<?php
/**
 * PowerBook - PHP Guestbook System
 * Configuration Management
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_config */
/** @var array<string, string> $admin_session */

// Check permission
if (($admin_session['config'] ?? 'N') !== 'Y') {
    pb_admin_card_open('PowerBook Konfiguration');
    echo pb_admin_alert('Sie haben keine Berechtigung für die Konfiguration.', 'danger');
    pb_admin_card_close();

    return;
}

$message = '';
$messageType = '';
$showForm = true;

// Process form submission
if (($_POST['action'] ?? '') === 'update' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    // Get and validate form data
    $change_release = ($_POST['change_release'] ?? 'R') === 'U' ? 'U' : 'R';
    $change_send_email = ($_POST['change_send_email'] ?? 'N') === 'Y' ? 'Y' : 'N';
    $change_email = trim($_POST['change_email'] ?? '');
    $change_date = trim($_POST['change_date'] ?? 'l, j. F Y');
    $change_time = trim($_POST['change_time'] ?? 'H:i');
    $change_spam_check = max(0, (int) ($_POST['change_spam_check'] ?? 30));
    $change_color = trim($_POST['change_color'] ?? '#FF0000');
    $change_show_entries = max(1, (int) ($_POST['change_show_entries'] ?? 10));
    $change_guestbook_name = trim($_POST['change_guestbook_name'] ?? 'pbook.php');
    $change_admin_url = trim($_POST['change_admin_url'] ?? '');
    $change_text_format = ($_POST['change_text_format'] ?? 'Y') === 'Y' ? 'Y' : 'N';
    $change_icons = ($_POST['change_icons'] ?? 'Y') === 'Y' ? 'Y' : 'N';
    $change_smilies = ($_POST['change_smilies'] ?? 'Y') === 'Y' ? 'Y' : 'N';
    // ICQ-Feature wurde komplett entfernt — keine $change_icq-Variable mehr noetig.
    $change_pages = ($_POST['change_pages'] ?? 'D') === 'L' ? 'L' : 'D';
    $change_use_thanks = ($_POST['change_use_thanks'] ?? 'N') === 'Y' ? 'Y' : 'N';
    $change_language = 'eng'; // Only German supported in this version
    $change_design = $_POST['change_design'] ?? '';
    $change_thanks_title = trim($_POST['change_thanks_title'] ?? '');
    $change_thanks = $_POST['change_thanks'] ?? '';
    $change_statements = ($_POST['change_statements'] ?? 'Y') === 'Y' ? 'Y' : 'N';

    // Validate required fields
    $errors = [];
    if (empty($change_email)) {
        $errors[] = 'E-Mail-Adresse';
    }
    if (empty($change_guestbook_name)) {
        $errors[] = 'Gästebuch-URL';
    }
    if (empty($change_date)) {
        $errors[] = 'Datumsformat';
    }
    if (empty($change_time)) {
        $errors[] = 'Zeitformat';
    }
    if (empty($change_design)) {
        $errors[] = 'Eintrags-Design';
    }

    // Validate thanks message if enabled
    if ($change_use_thanks === 'Y') {
        if (empty($change_thanks_title)) {
            $errors[] = 'Danksagungs-Titel';
        }
        if (empty($change_thanks)) {
            $errors[] = 'Danksagungs-Text';
        }
    }

    if (!empty($errors)) {
        $message = 'Bitte füllen Sie folgende Felder aus: ' . implode(', ', $errors);
        $messageType = 'error';
    } else {
        try {
            // Update configuration
            // ICQ-Spalte wird nicht mehr beschrieben (Legacy-Service eingestellt).
            // Falls die Spalte in der DB noch existiert, behaelt sie ihren
            // aktuellen Wert; neue Installationen legen sie gar nicht mehr an.
            $stmt = $pdo->prepare("UPDATE {$pb_config} SET
                `release` = ?,
                send_email = ?,
                email = ?,
                date = ?,
                time = ?,
                spam_check = ?,
                color = ?,
                show_entries = ?,
                guestbook_name = ?,
                admin_url = ?,
                text_format = ?,
                icons = ?,
                smilies = ?,
                pages = ?,
                use_thanks = ?,
                language = ?,
                design = ?,
                thanks_title = ?,
                thanks = ?,
                statements = ?");

            $stmt->execute([
                $change_release,
                $change_send_email,
                $change_email,
                $change_date,
                $change_time,
                $change_spam_check,
                $change_color,
                $change_show_entries,
                $change_guestbook_name,
                $change_admin_url,
                $change_text_format,
                $change_icons,
                $change_smilies,
                $change_pages,
                $change_use_thanks,
                $change_language,
                $change_design,
                $change_thanks_title,
                $change_thanks,
                $change_statements,
            ]);

            $message = 'Die Konfiguration wurde erfolgreich aktualisiert.';
            $messageType = 'success';
            $showForm = false;
        } catch (PDOException $e) {
            logDbError('Configuration update: ' . $e->getMessage());
            $message = 'Datenbankfehler beim Speichern der Konfiguration.';
            $messageType = 'error';
        }
    }
    regenerateCsrfToken();
}

// Load current configuration
if ($showForm) {
    try {
        $stmt = $pdo->query("SELECT * FROM {$pb_config} LIMIT 1");
        $config = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    } catch (PDOException $e) {
        logDbError('Configuration load: ' . $e->getMessage());
        $config = null;
        $message = 'Datenbankfehler beim Laden der Konfiguration.';
        $messageType = 'error';
    }

    if ($config) {
        $change_release = $config['release'] ?? 'R';
        $change_send_email = $config['send_email'] ?? 'N';
        $change_email = $config['email'] ?? '';
        $change_date = $config['date'] ?? 'l, j. F Y';
        $change_time = $config['time'] ?? 'H:i';
        $change_spam_check = (int) ($config['spam_check'] ?? 30);
        $change_color = $config['color'] ?? '#FF0000';
        $change_show_entries = (int) ($config['show_entries'] ?? 10);
        $change_guestbook_name = $config['guestbook_name'] ?? 'pbook.php';
        $change_admin_url = $config['admin_url'] ?? '';
        $change_text_format = $config['text_format'] ?? 'Y';
        $change_icons = $config['icons'] ?? 'Y';
        $change_smilies = $config['smilies'] ?? 'Y';
        $change_pages = $config['pages'] ?? 'D';
        $change_use_thanks = $config['use_thanks'] ?? 'N';
        $change_language = $config['language'] ?? 'eng';
        $change_design = $config['design'] ?? '';
        $change_thanks_title = $config['thanks_title'] ?? '';
        $change_thanks = $config['thanks'] ?? '';
        $change_statements = $config['statements'] ?? 'Y';
    }
}

// Helper for checked/selected attributes
$checked = fn (string $value, string $expected): string => $value === $expected ? 'checked' : '';
$selected = fn (string $value, string $expected): string => $value === $expected ? 'selected' : '';

pb_admin_card_open('PowerBook Konfiguration');

if (!empty($message)) {
    echo pb_admin_alert(e($message), pb_admin_message_type($messageType));
}

if ($showForm) { ?>
<form action="?page=configuration" method="post" novalidate>
<?= csrfField() ?>
<input type="hidden" name="action" value="update">

<!-- ALLGEMEINE KONFIGURATION -->
<section class="card mb-4">
    <header class="card-header bg-secondary text-white"><h3 class="h6 mb-0">Allgemeine Konfiguration</h3></header>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label d-block">Sende E-Mail</label>
                <small class="text-body-secondary d-block mb-2">E-Mail an Admin verschicken, wenn neuer Eintrag verfasst wurde.</small>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input id="cfg_send_email_y" class="form-check-input" type="radio" name="change_send_email" value="Y" <?= $checked($change_send_email, 'Y') ?>>
                        <label for="cfg_send_email_y" class="form-check-label">Ja</label>
                    </div>
                    <div class="form-check">
                        <input id="cfg_send_email_n" class="form-check-input" type="radio" name="change_send_email" value="N" <?= $checked($change_send_email, 'N') ?>>
                        <label for="cfg_send_email_n" class="form-check-label">Nein</label>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <label for="cfg_email" class="form-label">E-Mail-Adresse <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="cfg_email" type="email" class="form-control" name="change_email" maxlength="250" value="<?= e($change_email) ?>" aria-describedby="cfg_email_help">
                <div id="cfg_email_help" class="form-text">Obige E-Mail an folgende Adresse schicken.</div>
            </div>

            <div class="col-md-6">
                <label for="cfg_language" class="form-label">Sprache</label>
                <select id="cfg_language" class="form-select" name="change_language" disabled>
                    <option value="eng" selected>Deutsch</option>
                </select>
                <div class="form-text">Diese Version unterstuetzt nur Deutsch.</div>
            </div>

            <div class="col-md-6">
                <label for="cfg_guestbook" class="form-label">Gästebuch-URL <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="cfg_guestbook" type="text" class="form-control" name="change_guestbook_name" maxlength="250" value="<?= e($change_guestbook_name) ?>">
                <div class="form-text">Name der externen PowerBook-Datei (Standard: pbook.php).</div>
            </div>

            <div class="col-md-12">
                <label for="cfg_admin_url" class="form-label">Admin-URL</label>
                <input id="cfg_admin_url" type="text" class="form-control" name="change_admin_url" maxlength="250" value="<?= e($change_admin_url) ?>">
                <div class="form-text">URL zum AdminCenter (z. B. https://example.com/pb_inc/admincenter/).</div>
            </div>

            <div class="col-md-6">
                <label for="cfg_spam" class="form-label">IP abblocken (Sekunden)</label>
                <input id="cfg_spam" type="number" min="0" class="form-control" name="change_spam_check" maxlength="10" value="<?= (int) $change_spam_check ?>">
                <div class="form-text">Zeitintervall, innerhalb dessen mit der gleichen IP kein zweiter Eintrag verfasst werden kann.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label d-block">Freigabe</label>
                <small class="text-body-secondary d-block mb-2">Neue Einträge sofort freigeben?</small>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input id="cfg_release_y" class="form-check-input" type="radio" name="change_release" value="R" <?= $checked($change_release, 'R') ?>>
                        <label for="cfg_release_y" class="form-check-label">Ja</label>
                    </div>
                    <div class="form-check">
                        <input id="cfg_release_n" class="form-check-input" type="radio" name="change_release" value="U" <?= $checked($change_release, 'U') ?>>
                        <label for="cfg_release_n" class="form-check-label">Nein</label>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label d-block">Text-Formatierung (BBCode)</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input id="cfg_textfmt_y" class="form-check-input" type="radio" name="change_text_format" value="Y" <?= $checked($change_text_format, 'Y') ?>>
                        <label for="cfg_textfmt_y" class="form-check-label">Ja</label>
                    </div>
                    <div class="form-check">
                        <input id="cfg_textfmt_n" class="form-check-input" type="radio" name="change_text_format" value="N" <?= $checked($change_text_format, 'N') ?>>
                        <label for="cfg_textfmt_n" class="form-check-label">Nein</label>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label d-block">Icons</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input id="cfg_icons_y" class="form-check-input" type="radio" name="change_icons" value="Y" <?= $checked($change_icons, 'Y') ?>>
                        <label for="cfg_icons_y" class="form-check-label">Ja</label>
                    </div>
                    <div class="form-check">
                        <input id="cfg_icons_n" class="form-check-input" type="radio" name="change_icons" value="N" <?= $checked($change_icons, 'N') ?>>
                        <label for="cfg_icons_n" class="form-check-label">Nein</label>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label d-block">Smilies</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input id="cfg_smilies_y" class="form-check-input" type="radio" name="change_smilies" value="Y" <?= $checked($change_smilies, 'Y') ?>>
                        <label for="cfg_smilies_y" class="form-check-label">Ja</label>
                    </div>
                    <div class="form-check">
                        <input id="cfg_smilies_n" class="form-check-input" type="radio" name="change_smilies" value="N" <?= $checked($change_smilies, 'N') ?>>
                        <label for="cfg_smilies_n" class="form-check-label">Nein</label>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label d-block">Statements anzeigen</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input id="cfg_stmt_y" class="form-check-input" type="radio" name="change_statements" value="Y" <?= $checked($change_statements, 'Y') ?>>
                        <label for="cfg_stmt_y" class="form-check-label">Ja</label>
                    </div>
                    <div class="form-check">
                        <input id="cfg_stmt_n" class="form-check-input" type="radio" name="change_statements" value="N" <?= $checked($change_statements, 'N') ?>>
                        <label for="cfg_stmt_n" class="form-check-label">Nein</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- DESIGN KONFIGURATION -->
<section class="card mb-4">
    <header class="card-header bg-secondary text-white"><h3 class="h6 mb-0">Design</h3></header>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="cfg_date" class="form-label">Datum-Format <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="cfg_date" type="text" class="form-control" name="change_date" maxlength="20" value="<?= e($change_date) ?>">
                <div class="form-text">Standard: <code>l, j. F Y</code> &middot;
                    <a href="date-help.php?section=date" target="_blank" rel="noopener noreferrer">Hilfe</a>
                </div>
            </div>

            <div class="col-md-6">
                <label for="cfg_time" class="form-label">Zeit-Format <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="cfg_time" type="text" class="form-control" name="change_time" maxlength="20" value="<?= e($change_time) ?>">
                <div class="form-text">Standard: <code>H:i</code> &middot;
                    <a href="date-help.php?section=time" target="_blank" rel="noopener noreferrer">Hilfe</a>
                </div>
            </div>

            <div class="col-md-6">
                <label for="cfg_color" class="form-label">Akzentfarbe</label>
                <div class="input-group">
                    <input id="cfg_color" type="text" class="form-control" name="change_color" maxlength="10" value="<?= e($change_color) ?>">
                    <span class="input-group-text" style="background-color: <?= e($change_color) ?>; color: #fff; min-width: 80px;">Vorschau</span>
                </div>
                <div class="form-text">Farbe zum Hervorheben wichtiger Nachrichten (zur Zeit: <code><?= e($change_color) ?></code>).</div>
            </div>

            <div class="col-md-6">
                <label for="cfg_show" class="form-label">Einträge pro Seite</label>
                <input id="cfg_show" type="number" min="1" class="form-control" name="change_show_entries" maxlength="5" value="<?= (int) $change_show_entries ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label d-block">Seiten-Handhabung</label>
                <div class="form-check">
                    <input id="cfg_pages_l" class="form-check-input" type="radio" name="change_pages" value="L" <?= $checked($change_pages, 'L') ?>>
                    <label for="cfg_pages_l" class="form-check-label">Durchblaettern (Vor/Zurück)</label>
                </div>
                <div class="form-check">
                    <input id="cfg_pages_d" class="form-check-input" type="radio" name="change_pages" value="D" <?= $checked($change_pages, 'D') ?>>
                    <label for="cfg_pages_d" class="form-check-label">Seitennummern anzeigen</label>
                </div>
            </div>

            <div class="col-12">
                <label for="cfg_design" class="form-label">Eintrags-Design (HTML-Template) <span class="text-danger" aria-hidden="true">*</span></label>
                <textarea id="cfg_design" name="change_design" class="form-control font-monospace" rows="9"><?= e($change_design) ?></textarea>
                <div class="form-text">
                    Verfügbare Platzhalter:
                    <table class="pb-placeholder-table d-inline-table align-baseline mt-2">
                        <tr><td><code>(#ICON#)</code></td><td>Icon</td></tr>
                        <tr><td><code>(#DATE#)</code></td><td>Datum</td></tr>
                        <tr><td><code>(#TIME#)</code></td><td>Zeit</td></tr>
                        <tr><td><code>(#EMAIL_NAME#)</code></td><td>Name mit E-Mail-Link</td></tr>
                        <tr><td><code>(#TEXT#)</code></td><td>Text</td></tr>
                        <tr><td><code>(#URL#)</code></td><td>Homepage</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- DANKSAGUNGS-NACHRICHT -->
<section class="card mb-4">
    <header class="card-header bg-secondary text-white"><h3 class="h6 mb-0">Danksagungs-Nachricht</h3></header>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label d-block">Nachricht verwenden?</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input id="cfg_thanks_y" class="form-check-input" type="radio" name="change_use_thanks" value="Y" <?= $checked($change_use_thanks, 'Y') ?>>
                        <label for="cfg_thanks_y" class="form-check-label">Ja</label>
                    </div>
                    <div class="form-check">
                        <input id="cfg_thanks_n" class="form-check-input" type="radio" name="change_use_thanks" value="N" <?= $checked($change_use_thanks, 'N') ?>>
                        <label for="cfg_thanks_n" class="form-check-label">Nein</label>
                    </div>
                </div>
                <div class="form-text">Danksagungs-E-Mail an Eintrags-Autor verschicken?</div>
            </div>

            <div class="col-md-6">
                <label for="cfg_thanks_title" class="form-label">Titel der E-Mail</label>
                <input id="cfg_thanks_title" type="text" class="form-control" name="change_thanks_title" maxlength="250" value="<?= e($change_thanks_title) ?>">
            </div>

            <div class="col-12">
                <label for="cfg_thanks" class="form-label">Text der E-Mail</label>
                <textarea id="cfg_thanks" name="change_thanks" class="form-control font-monospace" rows="9"><?= e($change_thanks) ?></textarea>
                <div class="form-text">
                    Verfügbare Platzhalter:
                    <table class="pb-placeholder-table d-inline-table align-baseline mt-2">
                        <tr><td><code>(#NAME#)</code></td><td>Name</td></tr>
                        <tr><td><code>(#EMAIL#)</code></td><td>E-Mail</td></tr>
                        <tr><td><code>(#TEXT#)</code></td><td>Text</td></tr>
                        <tr><td><code>(#URL#)</code></td><td>Homepage</td></tr>
                        <tr><td><code>(#TIME#)</code></td><td>Datum</td></tr>
                        <tr><td><code>(#IP#)</code></td><td>IP</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="d-flex flex-wrap gap-2 justify-content-center">
    <button type="submit" class="btn btn-primary">Konfiguration speichern</button>
    <button type="reset" class="btn btn-outline-secondary">Zurücksetzen</button>
</div>
</form>
<?php }

pb_admin_card_close();
