<?php
/**
 * PowerBook - PHP Guestbook System
 * Entry Release/Approval
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
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
/** @var string $db_statement */

// Check permission
if (($admin_session['release'] ?? 'N') !== 'Y') {
    echo '<div style="color: #FF6666; padding: 20px;">Sie haben keine Berechtigung für die Eintrags-Freischaltung.</div>';
    return;
}

$message = '';
$messageType = '';
$action = $_POST['action'] ?? '';

// Release all entries
if ($action === 'release_all' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $stmt = $pdo->prepare("UPDATE {$pb_entries} SET status = 'R' WHERE status = 'U'");
    $stmt->execute();
    $affected = $stmt->rowCount();
    $message = "Alle Einträge ({$affected}) erfolgreich freigeschaltet.";
    $messageType = 'success';
    regenerateCsrfToken();
}

// Release single entry
if ($action === 'release_one' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $entry_id = (int)($_POST['entry_id'] ?? 0);
    if ($entry_id > 0) {
        $stmt = $pdo->prepare("UPDATE {$pb_entries} SET status = 'R' WHERE id = ?");
        $stmt->execute([$entry_id]);
        $message = 'Eintrag erfolgreich freigeschaltet.';
        $messageType = 'success';
    } else {
        $message = 'Bitte wählen Sie einen Eintrag aus.';
        $messageType = 'error';
    }
    regenerateCsrfToken();
}

// Get unreleased entries
$stmt = $pdo->prepare("SELECT * FROM {$pb_entries} WHERE status = 'U' ORDER BY id DESC");
$stmt->execute();
$unreleasedEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
$count_unreleased = count($unreleasedEntries);
?>

<tr><td bgcolor="#3F5070" align="center">
    <b class="headline">E I N T R Ä G E &nbsp; &nbsp; F R E I S C H A L T E N</b>
</td></tr>

<tr><td bgcolor="#001F3F" valign="top">

<?php if (!empty($message)): ?>
<div style="padding: 10px; margin: 10px 0; background: <?= $messageType === 'success' ? '#003300' : '#330000' ?>; border: 1px solid <?= $messageType === 'success' ? '#00FF00' : '#FF0000' ?>;">
    <?= e($message) ?>
</div>
<?php endif; ?>

<?php if ($count_unreleased === 0): ?>
<div align="center">
    <p>Keine Einträge zum Freischalten vorhanden!</p>
</div>
<?php else: ?>

<p>
Um einen Eintrag freizuschalten (d.h., ihn allen Besuchern sichtbar zu machen),
wählen Sie einen Eintrag aus und klicken Sie auf "Eintrag freischalten".
Um alle Einträge auf einmal freizuschalten, klicken Sie auf "Alle freischalten".
</p>

<div align="center">
    <?php if ($count_unreleased === 1): ?>
        <p>Es gibt <b>einen</b> nicht freigegebenen Eintrag.</p>
    <?php else: ?>
        <p>Es gibt <b><?= $count_unreleased ?></b> nicht freigegebene Einträge.</p>
    <?php endif; ?>

    <form action="?page=release" method="post" style="margin-bottom: 20px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="release_all">
        <input type="submit" value="Alle freischalten" style="background: #003366; padding: 5px 15px;">
    </form>
</div>

<form action="?page=release" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="release_one">

<?php foreach ($unreleasedEntries as $entry):
    // Process entry for display
    include __DIR__ . '/entry.inc.php';
?>
<table width="100%" border="0">
    <tr>
        <td width="40">
            <input type="radio" name="entry_id" value="<?= (int)$entry['id'] ?>">
        </td>
        <td align="left" bgcolor="#001329">
            <?= $show_icon ?><b><?= $date ?></b>, <small><?= $time ?></small> -
            <a href="?page=edit&amp;edit_id=<?= (int)$entry['id'] ?>">Bearbeiten/Löschen</a>
        </td>
        <td align="right" width="121" bgcolor="#001329">
            <?= $email_name ?>
        </td>
    </tr>
    <tr>
        <td width="40">&nbsp;</td>
        <td valign="top" bgcolor="#001930">
            <?= $entry['text'] ?>
        </td>
        <td width="121" align="right" valign="top" bgcolor="#001329">
            <?= $url ?><br>
            <?= $show_icq ?>
        </td>
    </tr>
</table>
<br>
<?php endforeach; ?>

<div align="center">
    <input type="submit" value="Ausgewählten Eintrag freischalten">
</div>
</form>

<?php endif; ?>

</td></tr>
