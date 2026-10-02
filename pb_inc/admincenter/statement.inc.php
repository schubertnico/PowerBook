<?php
/**
 * PowerBook - PHP Guestbook System
 * AdminCenter: Antwort auf einen Eintrag
 *
 * Pro Eintrag gibt es eine Antwort. Sie erscheint im Gästebuch unter dem
 * Eintrag als „Antwort von {Name}:“ – Autor ist, wer sie zuletzt gespeichert
 * hat. Ein leeres Feld entfernt die Antwort. Recht: Einträge bearbeiten und
 * löschen.
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
/** @var string $config_text_format */
/** @var string $config_smilies */
/** @var string $config_statements */
$admin_session ??= [];

if (!pb_admin_can($admin_session, 'entries')) {
    pb_admin_card_open('Antwort');
    echo pb_admin_alert('Sie haben keine Berechtigung, Antworten zu schreiben.', 'danger');
    pb_admin_card_close();

    return;
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$adminName = trim((string) ($admin_session['name'] ?? ''));
$selfUrl = '?page=statement&id=' . $id;

if ($id < 1) {
    // BUG-005: echter Link statt history.back().
    pb_admin_card_open('Antwort');
    echo pb_admin_alert('Dieser Eintrag existiert nicht (ID unbekannt). <a class="alert-link" href="?page=entries">Zur Eintragsliste</a>', 'danger');
    pb_admin_card_close();

    return;
}

$stmt = $pdo->prepare("SELECT * FROM {$pb_entries} WHERE id = ?");
$stmt->execute([$id]);
$stored = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($stored)) {
    pb_admin_card_open('Antwort');
    echo pb_admin_alert('Diesen Eintrag gibt es nicht mehr. Vielleicht wurde er schon gelöscht. <a class="alert-link" href="?page=entries">Zur Eintragsliste</a>', 'danger');
    pb_admin_card_close();

    return;
}

$storedAnswer = trim((string) ($stored['statement'] ?? ''));
$storedBy = trim((string) ($stored['statement_by'] ?? ''));
$edit_statement = $storedAnswer;
$answerError = '';

if ($action !== '') {
    $posted = str_replace(["\r\n", "\r"], "\n", is_string($_POST['edit_statement'] ?? null) ? $_POST['edit_statement'] : '');
    $edit_statement = $posted;

    if (!validateCsrfToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        logCsrfFailure('admin_statement');
        pb_admin_flash('danger', pb_admin_csrf_message());
    } else {
        $answer = trim($posted);
        if ($action === 'delete') {
            $answer = '';
        }

        if ($answer !== '' && mb_strlen($answer) > 5000) {
            $answerError = 'Die Antwort darf höchstens 5000 Zeichen lang sein.';
            pb_admin_flash('danger', $answerError);
        } elseif ($answer === '' && $storedAnswer === '') {
            $edit_statement = '';
            pb_admin_flash('info', 'Das Feld ist leer. Schreiben Sie zuerst eine Antwort.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE {$pb_entries} SET statement = ?, statement_by = ? WHERE id = ?");
                $stmt->execute([$answer, $answer === '' ? '' : $adminName, $id]);
                if ($answer === '') {
                    pb_admin_flash('success', 'Die Antwort wurde entfernt.');
                } else {
                    pb_admin_flash('success', 'Die Antwort wurde gespeichert. Sie erscheint im Gästebuch unter dem Eintrag.');
                }
                pb_admin_redirect($selfUrl);
            } catch (PDOException $e) {
                logDbError('Statement update: ' . $e->getMessage());
                pb_admin_flash('danger', 'Die Antwort konnte nicht gespeichert werden (Datenbankfehler).');
            }
        }
    }
}

// Vorschau: Eintrag mit der gespeicherten Antwort
$entry = $stored;
include __DIR__ . '/entry.inc.php';

pb_admin_card_open($storedAnswer !== '' ? 'Antwort bearbeiten' : 'Antwort schreiben', 'pbAnswer');
?>

<p class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>Ihre Antwort erscheint im Gästebuch unter dem Eintrag, mit Ihrem Namen davor. Pro Eintrag gibt es eine Antwort.</span>
    <a id="pbBackToEntries" class="btn btn-outline-secondary btn-sm" href="?page=entries">Zurück zu den Einträgen</a>
</p>

<?php if (($config_statements ?? 'Y') === 'N') { ?>
<div class="alert alert-warning">Antworten sind im Gästebuch zurzeit ausgeblendet (Konfiguration). Gespeichert wird die Antwort trotzdem.</div>
<?php } ?>

<article id="pbAnswerPreview" class="card pb-entry-card shadow-sm mb-4">
    <header class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?= $show_icon ?><b><?= $date ?></b>, <small class="text-body-secondary"><?= $time ?></small></span>
        <span class="d-flex flex-wrap align-items-center gap-2 text-end">
            <b class="pb-entry-name"><?= $entryName ?></b>
            <?= $entryEmailLink ?>
            <?= $statusBadge ?>
        </span>
    </header>
    <div class="card-body">
        <?= $entryText ?>
        <?= $answerHtml ?>
    </div>
</article>

<?php if ($storedAnswer !== '' && $storedBy !== '' && mb_strtolower($storedBy) !== mb_strtolower($adminName)) { ?>
<div id="pbAnswerOtherAuthor" class="alert alert-info">
    Die Antwort stammt von <b><?= e($storedBy) ?></b>. Wenn Sie sie ändern, erscheint sie künftig unter Ihrem Namen.
</div>
<?php } ?>

<form id="pbAnswerForm" action="?page=statement" method="post" novalidate>
<?= csrfField() ?>
<input type="hidden" name="id" value="<?= $id ?>">

<div class="mb-3">
    <label for="pb_statement" class="form-label">Ihre Antwort</label>
    <textarea id="pb_statement" name="edit_statement" rows="4" maxlength="5000" class="form-control<?= $answerError !== '' ? ' is-invalid' : '' ?>"><?= e($edit_statement) ?></textarea>
    <?php if ($answerError !== '') { ?>
    <div class="invalid-feedback"><?= e($answerError) ?></div>
    <?php } ?>
    <div class="form-text">Im Gästebuch steht davor „Antwort von <?= e($adminName) ?>:“. Ein leeres Feld entfernt die Antwort.</div>
    <details class="mt-2">
        <summary class="small">Hilfe zu Formatierung und Smileys</summary>
        <div class="mt-2"><?= pb_render_help(true, ($config_smilies ?? 'N') === 'Y', '../smilies/') ?></div>
    </details>
</div>

<div class="d-flex flex-wrap gap-2">
    <button type="submit" id="pbAnswerSubmit" name="action" value="save" class="btn btn-primary">Antwort speichern</button>
    <?php if ($storedAnswer !== '') { ?>
    <button type="submit" id="pbAnswerDelete" name="action" value="delete" class="btn btn-outline-danger">Antwort entfernen</button>
    <?php } ?>
</div>
</form>

<?php
pb_admin_card_close();
