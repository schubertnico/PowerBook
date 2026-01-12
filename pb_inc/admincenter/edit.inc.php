<?php
/**
 * PowerBook - PHP Guestbook System
 * Entry Edit/Delete
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_entries */
/** @var array $admin_session */
/** @var string $config_icons */
/** @var string $config_text_format */
/** @var string $config_smilies */
/** @var string $config_icq */

// Check permission
if (($admin_session['entries'] ?? 'N') !== 'Y') {
    echo '<div style="color: #FF6666; padding: 20px;">Sie haben keine Berechtigung für die Eintrags-Verwaltung.</div>';

    return;
}

$edit_id = (int) ($_GET['edit_id'] ?? $_POST['edit_id'] ?? 0);
$action = $_POST['action'] ?? '';
$message = '';
$messageType = '';
$showForm = true;
$showConfirm = false;

if ($edit_id === 0) {
    $message = '<a href="javascript:history.back()">Fehler: <b>ID unbekannt!</b></a>';
    $showForm = false;
}

// Process delete
if ($action === 'delete' && $edit_id > 0 && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    try {
        $stmt = $pdo->prepare("DELETE FROM {$pb_entries} WHERE id = ?");
        $stmt->execute([$edit_id]);
        $message = 'Eintrag erfolgreich gelöscht!';
        $messageType = 'success';
        $showForm = false;
    } catch (PDOException $e) {
        logDbError('Entry delete: ' . $e->getMessage());
        $message = 'Datenbankfehler beim Löschen des Eintrags.';
        $messageType = 'error';
    }
    regenerateCsrfToken();
}

// Process edit
if ($action === 'update' && $edit_id > 0 && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $edit_name = trim($_POST['edit_name'] ?? '');
    $edit_email = trim($_POST['edit_email'] ?? '');
    $edit_text = $_POST['edit_text'] ?? '';
    $edit_homepage = trim($_POST['edit_homepage'] ?? '');
    $edit_icq = trim($_POST['edit_icq'] ?? '');
    $edit_icon = $_POST['edit_icon'] ?? 'no';
    $edit_status = ($_POST['edit_status'] ?? 'R') === 'U' ? 'U' : 'R';
    $edit_smilies = ($_POST['edit_smilies'] ?? 'N') === 'Y' ? 'Y' : 'N';

    // Validate
    if (empty($edit_name) || empty($edit_text)) {
        $message = 'Bitte geben Sie Namen und Text ein!';
        $messageType = 'error';
    } elseif (!empty($edit_email) && !filter_var($edit_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'E-Mail-Adresse ist ungültig!';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE {$pb_entries} SET
                name = ?, email = ?, text = ?, homepage = ?,
                icq = ?, status = ?, icon = ?, smilies = ?
                WHERE id = ?");
            $stmt->execute([
                $edit_name, $edit_email, $edit_text, $edit_homepage,
                $edit_icq, $edit_status, $edit_icon, $edit_smilies,
                $edit_id,
            ]);
            $message = 'Eintrag erfolgreich bearbeitet!';
            $messageType = 'success';
            $showForm = false;
        } catch (PDOException $e) {
            logDbError('Entry update: ' . $e->getMessage());
            $message = 'Datenbankfehler beim Speichern des Eintrags.';
            $messageType = 'error';
        }
    }
    regenerateCsrfToken();
}

// Confirm delete
if ($action === 'confirm_delete' && $edit_id > 0 && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $showConfirm = true;
    $showForm = false;
}

// Load entry data
if ($showForm && $edit_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM {$pb_entries} WHERE id = ?");
        $stmt->execute([$edit_id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            $message = 'Eintrag nicht gefunden!';
            $messageType = 'error';
            $showForm = false;
        } else {
            $edit_name = $entry['name'] ?? '';
            $edit_email = $entry['email'] ?? '';
            $edit_text = $entry['text'] ?? '';
            $edit_date = (int) ($entry['date'] ?? 0);
            $edit_homepage = $entry['homepage'] ?? '';
            $edit_icq = $entry['icq'] ?? '';
            $edit_ip = $entry['ip'] ?? '';
            $edit_status = $entry['status'] ?? 'R';
            $edit_icon = $entry['icon'] ?? 'no';
            $edit_smilies = $entry['smilies'] ?? 'N';
        }
    } catch (PDOException $e) {
        logDbError('Entry load: ' . $e->getMessage());
        $message = 'Datenbankfehler beim Laden des Eintrags.';
        $messageType = 'error';
        $showForm = false;
    }
}
?>

<tr><td bgcolor="#3F5070" align="center">
    <b class="headline">E I N T R Ä G E &nbsp; &nbsp; B E A R B E I T E N</b>
</td></tr>

<tr><td bgcolor="#001F3F" valign="top">

<?php if (!empty($message)) { ?>
<div style="padding: 10px; margin: 10px 0; background: <?= $messageType === 'success' ? '#003300' : '#330000' ?>; border: 1px solid <?= $messageType === 'success' ? '#00FF00' : '#FF0000' ?>;">
    <?= $message ?>
</div>
<?php if ($messageType === 'success') { ?>
<p><a href="?page=entries">Zurück zur Eintrags-Übersicht</a></p>
<?php } ?>
<?php } ?>

