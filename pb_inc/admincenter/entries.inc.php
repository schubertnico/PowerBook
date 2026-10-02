<?php
/**
 * PowerBook - PHP Guestbook System
 * AdminCenter: Liste aller Einträge
 *
 * Neueste zuerst, mit Status, Antwort, E-Mail-Adresse des Gastes und den
 * Knöpfen „Bearbeiten“ und „Antworten“. Recht: Einträge bearbeiten und löschen.
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
/** @var string $config_statements */
$admin_session ??= [];

if (!pb_admin_can($admin_session, 'entries')) {
    pb_admin_card_open('Einträge', 'pbEntries');
    echo pb_admin_alert('Sie haben keine Berechtigung, Einträge zu bearbeiten.', 'danger');
    pb_admin_card_close();

    return;
}

// Die Liste zeigt immer 15 Einträge je Seite – unabhängig von der Einstellung
// für das Gästebuch, damit man im AdminCenter mehr auf einen Blick sieht.
$perPage = 15;
$tmp_start = max(0, (int) ($_GET['tmp_start'] ?? 0));
$tmp_start -= $tmp_start % $perPage;

$counts = pb_admin_count_entries($pdo, $pb_entries);
$count_pages = $counts['public'] + $counts['pending'];

$stmt = $pdo->prepare("SELECT * FROM {$pb_entries} ORDER BY date DESC, id DESC LIMIT :start, :limit");
$stmt->bindValue(':start', $tmp_start, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->execute();
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

pb_admin_card_open('Einträge', 'pbEntries');
?>

<p>
Hier stehen alle Einträge, die neuesten zuerst. Mit „Bearbeiten“ ändern oder löschen Sie einen Eintrag,
mit „Antworten“ schreiben Sie eine Antwort, die im Gästebuch unter dem Eintrag erscheint.
</p>

<?php if ($counts['pending'] > 0) { ?>
<p id="pbEntriesPendingHint" class="alert alert-warning py-2">
    <?= e(pb_admin_plural($counts['pending'], 'Eintrag wartet', 'Einträge warten')) ?> auf Freischaltung und
    <?= $counts['pending'] === 1 ? 'ist' : 'sind' ?> im Gästebuch noch nicht zu sehen.
    <?php if (pb_admin_can($admin_session, 'release')) { ?>
    <a class="alert-link" href="?page=release">Zur Freischaltung</a>
    <?php } ?>
</p>
<?php } ?>

<?php if (($config_statements ?? 'Y') === 'N') { ?>
<p class="text-body-secondary"><small>Antworten sind im Gästebuch zurzeit ausgeblendet (Konfiguration). Hier im AdminCenter sehen Sie sie trotzdem.</small></p>
<?php } ?>

<?php if ($count_pages === 0) { ?>
<div id="pbEntriesEmpty" class="alert alert-info" role="status">Es gibt noch keine Einträge.</div>
<?php } elseif ($entries === []) { ?>
<div id="pbEntriesEmpty" class="alert alert-info" role="status">
    Auf dieser Seite stehen keine Einträge. <a class="alert-link" href="?page=entries">Zur ersten Seite</a>
</div>
<?php } else { ?>

<?php
    $pagerId = 'pbEntriesPagerTop';
    include __DIR__ . '/pages.inc.php';

    foreach ($entries as $entry) {
        include __DIR__ . '/entry.inc.php';
        ?>
<article id="pbEntry<?= $entryId ?>" class="card pb-entry-card shadow-sm mb-3<?= $isPending ? ' border-warning' : '' ?>" data-status="<?= $isPending ? 'U' : 'R' ?>">
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
    <footer class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-body-secondary">IP-Adresse: <code><?= $ip ?></code> &middot; Homepage: <?= $homepage_link ?></small>
        <div class="d-flex flex-wrap gap-2">
            <a id="pbEntryAnswer<?= $entryId ?>" class="btn btn-outline-secondary btn-sm" href="?page=statement&amp;id=<?= $entryId ?>"><?= $answerText !== '' ? 'Antwort bearbeiten' : 'Antworten' ?></a>
            <a id="pbEntryEdit<?= $entryId ?>" class="btn btn-outline-primary btn-sm" href="?page=edit&amp;edit_id=<?= $entryId ?>">Bearbeiten</a>
        </div>
    </footer>
</article>
<?php
    }

    $pagerId = 'pbEntriesPagerBottom';
    include __DIR__ . '/pages.inc.php';
}

pb_admin_card_close();
