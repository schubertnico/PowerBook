<?php
/**
 * PowerBook - PHP Guestbook System
 * Entry Edit/Delete
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
/** @var string $pb_entries */
/** @var array $admin_session */
/** @var string $config_icons */
/** @var string $config_text_format */
/** @var string $config_smilies */
// ICQ wurde komplett entfernt — entsprechende @var-Dokumentation entfaellt.

// Check permission
if (($admin_session['entries'] ?? 'N') !== 'Y') {
    pb_admin_card_open('Einträge bearbeiten');
    echo pb_admin_alert('Sie haben keine Berechtigung für die Eintrags-Verwaltung.', 'danger');
    pb_admin_card_close();

    return;
}

$edit_id = (int) ($_GET['edit_id'] ?? $_POST['edit_id'] ?? 0);
$action = $_POST['action'] ?? '';
$message = '';
$messageType = '';
$showForm = true;
$showConfirm = false;

if ($edit_id === 0) {
    // BUG-005: Echter Link statt history.back() — bei Direktaufruf (ohne History) sinnvoll navigierbar.
    $message = 'Fehler: <b>ID unbekannt!</b> <a class="alert-link" href="?page=entries">Zur Eintragsliste</a>';
    $messageType = 'error';
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
    } elseif (mb_strlen($edit_name) > 100) {
        // BUG-008: serverseitige Laengenpruefung.
        $message = 'Der Name darf höchstens 100 Zeichen lang sein!';
        $messageType = 'error';
    } elseif (mb_strlen($edit_text) > 5000) {
        $message = 'Der Text darf höchstens 5000 Zeichen lang sein!';
        $messageType = 'error';
    } elseif (mb_strlen($edit_email) > 250) {
        $message = 'Die E-Mail-Adresse darf höchstens 250 Zeichen lang sein!';
        $messageType = 'error';
    } elseif (mb_strlen($edit_homepage) > 255) {
        $message = 'Die Homepage-URL darf höchstens 255 Zeichen lang sein!';
        $messageType = 'error';
    } else {
        try {
            // ICQ-Spalte wird nicht mehr beschrieben (Legacy-Service eingestellt).
            // Bestehende DB-Spalte bleibt unangetastet (kein Datenverlust), wird
            // beim Update aber nicht mehr ueberschrieben.
            $stmt = $pdo->prepare("UPDATE {$pb_entries} SET
                name = ?, email = ?, text = ?, homepage = ?,
                status = ?, icon = ?, smilies = ?
                WHERE id = ?");
            $stmt->execute([
                $edit_name, $edit_email, $edit_text, $edit_homepage,
                $edit_status, $edit_icon, $edit_smilies,
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

pb_admin_card_open('Einträge bearbeiten');

if (!empty($message)) {
    echo pb_admin_alert($message, pb_admin_message_type($messageType));
    if ($messageType === 'success') {
        echo '<p><a class="btn btn-outline-secondary btn-sm" href="?page=entries">Zurück zur Eintrags-Übersicht</a></p>';
    }
}

if ($showConfirm) { ?>
<div class="alert alert-danger" role="alert">
    <h3 class="h6 mb-2">Achtung: Löschen ist endgültig!</h3>
    <p class="mb-3"><b>Sind Sie sicher, dass Sie diesen Eintrag löschen wollen?</b></p>
    <form action="?page=edit" method="post" class="d-inline">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-danger">Ja, löschen</button>
    </form>
    <a class="btn btn-outline-secondary" href="?page=edit&amp;edit_id=<?= $edit_id ?>">Nein, abbrechen</a>
</div>
<?php } ?>

<?php if ($showForm) { ?>
<form action="?page=edit" method="post" novalidate>
<?= csrfField() ?>
<input type="hidden" name="action" value="update">
<input type="hidden" name="edit_id" value="<?= $edit_id ?>">

<div class="row g-3">
    <div class="col-md-6">
        <label for="pb_edit_name" class="form-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
        <input id="pb_edit_name" name="edit_name" type="text" class="form-control" maxlength="100" required value="<?= e($edit_name) ?>">
    </div>
    <div class="col-md-6">
        <label for="pb_edit_email" class="form-label">E-Mail-Adresse</label>
        <input id="pb_edit_email" name="edit_email" type="email" class="form-control" maxlength="250" value="<?= e($edit_email) ?>">
    </div>
    <div class="col-12">
        <label for="pb_edit_homepage" class="form-label">Homepage</label>
        <div class="input-group">
            <span class="input-group-text">http://</span>
            <input id="pb_edit_homepage" name="edit_homepage" type="text" class="form-control" maxlength="100" value="<?= e($edit_homepage) ?>">
        </div>
    </div>
    <div class="col-md-6">
        <label for="pb_edit_status" class="form-label">Status</label>
        <select id="pb_edit_status" name="edit_status" class="form-select">
            <option value="R" <?= ($edit_status ?? 'R') === 'R' ? 'selected' : '' ?>>Veroeffentlicht</option>
            <option value="U" <?= ($edit_status ?? 'R') === 'U' ? 'selected' : '' ?>>Verborgen</option>
        </select>
    </div>

    <fieldset class="col-12">
        <legend class="form-label">Icon</legend>
        <?php if (($config_icons ?? 'N') === 'Y') { ?>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <div class="form-check">
                <input id="pb_edit_icon_no" type="radio" class="form-check-input" name="edit_icon" value="no" <?= (empty($edit_icon) || $edit_icon === 'no') ? 'checked' : '' ?>>
                <label for="pb_edit_icon_no" class="form-check-label">Kein Icon</label>
            </div>
            <?php foreach (['text', 'question', 'mark', 'shock', 'sad2', 'happy1', 'happy5'] as $icon) { ?>
            <div class="form-check">
                <input id="pb_edit_icon_<?= $icon ?>" type="radio" class="form-check-input" name="edit_icon" value="<?= $icon ?>" <?= $edit_icon === $icon ? 'checked' : '' ?>>
                <label for="pb_edit_icon_<?= $icon ?>" class="form-check-label">
                    <img src="../smilies/<?= $icon ?>.gif" alt="<?= $icon ?>">
                </label>
            </div>
            <?php } ?>
        </div>
        <?php } else { ?>
        <p class="form-control-plaintext text-body-secondary">Icons sind deaktiviert.</p>
        <?php } ?>
    </fieldset>

    <div class="col-12">
        <label for="pb_edit_text" class="form-label">
            Text <span class="text-danger" aria-hidden="true">*</span>
            <?php if (($config_text_format ?? 'N') === 'Y') { ?>
            &nbsp;<small><a href="../text-help.html" target="_blank" rel="noopener noreferrer">Formatierungs-Hilfe</a></small>
            <?php } ?>
        </label>
        <textarea id="pb_edit_text" name="edit_text" rows="10" class="form-control" maxlength="5000" required><?= e($edit_text) ?></textarea>
        <?php if (($config_smilies ?? 'N') === 'Y') { ?>
        <div class="form-check mt-2">
            <input id="pb_edit_smilies" type="checkbox" class="form-check-input" name="edit_smilies" value="Y" <?= ($edit_smilies ?? 'N') === 'Y' ? 'checked' : '' ?>>
            <label for="pb_edit_smilies" class="form-check-label">
                Smilies aktivieren <small>(<a href="../smilies-help.html" target="_blank" rel="noopener noreferrer">Hilfe</a>)</small>
            </label>
        </div>
        <?php } ?>
    </div>

    <div class="col-md-6">
        <label class="form-label">IP-Adresse</label>
        <p class="form-control-plaintext"><code><?= e($edit_ip ?? 'unbekannt') ?></code></p>
    </div>
    <div class="col-md-6">
        <label class="form-label">Datum</label>
        <p class="form-control-plaintext"><?= isset($edit_date) && $edit_date > 0 ? date('d.m.Y H:i', $edit_date) : '-' ?></p>
    </div>

    <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Speichern</button>
            <button type="reset" class="btn btn-outline-secondary">Zurücksetzen</button>
        </div>
    </div>
</div>
</form>

<hr class="my-4">

<div class="alert alert-warning" role="alert">
    <h3 class="h6 mb-2">Gefährliche Aktion: Löschen</h3>
    <p class="mb-3">Mit dem folgenden Button wird der Eintrag <b>endgültig</b> entfernt. Sie werden vorher gefragt.</p>
    <form action="?page=edit" method="post" class="mb-0">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="confirm_delete">
        <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
        <button type="submit" class="btn btn-outline-danger">Eintrag löschen</button>
    </form>
</div>

<?php }

pb_admin_card_close();