<?php if ($showConfirm) { ?>
<div style="padding: 20px; text-align: center;">
    <p><font color="#FF0000"><b>Sind Sie sicher, dass Sie diesen Eintrag löschen wollen?</b></font></p>
    <form action="?page=edit" method="post" style="display: inline;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
        <?= csrfField() ?>
        <input type="submit" value="Ja, löschen" style="background: #660000; color: white;">
    </form>
    &nbsp;&nbsp;
    <a href="?page=edit&edit_id=<?= $edit_id ?>">Nein, abbrechen</a>
</div>
<?php } ?>

<?php if ($showForm) { ?>
<form action="?page=edit" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="update">
<input type="hidden" name="edit_id" value="<?= $edit_id ?>">

<div align="center">
<table border="0">
    <tr bgcolor="#001329">
        <td width="120">Name:</td>
        <td><input name="edit_name" maxlength="100" size="55" value="<?= e($edit_name) ?>"></td>
    </tr>
    <tr bgcolor="#001930">
        <td width="120">E-Mail:</td>
        <td><input name="edit_email" maxlength="250" size="55" value="<?= e($edit_email) ?>"></td>
    </tr>
    <tr bgcolor="#001329">
        <td width="120">Homepage:</td>
        <td>
            <input maxlength="5" size="4" value="http://" readonly>
            <input name="edit_homepage" maxlength="100" size="46" value="<?= e($edit_homepage) ?>">
        </td>
    </tr>
    <tr bgcolor="#001930">
        <td width="120">ICQ#:</td>
        <td>
            <?php if (($config_icq ?? 'N') === 'Y') { ?>
                <input name="edit_icq" maxlength="20" size="10" value="<?= e($edit_icq) ?>">
            <?php } else { ?>
                <span style="color: #888;">ICQ ist deaktiviert.</span>
            <?php } ?>
        </td>
    </tr>
    <tr bgcolor="#001329">
        <td width="120" valign="top">Icon:</td>
        <td>
            <?php if (($config_icons ?? 'N') === 'Y') {
                $icons = ['no' => 'Kein Icon', 'text' => 'text', 'question' => 'question', 'mark' => 'mark', 'shock' => 'shock', 'sad2' => 'sad2', 'happy1' => 'happy1', 'happy5' => 'happy5'];
                ?>
                <input type="radio" name="edit_icon" value="no" <?= (empty($edit_icon) || $edit_icon === 'no') ? 'checked' : '' ?>> Kein Icon<br>
                <?php foreach (['text', 'question', 'mark', 'shock', 'sad2', 'happy1', 'happy5'] as $icon) { ?>
                <input type="radio" name="edit_icon" value="<?= $icon ?>" <?= $edit_icon === $icon ? 'checked' : '' ?>>
                <img src="../smilies/<?= $icon ?>.gif" alt="<?= $icon ?>">
                <?php } ?>
            <?php } else { ?>
                <span style="color: #888;">Icons sind deaktiviert.</span>
            <?php } ?>
        </td>
    </tr>
    <tr bgcolor="#001930">
        <td width="120" valign="top">
            Text<?php if (($config_text_format ?? 'N') === 'Y') { ?>
            <br><small><a href="../text-help.html" target="_blank">Hilfe</a></small>
            <?php } ?>:
        </td>
        <td>
            <textarea name="edit_text" rows="10" cols="55"><?= e($edit_text) ?></textarea><br>
            <?php if (($config_smilies ?? 'N') === 'Y') { ?>
            <input type="checkbox" name="edit_smilies" value="Y" <?= ($edit_smilies ?? 'N') === 'Y' ? 'checked' : '' ?>>
            Smilies aktivieren <small>(<a href="../smilies-help.html" target="_blank">Hilfe</a>)</small>
            <?php } ?>
        </td>
    </tr>
    <tr bgcolor="#001329">
        <td width="120">Status:</td>
        <td>
            <select size="1" name="edit_status">
                <option value="R" <?= ($edit_status ?? 'R') === 'R' ? 'selected' : '' ?>>Veröffentlicht</option>
                <option value="U" <?= ($edit_status ?? 'R') === 'U' ? 'selected' : '' ?>>Verborgen</option>
            </select>
        </td>
    </tr>
    <tr bgcolor="#001930">
        <td width="120">IP-Adresse:</td>
        <td><?= e($edit_ip ?? 'unbekannt') ?></td>
    </tr>
    <tr bgcolor="#001329">
        <td width="120">Datum:</td>
        <td><?= isset($edit_date) && $edit_date > 0 ? date('d.m.Y H:i', $edit_date) : '-' ?></td>
    </tr>
    <tr bgcolor="#001930">
        <td width="120">&nbsp;</td>
        <td>
            <input type="submit" value="Speichern">
            <input type="reset" value="Zurücksetzen">
        </td>
    </tr>
</table>
</div>
</form>

<br>
<form action="?page=edit" method="post" style="text-align: center;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="confirm_delete">
    <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
    <input type="submit" value="Eintrag löschen" style="background: #660000; color: white;">
</form>

<?php } ?>

</td></tr>
