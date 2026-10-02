<?php
/**
 * PowerBook - PHP Guestbook System
 * AdminCenter: Eintrag bearbeiten und löschen
 *
 * Recht: Einträge bearbeiten und löschen. Den Status (freigeschaltet oder
 * wartend) ändert nur, wer zusätzlich Einträge freischalten darf. Nach dem
 * Speichern bleibt das Formular stehen; bei einem Fehler bleiben die
 * Eingaben erhalten. Löschen fragt auf einer eigenen Seite nach.
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
/** @var array<string, mixed> $admin_session */
/** @var string $config_icons */
/** @var string $config_text_format */
/** @var string $config_smilies */
$admin_session ??= [];

if (!pb_admin_can($admin_session, 'entries')) {
    pb_admin_card_open('Eintrag bearbeiten');
    echo pb_admin_alert('Sie haben keine Berechtigung, Einträge zu bearbeiten.', 'danger');
    pb_admin_card_close();

    return;
}

$canRelease = pb_admin_can($admin_session, 'release');
$iconsOn = ($config_icons ?? 'N') === 'Y';
$smileysOn = ($config_smilies ?? 'N') === 'Y';
$textFormatOn = ($config_text_format ?? 'N') === 'Y';

$edit_id = (int) ($_GET['edit_id'] ?? $_POST['edit_id'] ?? 0);
$returnTo = (($_GET['return'] ?? $_POST['return'] ?? '') === 'release') ? 'release' : 'entries';
$returnUrl = '?page=' . $returnTo;
$returnLabel = $returnTo === 'release' ? 'Zurück zur Freischaltung' : 'Zurück zu den Einträgen';
$selfUrl = '?page=edit&edit_id=' . $edit_id . ($returnTo === 'release' ? '&return=release' : '');
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

if ($edit_id < 1) {
    // BUG-005: echter Link statt history.back().
    pb_admin_card_open('Eintrag bearbeiten');
    echo pb_admin_alert('Dieser Eintrag existiert nicht (ID unbekannt). <a class="alert-link" href="?page=entries">Zur Eintragsliste</a>', 'danger');
    pb_admin_card_close();

    return;
}

$stmt = $pdo->prepare("SELECT * FROM {$pb_entries} WHERE id = ?");
$stmt->execute([$edit_id]);
$stored = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($stored)) {
    pb_admin_card_open('Eintrag bearbeiten');
    echo pb_admin_alert('Diesen Eintrag gibt es nicht mehr. Vielleicht wurde er schon gelöscht. <a class="alert-link" href="?page=entries">Zur Eintragsliste</a>', 'danger');
    pb_admin_card_close();

    return;
}

// Formularwerte: gespeicherter Stand, bei einem abgeschickten Formular die Eingaben.
$edit_name = (string) ($stored['name'] ?? '');
$edit_email = (string) ($stored['email'] ?? '');
$edit_text = (string) ($stored['text'] ?? '');
$edit_homepage = (string) ($stored['homepage'] ?? '');
$edit_status = ($stored['status'] ?? 'R') === 'U' ? 'U' : 'R';
$edit_icon = (string) ($stored['icon'] ?? 'no');
$edit_smilies = ($stored['smilies'] ?? 'N') === 'Y' ? 'Y' : 'N';
$errors = [];
$showConfirm = false;

if ($action !== '' && !validateCsrfToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
    logCsrfFailure('admin_edit');
    pb_admin_flash('danger', pb_admin_csrf_message());
    if ($action !== 'update') {
        $action = '';
    }
    $csrfFailed = true;
} else {
    $csrfFailed = false;
}

