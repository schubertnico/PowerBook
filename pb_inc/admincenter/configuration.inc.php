<?php
/**
 * PowerBook - PHP Guestbook System
 * Configuration Management
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_config */
/** @var array<string, string> $admin_session */

// Check permission
if (($admin_session['config'] ?? 'N') !== 'Y') {
    echo '<div style="color: #FF6666; padding: 20px;">Sie haben keine Berechtigung für die Konfiguration.</div>';

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
    $change_icq = ($_POST['change_icq'] ?? 'N') === 'Y' ? 'Y' : 'N';
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
                icq = ?,
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
                $change_icq,
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
        $change_icq = $config['icq'] ?? 'N';
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
?>

<tr><td bgcolor="#3F5070" align="center">
    <b class="headline">P O W E R B O O K &nbsp; K O N F I G U R A T I O N</b>
</td></tr>

<tr><td bgcolor="#001F3F" valign="top">

<?php if (!empty($message)) { ?>
<div style="padding: 10px; margin: 10px 0; background: <?= $messageType === 'success' ? '#003300' : '#330000' ?>; border: 1px solid <?= $messageType === 'success' ? '#00FF00' : '#FF0000' ?>;">
    <?= e($message) ?>
</div>
<?php } ?>

<?php if ($showForm) { ?>
<form action="?page=configuration" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="update">

<table border="0" width="100%">
    <tr bgcolor="#001329"><td colspan="2" align="center">
        <b>ALLGEMEINE KONFIGURATION</b>
    </td></tr>
    <tr bgcolor="#001930"><td>
        <b>Sende E-Mail:</b><br>
        <small class="info">E-Mail an Admin verschicken, wenn neuer Eintrag verfasst wurde</small>
    </td><td width="350">
        <input type="radio" name="change_send_email" value="Y" <?= $checked($change_send_email, 'Y') ?>> Ja &nbsp;&nbsp;
        <input type="radio" name="change_send_email" value="N" <?= $checked($change_send_email, 'N') ?>> Nein
    </td></tr>
    <tr bgcolor="#001329"><td>
        <b>E-Mail:</b><br>
        <small class="info">Obige E-Mail an folgende Adresse schicken</small>
    </td><td width="350">
        <input type="text" name="change_email" value="<?= e($change_email) ?>" size="35" maxlength="250">
    </td></tr>
    <tr bgcolor="#001930"><td>
        <b>Sprache:</b><br>
        <small class="info">Diese Version unterstützt nur Deutsch.</small>
    </td><td width="350">
        <select size="1" name="change_language">
            <option value="eng" selected>Deutsch</option>
        </select>
    </td></tr>
    <tr bgcolor="#001329"><td>
        <b>Gästebuch-URL:</b><br>
        <small class="info">Name der externen PowerBook-Datei (Standard: pbook.php)</small>
    </td><td width="350">
        <input type="text" name="change_guestbook_name" value="<?= e($change_guestbook_name) ?>" size="15" maxlength="250">
    </td></tr>
    <tr bgcolor="#001930"><td>
        <b>Admin-URL:</b><br>
        <small class="info">URL zum AdminCenter (http://--URL--/pb_inc/admincenter/)</small>
    </td><td width="350">
        <input type="text" name="change_admin_url" value="<?= e($change_admin_url) ?>" size="65" maxlength="250">
    </td></tr>
    <tr bgcolor="#001329"><td>
        <b>IP Abblocken:</b><br>
        <small class="info">Zeitintervall (Sekunden), innerhalb dessen mit der gleichen IP kein zweiter Eintrag verfasst werden kann.</small>
    </td><td width="350">
        <input type="text" name="change_spam_check" value="<?= (int) $change_spam_check ?>" size="3" maxlength="10"> Sekunden
    </td></tr>
    <tr bgcolor="#001930"><td>
        <b>Freigeben:</b><br>
        <small class="info">Neue Einträge sofort freigeben?</small>
    </td><td width="350">
        <input type="radio" name="change_release" value="R" <?= $checked($change_release, 'R') ?>> Ja &nbsp;&nbsp;
        <input type="radio" name="change_release" value="U" <?= $checked($change_release, 'U') ?>> Nein
    </td></tr>
    <tr bgcolor="#001329"><td>
        <b>Text Formatierung:</b><br>
        <small class="info">Textformatierung (BBCode) zulassen?</small>
    </td><td width="350">
        <input type="radio" name="change_text_format" value="Y" <?= $checked($change_text_format, 'Y') ?>> Ja &nbsp;&nbsp;
        <input type="radio" name="change_text_format" value="N" <?= $checked($change_text_format, 'N') ?>> Nein
    </td></tr>
    <tr bgcolor="#001930"><td>
        <b>Icons:</b><br>
        <small class="info">Icons zulassen?</small>
    </td><td width="350">
        <input type="radio" name="change_icons" value="Y" <?= $checked($change_icons, 'Y') ?>> Ja &nbsp;&nbsp;
        <input type="radio" name="change_icons" value="N" <?= $checked($change_icons, 'N') ?>> Nein
    </td></tr>
    <tr bgcolor="#001329"><td>
        <b>Smilies:</b><br>
        <small class="info">Smilies zulassen?</small>
    </td><td width="350">
        <input type="radio" name="change_smilies" value="Y" <?= $checked($change_smilies, 'Y') ?>> Ja &nbsp;&nbsp;
        <input type="radio" name="change_smilies" value="N" <?= $checked($change_smilies, 'N') ?>> Nein
    </td></tr>
    <tr bgcolor="#001930"><td>
        <b>ICQ:</b><br>
        <small class="info">Eingabe der ICQ# zulassen? (Legacy-Feature)</small>
    </td><td width="350">
        <input type="radio" name="change_icq" value="Y" <?= $checked($change_icq, 'Y') ?>> Ja &nbsp;&nbsp;
        <input type="radio" name="change_icq" value="N" <?= $checked($change_icq, 'N') ?>> Nein
    </td></tr>
    <tr bgcolor="#001329"><td>
        <b>Statement:</b><br>
        <small class="info">Admin-Statement anzeigen, falls verfügbar?</small>
    </td><td width="350">
        <input type="radio" name="change_statements" value="Y" <?= $checked($change_statements, 'Y') ?>> Ja &nbsp;&nbsp;
        <input type="radio" name="change_statements" value="N" <?= $checked($change_statements, 'N') ?>> Nein
    </td></tr>
</table>
<br><br>

<table border="0" width="100%">
    <tr bgcolor="#001329"><td colspan="2" align="center">
        <b>DESIGN KONFIGURATION</b>
    </td></tr>
    <tr bgcolor="#001930"><td>
        <b>Datum:</b><br>
        <small class="info">Wie das Datum angezeigt werden soll (Standard: l, j. F Y)<br>
        <a href="date-help.php?section=date" target="_blank">Hilfe zu Datumsformaten</a></small>
    </td><td width="450">
        <input type="text" name="change_date" value="<?= e($change_date) ?>" size="15" maxlength="20">
    </td></tr>
    <tr bgcolor="#001329"><td>
        <b>Zeit:</b><br>
        <small class="info">Wie die Zeit angezeigt werden soll (Standard: H:i)<br>
        <a href="date-help.php?section=time" target="_blank">Hilfe zu Zeitformaten</a></small>
    </td><td width="450">
        <input type="text" name="change_time" value="<?= e($change_time) ?>" size="15" maxlength="20">
    </td></tr>
    <tr bgcolor="#001930"><td>
        <b>Farbe:</b><br>
        <small class="info">Farbe zum Hervorheben wichtiger Nachrichten<br>
        (zur Zeit: <font color="<?= e($change_color) ?>"><?= e($change_color) ?></font>)</small>
    </td><td width="450">
        <input type="text" name="change_color" value="<?= e($change_color) ?>" size="10" maxlength="10">
    </td></tr>
    <tr bgcolor="#001329"><td>
        <b>Einträge:</b><br>
        <small class="info">Anzahl der pro Seite angezeigten Einträge</small>
    </td><td width="450">
        <input type="text" name="change_show_entries" value="<?= (int) $change_show_entries ?>" size="3" maxlength="5">
    </td></tr>
    <tr bgcolor="#001930"><td valign="top">
        <b>Seiten:</b><br>
        <small class="info">Handhabung multipler Seiten</small>
    </td><td width="450">
        <input type="radio" name="change_pages" value="L" <?= $checked($change_pages, 'L') ?>> Durchblättern<br>
        <input type="radio" name="change_pages" value="D" <?= $checked($change_pages, 'D') ?>> Seitennummern anzeigen
    </td></tr>
    <tr bgcolor="#001329"><td valign="top">
        <b>Eintrags-Design:</b><br>
        <small class="info">Das Design der Einträge</small><br><br>
        <table border="0">
            <tr><td class="small">(#ICON#)</td><td class="small">→</td><td class="small">Icon des Eintrags</td></tr>
            <tr><td class="small">(#DATE#)</td><td class="small">→</td><td class="small">Datum des Eintrags</td></tr>
            <tr><td class="small">(#TIME#)</td><td class="small">→</td><td class="small">Zeit des Eintrags</td></tr>
            <tr><td class="small">(#EMAIL_NAME#)</td><td class="small">→</td><td class="small">Name mit E-Mail-Link</td></tr>
            <tr><td class="small">(#TEXT#)</td><td class="small">→</td><td class="small">Text des Eintrags</td></tr>
            <tr><td class="small">(#URL#)</td><td class="small">→</td><td class="small">Homepage des Autors</td></tr>
            <tr><td class="small">(#ICQ#)</td><td class="small">→</td><td class="small">ICQ# des Autors</td></tr>
        </table>
    </td><td width="450">
        <textarea name="change_design" cols="70" rows="9"><?= e($change_design) ?></textarea>
    </td></tr>
</table>
<br><br>

<table border="0" width="100%">
    <tr bgcolor="#001329"><td colspan="2" align="center">
        <b>DANKSAGUNGS-NACHRICHT</b>
    </td></tr>
    <tr bgcolor="#001930"><td>
        <b>Danksagungs-Nachricht verwenden:</b><br>
        <small class="info">Danksagungs-E-Mail an Eintrags-Autor verschicken?</small>
    </td><td width="450">
        <input type="radio" name="change_use_thanks" value="Y" <?= $checked($change_use_thanks, 'Y') ?>> Ja &nbsp;&nbsp;
        <input type="radio" name="change_use_thanks" value="N" <?= $checked($change_use_thanks, 'N') ?>> Nein
    </td></tr>
    <tr bgcolor="#001329"><td>
        <b>Titel:</b><br>
        <small class="info">Titel der E-Mail (nicht benötigt falls oben "Nein" gewählt wurde)</small>
    </td><td width="450">
        <input type="text" name="change_thanks_title" value="<?= e($change_thanks_title) ?>" size="35" maxlength="250">
    </td></tr>
    <tr bgcolor="#001930"><td valign="top">
        <b>Text:</b><br>
        <small class="info">Text der E-Mail</small><br><br>
        <table border="0">
            <tr><td class="small">(#NAME#)</td><td class="small">→</td><td class="small">Name des Autors</td></tr>
            <tr><td class="small">(#EMAIL#)</td><td class="small">→</td><td class="small">E-Mail des Autors</td></tr>
            <tr><td class="small">(#TEXT#)</td><td class="small">→</td><td class="small">Text des Eintrags</td></tr>
            <tr><td class="small">(#URL#)</td><td class="small">→</td><td class="small">Homepage des Autors</td></tr>
            <tr><td class="small">(#ICQ#)</td><td class="small">→</td><td class="small">ICQ# des Autors</td></tr>
            <tr><td class="small">(#TIME#)</td><td class="small">→</td><td class="small">Datum des Eintrags</td></tr>
            <tr><td class="small">(#IP#)</td><td class="small">→</td><td class="small">IP-Adresse des Autors</td></tr>
        </table>
    </td><td width="450">
        <textarea name="change_thanks" cols="70" rows="9"><?= e($change_thanks) ?></textarea>
    </td></tr>
</table>
<br>
<div align="center">
    <input type="submit" value="Konfiguration speichern">
    <input type="reset" value="Zurücksetzen">
</div>
</form>
<?php } ?>

</td></tr>
