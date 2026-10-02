<?php
/**
 * PowerBook - PHP Guestbook System
 * AdminCenter: Einträge freischalten
 *
 * Liste der wartenden Einträge mit Auswahl. „Ausgewählte freischalten“
 * veröffentlicht nur die ausgewählten Einträge, „Ausgewählte löschen“ fragt
 * auf einer eigenen Seite nach und entfernt dann Spam und unerwünschte
 * Einträge. Recht: Einträge freischalten.
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
$admin_session ??= [];

if (!pb_admin_can($admin_session, 'release')) {
    pb_admin_card_open('Einträge freischalten', 'pbRelease');
    echo pb_admin_alert('Sie haben keine Berechtigung, Einträge freizuschalten.', 'danger');
    pb_admin_card_close();

    return;
}

$canEdit = pb_admin_can($admin_session, 'entries');
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$selectedIds = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $rawId) {
        $selectedId = is_scalar($rawId) ? (int) $rawId : 0;
        if ($selectedId > 0) {
            $selectedIds[$selectedId] = $selectedId;
        }
    }
}
$selectedIds = array_values($selectedIds);
$confirmEntries = [];

if ($action !== '' && !validateCsrfToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
    logCsrfFailure('admin_release');
    pb_admin_flash('danger', pb_admin_csrf_message());
    $action = '';
}

if (in_array($action, ['release', 'delete_confirm', 'delete'], true) && $selectedIds === []) {
    pb_admin_flash('danger', 'Bitte wählen Sie mindestens einen Eintrag aus.');
    $action = '';
}

if ($action !== '') {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));

    try {
        if ($action === 'release') {
            $stmt = $pdo->prepare("UPDATE {$pb_entries} SET status = 'R' WHERE status = 'U' AND id IN ({$placeholders})");
            $stmt->execute($selectedIds);
            $done = $stmt->rowCount();
            if ($done === 0) {
                pb_admin_flash('info', 'Die ausgewählten Einträge waren schon freigeschaltet oder gelöscht.');
            } elseif ($done === 1) {
                pb_admin_flash('success', 'Ein Eintrag wurde freigeschaltet. Er steht jetzt im Gästebuch.');
            } else {
                pb_admin_flash('success', $done . ' Einträge wurden freigeschaltet. Sie stehen jetzt im Gästebuch.');
            }
            pb_admin_redirect('?page=release');
        }

        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM {$pb_entries} WHERE status = 'U' AND id IN ({$placeholders})");
            $stmt->execute($selectedIds);
            $done = $stmt->rowCount();
            if ($done === 0) {
                pb_admin_flash('info', 'Die ausgewählten Einträge gibt es nicht mehr oder sie sind schon freigeschaltet.');
            } elseif ($done === 1) {
                pb_admin_flash('success', 'Ein Eintrag wurde gelöscht.');
            } else {
                pb_admin_flash('success', $done . ' Einträge wurden gelöscht.');
            }
            pb_admin_redirect('?page=release');
        }

        if ($action === 'delete_confirm') {
            $stmt = $pdo->prepare("SELECT * FROM {$pb_entries} WHERE status = 'U' AND id IN ({$placeholders}) ORDER BY date DESC, id DESC");
            $stmt->execute($selectedIds);
            $confirmEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($confirmEntries === []) {
                pb_admin_flash('info', 'Die ausgewählten Einträge gibt es nicht mehr oder sie sind schon freigeschaltet.');
            }
        }
    } catch (PDOException $e) {
        logDbError('Release ' . $action . ': ' . $e->getMessage());
        pb_admin_flash('danger', 'Die Aktion ist fehlgeschlagen (Datenbankfehler).');
        $confirmEntries = [];
    }
}

pb_admin_card_open('Einträge freischalten', 'pbRelease');

// --- Rückfrage vor dem Löschen ---------------------------------------------
if ($confirmEntries !== []) {
    $confirmCount = count($confirmEntries);
    ?>
<div id="pbDeleteQuestion" class="alert alert-danger" role="alert">
    <h3 class="h6 mb-2"><?= $confirmCount === 1 ? 'Diesen Eintrag wirklich löschen?' : 'Diese ' . $confirmCount . ' Einträge wirklich löschen?' ?></h3>
    <p class="mb-2"><?= $confirmCount === 1
        ? 'Der Eintrag wird endgültig gelöscht und erscheint nie im Gästebuch.'
        : 'Die Einträge werden endgültig gelöscht und erscheinen nie im Gästebuch.' ?> Das lässt sich nicht rückgängig machen.</p>
    <ul class="mb-3">
        <?php foreach ($confirmEntries as $entry) {
            $excerpt = trim(preg_replace('/\s+/u', ' ', (string) ($entry['text'] ?? '')) ?? '');
            if (mb_strlen($excerpt) > 80) {
                $excerpt = rtrim(mb_substr($excerpt, 0, 80)) . ' …';
            }
            include __DIR__ . '/entry.inc.php';
            ?>
        <li><b><?= $entryName ?></b>, <?= $date ?>: <i><?= e($excerpt) ?></i></li>
        <?php } ?>
    </ul>
    <div class="d-flex flex-wrap gap-2">
        <form action="?page=release" method="post" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <?php foreach ($confirmEntries as $confirmEntry) { ?>
            <input type="hidden" name="ids[]" value="<?= (int) $confirmEntry['id'] ?>">
            <?php } ?>
            <button type="submit" id="pbDeleteConfirm" class="btn btn-danger">Ja, endgültig löschen</button>
        </form>
        <a id="pbDeleteCancel" class="btn btn-outline-secondary" href="?page=release">Abbrechen</a>
    </div>
</div>
    <?php
    pb_admin_card_close();

    return;
}

// --- Liste der wartenden Einträge -------------------------------------------
$stmt = $pdo->query("SELECT * FROM {$pb_entries} WHERE status = 'U' ORDER BY date DESC, id DESC");
$pendingEntries = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$pendingCount = count($pendingEntries);

if ($pendingCount === 0) { ?>
<div id="pbReleaseEmpty" class="alert alert-info" role="status">Keine Einträge warten auf Freischaltung.</div>
<?php
    pb_admin_card_close();

    return;
}
?>

<p>
Diese Einträge sind im Gästebuch noch nicht zu sehen. Wählen Sie Einträge aus und klicken Sie auf
„Ausgewählte freischalten“. Spam und unerwünschte Einträge entfernen Sie mit „Ausgewählte löschen“.
</p>

<p id="pbReleaseCount">
    <?= $pendingCount === 1 ? 'Es wartet' : 'Es warten' ?>
    <span class="badge text-bg-warning fs-6"><?= $pendingCount ?></span>
    <?= $pendingCount === 1 ? 'Eintrag' : 'Einträge' ?> auf Freischaltung.
</p>

<form id="pbReleaseForm" action="?page=release" method="post">
<?= csrfField() ?>

<?php foreach ($pendingEntries as $entry) {
    include __DIR__ . '/entry.inc.php';
    $isChecked = in_array($entryId, $selectedIds, true);
    ?>
<article id="pbEntry<?= $entryId ?>" class="card pb-entry-card shadow-sm mb-3 border-warning" data-status="U">
    <header class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="d-flex flex-wrap align-items-center gap-3">
            <span class="form-check mb-0">
                <input id="pbReleaseCheck<?= $entryId ?>" class="form-check-input" type="checkbox" name="ids[]" value="<?= $entryId ?>"<?= $isChecked ? ' checked' : '' ?>>
                <label for="pbReleaseCheck<?= $entryId ?>" class="form-check-label">Auswählen</label>
            </span>
            <span><?= $show_icon ?><b><?= $date ?></b>, <small class="text-body-secondary"><?= $time ?></small></span>
        </span>
        <span class="d-flex flex-wrap align-items-center gap-2 text-end">
            <b class="pb-entry-name"><?= $entryName ?></b>
            <?= $entryEmailLink ?>
            <?= $statusBadge ?>
        </span>
    </header>
    <div class="card-body"><?= $entryText ?><?= $answerHtml ?></div>
    <footer class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-body-secondary">IP-Adresse: <code><?= $ip ?></code> &middot; Homepage: <?= $homepage_link ?></small>
        <?php if ($canEdit) { ?>
        <a id="pbEntryEdit<?= $entryId ?>" class="btn btn-outline-primary btn-sm" href="?page=edit&amp;edit_id=<?= $entryId ?>&amp;return=release">Bearbeiten</a>
        <?php } ?>
    </footer>
</article>
<?php } ?>

<div class="d-flex flex-wrap gap-2 mt-3">
    <button type="submit" id="pbReleaseSelected" name="action" value="release" class="btn btn-success">Ausgewählte freischalten</button>
    <button type="submit" id="pbDeleteSelected" name="action" value="delete_confirm" class="btn btn-outline-danger">Ausgewählte löschen</button>
</div>
</form>

<?php
pb_admin_card_close();
