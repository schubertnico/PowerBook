<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Entry Listing
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
/** @var string $db_statement */

// Check permission
if (($admin_session['entries'] ?? 'N') !== 'Y') {
    echo '<div style="color: #FF6666; padding: 20px;">Sie haben keine Berechtigung für die Eintrags-Verwaltung.</div>';

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
?>

<tr><td bgcolor="#3F5070" align="center">
    <b class="headline">A D M I N I S T R A T I O N : &nbsp; E I N T R Ä G E</b>
</td></tr>

<tr><td bgcolor="#001F3F" valign="top">

<p>
Um Einträge zu bearbeiten oder zu löschen, klicken Sie bitte auf den Link "Bearbeiten/Löschen" bei dem gewünschten Eintrag.
Um ein Statement zu schreiben, klicken Sie bitte auf den "Statement"-Link.
</p>

<?php if ($count_pages === 0) { ?>
<p><i>Keine Einträge vorhanden.</i></p>
<?php } else { ?>

<?php include __DIR__ . '/pages.inc.php'; ?>

<?php foreach ($entries as $entry) {
    // Process entry for display
    include __DIR__ . '/entry.inc.php';
    ?>
<table width="100%" border="0">
    <tr>
        <td align="left" bgcolor="#001329">
            <?= $show_icon ?><b><?= $date ?></b>, <small><?= $time ?></small>
        </td>
        <td align="right" width="121" bgcolor="#001329">
            <?= $email_name ?>
        </td>
    </tr>
    <tr>
        <td valign="top" bgcolor="#001930">
            <?= $entry['text'] ?><br>
            <hr color="#001329">
            <div align="right"><small>
                IP: <b><?= $ip ?></b> |
                <a href="?page=edit&amp;edit_id=<?= (int) $entry['id'] ?>">Bearbeiten/Löschen</a> |
                <a href="?page=statement&amp;id=<?= (int) $entry['id'] ?>">Statement</a>
            </small></div>
        </td>
        <td width="121" align="right" valign="top" bgcolor="#001329">
            <?= $url ?><br>
            <?= $show_icq ?>
        </td>
    </tr>
</table>
<br>
<?php } ?>

<?php include __DIR__ . '/pages.inc.php'; ?>

<?php } ?>

</td></tr>
