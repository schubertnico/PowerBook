<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Entry Listing
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
if (($admin_session['entries'] ?? 'N') !== 'Y') {
    pb_admin_card_open('Administration: Einträge');
    echo pb_admin_alert('Sie haben keine Berechtigung für die Eintrags-Verwaltung.', 'danger');
    pb_admin_card_close();

    return;
}

// Pagination
$tmp_start = max(0, (int) ($_GET['tmp_start'] ?? 0));
$perPage = 15;

// Get entries
$stmt = $pdo->prepare("SELECT * FROM {$pb_entries} ORDER BY id DESC LIMIT :start, :limit");
$stmt->bindValue(':start', $tmp_start, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->execute();
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count_entry = count($entries);

// Get total count for pagination
$stmt = $pdo->query("SELECT COUNT(*) FROM {$pb_entries}");
$count_pages = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
$tmp_pages = (int) ceil($count_pages / $perPage);

pb_admin_card_open('Administration: Einträge');
?>

<p>
Um Einträge zu bearbeiten oder zu löschen, klicken Sie bitte auf den Link "Bearbeiten/Löschen" bei dem gewünschten Eintrag.
Um ein Statement zu schreiben, klicken Sie bitte auf den "Statement"-Link.
</p>

<?php if ($count_pages === 0) { ?>
<div class="alert alert-info" role="status"><i>Keine Einträge vorhanden.</i></div>
<?php } else { ?>

<?php include __DIR__ . '/pages.inc.php'; ?>

<?php foreach ($entries as $entry) {
    // Process entry for display
    include __DIR__ . '/entry.inc.php';
    ?>
<article class="card pb-entry-card shadow-sm mb-3">
    <header class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <span><?= $show_icon ?><b><?= $date ?></b>, <small class="text-body-secondary"><?= $time ?></small></span>
        <span class="text-end"><?= $email_name ?></span>
    </header>
    <div class="card-body">
        <?= $entry['text'] ?>
    </div>
    <footer class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-body-secondary">IP: <code><?= $ip ?></code></small>
        <div class="d-flex flex-wrap gap-2">
            <?= $url ?>
            <?= $show_icq ?>
            <a class="btn btn-outline-primary btn-sm" href="?page=edit&amp;edit_id=<?= (int) $entry['id'] ?>">Bearbeiten/Löschen</a>
            <a class="btn btn-outline-secondary btn-sm" href="?page=statement&amp;id=<?= (int) $entry['id'] ?>">Statement</a>
        </div>
    </footer>
</article>
<?php } ?>

<?php include __DIR__ . '/pages.inc.php'; ?>

<?php }

pb_admin_card_close();
