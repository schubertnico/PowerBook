<?php
/**
 * PowerBook - PHP Guestbook System
 * Entry Release/Approval
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
/** @var string $db_statement */

// Check permission
if (($admin_session['release'] ?? 'N') !== 'Y') {
    pb_admin_card_open('Einträge freischalten');
    echo pb_admin_alert('Sie haben keine Berechtigung für die Eintrags-Freischaltung.', 'danger');
    pb_admin_card_close();

    return;
}

$message = '';
$messageType = '';
$action = $_POST['action'] ?? '';

// Release all entries
if ($action === 'release_all' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    try {
        $stmt = $pdo->prepare("UPDATE {$pb_entries} SET status = 'R' WHERE status = 'U'");
        $stmt->execute();
        $affected = $stmt->rowCount();
        $message = "Alle Einträge ({$affected}) erfolgreich freigeschaltet.";
        $messageType = 'success';
    } catch (PDOException $e) {
        logDbError('Release all entries: ' . $e->getMessage());
        $message = 'Datenbankfehler beim Freischalten der Einträge.';
        $messageType = 'error';
    }
    regenerateCsrfToken();
}

// Release single entry
if ($action === 'release_one' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $entry_id = (int) ($_POST['entry_id'] ?? 0);
    if ($entry_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE {$pb_entries} SET status = 'R' WHERE id = ?");
            $stmt->execute([$entry_id]);
            $message = 'Eintrag erfolgreich freigeschaltet.';
            $messageType = 'success';
        } catch (PDOException $e) {
            logDbError('Release single entry: ' . $e->getMessage());
            $message = 'Datenbankfehler beim Freischalten des Eintrags.';
            $messageType = 'error';
        }
    } else {
        $message = 'Bitte wählen Sie einen Eintrag aus.';
        $messageType = 'error';
    }
    regenerateCsrfToken();
}

// Get unreleased entries
try {
    $stmt = $pdo->prepare("SELECT * FROM {$pb_entries} WHERE status = 'U' ORDER BY id DESC");
    $stmt->execute();
    $unreleasedEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count_unreleased = count($unreleasedEntries);
} catch (PDOException $e) {
    logDbError('Load unreleased entries: ' . $e->getMessage());
    $unreleasedEntries = [];
    $count_unreleased = 0;
    if (empty($message)) {
        $message = 'Datenbankfehler beim Laden der Einträge.';
        $messageType = 'error';
    }
}

pb_admin_card_open('Einträge freischalten');

if (!empty($message)) {
    echo pb_admin_alert(e($message), pb_admin_message_type($messageType));
}

if ($count_unreleased === 0) { ?>
<div class="alert alert-info text-center" role="status"><b>Keine Einträge zum Freischalten vorhanden!</b></div>
<?php } else { ?>

<p>
Um einen Eintrag freizuschalten (d.h., ihn allen Besuchern sichtbar zu machen),
wählen Sie einen Eintrag aus und klicken Sie auf "Eintrag freischalten".
Um alle Einträge auf einmal freizuschalten, klicken Sie auf "Alle freischalten".
</p>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <?php if ($count_unreleased === 1) { ?>
            Es gibt <span class="badge bg-warning text-dark fs-6">1</span> nicht freigegebenen Eintrag.
        <?php } else { ?>
            Es gibt <span class="badge bg-warning text-dark fs-6"><?= $count_unreleased ?></span> nicht freigegebene Einträge.
        <?php } ?>
    </div>
    <form action="?page=release" method="post" class="mb-0">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="release_all">
        <button type="submit" class="btn btn-success">Alle freischalten</button>
    </form>
</div>

<form action="?page=release" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="release_one">

<?php foreach ($unreleasedEntries as $entry) {
    // Process entry for display
    include __DIR__ . '/entry.inc.php';
    ?>
<article class="card pb-entry-card shadow-sm mb-3">
    <header class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center">
            <div class="form-check me-3 mb-0">
                <input id="entry_<?= (int) $entry['id'] ?>" class="form-check-input" type="radio" name="entry_id" value="<?= (int) $entry['id'] ?>">
                <label for="entry_<?= (int) $entry['id'] ?>" class="form-check-label visually-hidden">Eintrag <?= (int) $entry['id'] ?> auswählen</label>
            </div>
            <span><?= $show_icon ?><b><?= $date ?></b>, <small class="text-body-secondary"><?= $time ?></small></span>
        </div>
        <span class="text-end"><?= $email_name ?></span>
    </header>
    <div class="card-body"><?= $entry['text'] ?></div>
    <footer class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <a class="btn btn-outline-primary btn-sm" href="?page=edit&amp;edit_id=<?= (int) $entry['id'] ?>">Bearbeiten/Löschen</a>
        <div class="d-flex flex-wrap gap-2"><?= $url ?> <?= $show_icq ?></div>
    </footer>
</article>
<?php } ?>

<div class="d-flex justify-content-center">
    <button type="submit" class="btn btn-primary">Ausgewählten Eintrag freischalten</button>
</div>
</form>

<?php }

pb_admin_card_close();