if ($action === 'update') {
    $edit_name = trim(is_string($_POST['edit_name'] ?? null) ? $_POST['edit_name'] : '');
    $edit_email = trim(is_string($_POST['edit_email'] ?? null) ? $_POST['edit_email'] : '');
    $edit_text = str_replace(["\r\n", "\r"], "\n", is_string($_POST['edit_text'] ?? null) ? $_POST['edit_text'] : '');
    $edit_homepage = trim(is_string($_POST['edit_homepage'] ?? null) ? $_POST['edit_homepage'] : '');
    // A21: Felder, die wegen der Konfiguration fehlen, behalten den gespeicherten Wert.
    if ($iconsOn) {
        $postedIcon = is_string($_POST['edit_icon'] ?? null) ? $_POST['edit_icon'] : 'no';
        $edit_icon = ($postedIcon === 'no' || pb_icon_label($postedIcon) !== null) ? $postedIcon : 'no';
    }
    if ($smileysOn) {
        $edit_smilies = ($_POST['edit_smilies'] ?? 'N') === 'Y' ? 'Y' : 'N';
    }
    // A13: Status nur mit dem Recht „Einträge freischalten“.
    if ($canRelease) {
        $edit_status = ($_POST['edit_status'] ?? 'R') === 'U' ? 'U' : 'R';
    }

    $homepageNormalized = $edit_homepage === '' ? '' : pb_normalize_url($edit_homepage);

    if ($edit_name === '') {
        $errors['name'] = 'Bitte geben Sie einen Namen ein.';
    } elseif (mb_strlen($edit_name) > 100) {
        // BUG-008: serverseitige Längenprüfung.
        $errors['name'] = 'Der Name darf höchstens 100 Zeichen lang sein.';
    }
    if ($edit_email !== '' && mb_strlen($edit_email) > 250) {
        $errors['email'] = 'Die E-Mail-Adresse darf höchstens 250 Zeichen lang sein.';
    } elseif ($edit_email !== '' && filter_var($edit_email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Die E-Mail-Adresse ist ungültig.';
    }
    if ($edit_homepage !== '' && $homepageNormalized === '') {
        $errors['homepage'] = 'Die Homepage-Adresse ist ungültig.';
    } elseif (mb_strlen($homepageNormalized) > 255) {
        $errors['homepage'] = 'Die Homepage-Adresse darf höchstens 255 Zeichen lang sein.';
    }
    if (trim($edit_text) === '') {
        $errors['text'] = 'Bitte geben Sie einen Text ein.';
    } elseif (mb_strlen($edit_text) > 5000) {
        $errors['text'] = 'Der Text darf höchstens 5000 Zeichen lang sein.';
    }

    if ($csrfFailed) {
        // Meldung steht schon; Eingaben bleiben im Formular.
    } elseif ($errors !== []) {
        pb_admin_flash('danger', count($errors) === 1 ? (string) reset($errors) : 'Bitte prüfen Sie die markierten Felder.');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE {$pb_entries} SET
                name = ?, email = ?, text = ?, homepage = ?,
                status = ?, icon = ?, smilies = ?
                WHERE id = ?");
            $stmt->execute([
                $edit_name, $edit_email, $edit_text, $homepageNormalized,
                $edit_status, $edit_icon, $edit_smilies,
                $edit_id,
            ]);
            pb_admin_flash('success', 'Der Eintrag wurde gespeichert.');
            pb_admin_redirect($selfUrl);
        } catch (PDOException $e) {
            logDbError('Entry update: ' . $e->getMessage());
            pb_admin_flash('danger', 'Der Eintrag konnte nicht gespeichert werden (Datenbankfehler).');
        }
    }
}

if ($action === 'confirm_delete') {
    $showConfirm = true;
}

if ($action === 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM {$pb_entries} WHERE id = ?");
        $stmt->execute([$edit_id]);
        if ($stmt->rowCount() > 0) {
            pb_admin_flash('success', 'Der Eintrag von „' . trim((string) $stored['name']) . '“ wurde gelöscht.');
        } else {
            pb_admin_flash('danger', 'Diesen Eintrag gibt es nicht mehr.');
        }
        pb_admin_redirect($returnUrl);
    } catch (PDOException $e) {
        logDbError('Entry delete: ' . $e->getMessage());
        pb_admin_flash('danger', 'Der Eintrag konnte nicht gelöscht werden (Datenbankfehler).');
    }
}

// Anzeige des gespeicherten Eintrags (Datum, IP, Status)
$entry = $stored;
include __DIR__ . '/entry.inc.php';

$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>'
    : '';

pb_admin_card_open('Eintrag bearbeiten', 'pbEdit');
?>

<p class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>Eintrag von <b><?= $entryName ?></b> vom <?= $date ?>, <?= $time ?> <?= $statusBadge ?></span>
    <a id="pbBackToEntries" class="btn btn-outline-secondary btn-sm" href="<?= e($returnUrl) ?>"><?= $returnLabel ?></a>
</p>

<?php if ($showConfirm) { ?>
<div id="pbDeleteQuestion" class="alert alert-danger" role="alert">
    <h3 class="h6 mb-2">Eintrag wirklich löschen?</h3>
    <p>Der Eintrag von <b><?= $entryName ?></b> vom <?= $date ?> wird endgültig gelöscht<?= $answerText !== '' ? ', zusammen mit Ihrer Antwort' : '' ?>. Das lässt sich nicht rückgängig machen.</p>
    <div class="d-flex flex-wrap gap-2">
        <form action="?page=edit" method="post" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
            <input type="hidden" name="return" value="<?= $returnTo ?>">
            <button type="submit" id="pbDeleteConfirm" class="btn btn-danger">Ja, endgültig löschen</button>
        </form>
        <a id="pbDeleteCancel" class="btn btn-outline-secondary" href="<?= e($selfUrl) ?>">Abbrechen</a>
    </div>
</div>
<?php } else { ?>

<form id="pbEditForm" action="?page=edit" method="post" novalidate>
<?= csrfField() ?>
<input type="hidden" name="action" value="update">
<input type="hidden" name="edit_id" value="<?= $edit_id ?>">
<input type="hidden" name="return" value="<?= $returnTo ?>">

<div class="row g-3">
    <div class="col-md-6">
        <label for="pb_edit_name" class="form-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
        <input id="pb_edit_name" name="edit_name" type="text" class="form-control<?= $invalid('name') ?>" maxlength="100" required value="<?= e($edit_name) ?>">
        <?= $feedback('name') ?>
    </div>
    <div class="col-md-6">
        <label for="pb_edit_email" class="form-label">E-Mail-Adresse</label>
        <input id="pb_edit_email" name="edit_email" type="email" class="form-control<?= $invalid('email') ?>" maxlength="250" value="<?= e($edit_email) ?>">
        <?= $feedback('email') ?>
    </div>
    <div class="col-md-6">
        <label for="pb_edit_homepage" class="form-label">Homepage</label>
        <input id="pb_edit_homepage" name="edit_homepage" type="url" class="form-control<?= $invalid('homepage') ?>" maxlength="255" placeholder="https://www.example.org/" value="<?= e($edit_homepage) ?>">
        <?= $feedback('homepage') ?>
    </div>
    <div class="col-md-6">
        <label for="pb_edit_status" class="form-label">Status</label>
        <?php if ($canRelease) { ?>
        <select id="pb_edit_status" name="edit_status" class="form-select">
            <option value="R" <?= $edit_status === 'R' ? 'selected' : '' ?>>Freigeschaltet</option>
            <option value="U" <?= $edit_status === 'U' ? 'selected' : '' ?>>Wartet auf Freischaltung</option>
        </select>
        <div class="form-text">„Wartet auf Freischaltung“ nimmt den Eintrag aus dem Gästebuch, ohne ihn zu löschen.</div>
        <?php } else { ?>
        <p class="form-control-plaintext mb-0"><?= $statusBadge ?></p>
        <div class="form-text">Den Status ändert nur, wer Einträge freischalten darf.</div>
        <?php } ?>
    </div>

    <?php if ($iconsOn) { ?>
    <fieldset class="col-12">
        <legend class="form-label fs-6">Icon</legend>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <div class="form-check">
                <input id="pb_edit_icon_no" type="radio" class="form-check-input" name="edit_icon" value="no" <?= pb_icon_label($edit_icon) === null ? 'checked' : '' ?>>
                <label for="pb_edit_icon_no" class="form-check-label">Kein Icon</label>
            </div>
            <?php foreach (PB_ICONS as $icon => $iconLabel) { ?>
            <div class="form-check">
                <input id="pb_edit_icon_<?= $icon ?>" type="radio" class="form-check-input" name="edit_icon" value="<?= $icon ?>" <?= $edit_icon === $icon ? 'checked' : '' ?>>
                <label for="pb_edit_icon_<?= $icon ?>" class="form-check-label">
                    <img src="../smilies/<?= $icon ?>.gif" alt="<?= e($iconLabel) ?>" title="<?= e($iconLabel) ?>">
                </label>
            </div>
            <?php } ?>
        </div>
    </fieldset>
    <?php } ?>

    <div class="col-12">
        <label for="pb_edit_text" class="form-label">Text <span class="text-danger" aria-hidden="true">*</span></label>
        <textarea id="pb_edit_text" name="edit_text" rows="5" class="form-control<?= $invalid('text') ?>" maxlength="5000" required><?= e($edit_text) ?></textarea>
        <?= $feedback('text') ?>
        <?php if ($smileysOn) { ?>
        <div class="form-check mt-2">
            <input id="pb_edit_smilies" type="checkbox" class="form-check-input" name="edit_smilies" value="Y" <?= $edit_smilies === 'Y' ? 'checked' : '' ?>>
            <label for="pb_edit_smilies" class="form-check-label">Smileys im Text als Bild anzeigen</label>
        </div>
        <?php } ?>
        <?php if ($textFormatOn || $smileysOn) { ?>
        <details class="mt-2">
            <summary class="small">Hilfe zu Formatierung und Smileys</summary>
            <div class="mt-2"><?= pb_render_help($textFormatOn, $smileysOn, '../smilies/') ?></div>
        </details>
        <?php } ?>
    </div>

    <div class="col-md-6">
        <span class="form-label d-block">IP-Adresse</span>
        <code><?= $ip ?></code>
    </div>
    <div class="col-md-6">
        <span class="form-label d-block">Geschrieben am</span>
        <?= $date ?>, <?= $time ?>
    </div>

    <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" id="pbEditSubmit" class="btn btn-primary">Speichern</button>
            <button type="reset" id="pbEditReset" class="btn btn-outline-secondary">Zurücksetzen</button>
        </div>
    </div>
</div>
</form>

<hr class="my-4">

<div class="alert alert-warning mb-0">
    <h3 class="h6 mb-2">Eintrag löschen</h3>
    <p class="mb-3">Löschen ist endgültig. Sie werden vorher noch einmal gefragt.</p>
    <form action="?page=edit" method="post" class="mb-0">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="confirm_delete">
        <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
        <input type="hidden" name="return" value="<?= $returnTo ?>">
        <button type="submit" id="pbEditDelete" class="btn btn-outline-danger">Eintrag löschen</button>
    </form>
</div>

<?php }

pb_admin_card_close();
