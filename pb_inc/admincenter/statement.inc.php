<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Statements
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

// Check permission
if (($admin_session['entries'] ?? 'N') !== 'Y') {
    echo '<div style="color: #FF6666; padding: 20px;">Sie haben keine Berechtigung für Statements.</div>';
    return;
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$message = '';
$messageType = '';
$showForm = true;

if ($id === 0) {
    $message = '<a href="javascript:history.back()">Es ist ein Fehler aufgetreten: <b>ID unbekannt!</b></a>';
    $showForm = false;
}

// Update statement
if ($action === 'update' && $id > 0 && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $edit_statement = $_POST['edit_statement'] ?? '';

    $stmt = $pdo->prepare("UPDATE {$pb_entries} SET statement = ?, statement_by = ? WHERE id = ?");
    $stmt->execute([$edit_statement, $admin_session['name'] ?? 'Admin', $id]);

    $message = empty($edit_statement) ? 'Statement wurde gelöscht.' : 'Statement wurde erfolgreich aktualisiert.';
    $messageType = 'success';
    $showForm = false;
    regenerateCsrfToken();
}

// Load entry data
$entry = null;
if ($showForm && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM {$pb_entries} WHERE id = ?");
    $stmt->execute([$id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        $message = 'Eintrag nicht gefunden!';
        $messageType = 'error';
        $showForm = false;
    } else {
        $edit_statement = $entry['statement'] ?? '';
        $statement_by = $entry['statement_by'] ?? '';
    }
}

// Process statement for preview display
function formatStatement(string $text): string {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = str_replace("\n", "<br>", $text);

    // BBCode
    $text = preg_replace('/\[b\]/i', '<b>', $text);
    $text = preg_replace('/\[\/b\]/i', '</b>', $text);
    $text = preg_replace('/\[u\]/i', '<u>', $text);
    $text = preg_replace('/\[\/u\]/i', '</u>', $text);
    $text = preg_replace('/\[i\]/i', '<i>', $text);
    $text = preg_replace('/\[\/i\]/i', '</i>', $text);
    $text = preg_replace('/\[small\]/i', '<small>', $text);
    $text = preg_replace('/\[\/small\]/i', '</small>', $text);
    $text = preg_replace('/(https?:\/\/[-~a-zA-Z0-9\/\.\+%&\?|=:]+)([^-~a-zA-Z0-9\/\.\+%&\?|=:]|$)/i', '<a href="$1" target="_blank" rel="noopener">$1</a>$2', $text);
    $text = preg_replace('/(www\.[-~a-zA-Z0-9\/\.\+%&\?|=:]+)([^-~a-zA-Z0-9\/\.\+%&\?|=:]|$)/i', '<a href="http://$1" target="_blank" rel="noopener">$1</a>$2', $text);

    // Smilies
    $smilies = [
        '?:)' => '<img src="../smilies/confused.gif" alt=":confused:">',
        '!:)' => '<img src="../smilies/shock.gif" alt=":shock:">',
        ';(' => '<img src="../smilies/sad1.gif" alt=":sad:">',
        ':(' => '<img src="../smilies/sad2.gif" alt=":sad:">',
        ':X' => '<img src="../smilies/sad3.gif" alt=":sad:">',
        ':)' => '<img src="../smilies/happy1.gif" alt=":happy:">',
        ':P' => '<img src="../smilies/happy2.gif" alt=":tongue:">',
        ';)' => '<img src="../smilies/happy3.gif" alt=":wink:">',
        ':D' => '<img src="../smilies/happy4.gif" alt=":grin:">',
        ';o)' => '<img src="../smilies/happy5.gif" alt=":happy:">',
    ];
    $text = str_replace(array_keys($smilies), array_values($smilies), $text);

    return $text;
}
?>

<tr><td bgcolor="#3F5070" align="center">
    <b class="headline">S T A T E M E N T S</b>
</td></tr>

<tr><td bgcolor="#001F3F" valign="top">

<?php if (!empty($message)): ?>
<div style="padding: 10px; margin: 10px 0; background: <?= $messageType === 'success' ? '#003300' : '#330000' ?>; border: 1px solid <?= $messageType === 'success' ? '#00FF00' : '#FF0000' ?>;">
    <?= $message ?>
</div>
<?php if ($messageType === 'success'): ?>
<p><a href="?page=entries">Zurück zur Eintrags-Übersicht</a></p>
<?php endif; ?>
<?php endif; ?>

<?php if ($showForm && $entry): ?>
<p>
Sie können mit dem untenstehenden Formular Statements schreiben oder bearbeiten.
Um ein Statement zu löschen, lassen Sie das Formular einfach leer.
Bitte beachten Sie, dass es nur <b>ein</b> Statement von nur <b>einem</b> Admin geben kann!
</p>

<p>Dies ist der Eintrag, zu welchem Ihr Statement hinzugefügt werden wird:</p>

<?php
// Display the entry
$db_statement = 'N'; // Don't show statement in preview
include __DIR__ . '/entry.inc.php';

// Show current statement if exists
$statementPreview = '';
if (!empty($edit_statement)) {
    $statementPreview = formatStatement($edit_statement);
    $currentBy = !empty($statement_by) ? e($statement_by) : e($admin_session['name'] ?? 'Admin');
}
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
            <?= $entry['text'] ?>
            <?php if (!empty($statementPreview)): ?>
            <br><br><hr noshade>
            <i><b><?= $currentBy ?></b>'s Statement:<br><br><?= $statementPreview ?></i>
            <?php endif; ?>
        </td>
        <td width="121" align="right" valign="top" bgcolor="#001329">
            <?= $url ?><br>
            <?= $show_icq ?>
        </td>
    </tr>
</table>
<br><br>

<div align="center">
<form action="?page=statement" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="update">
<input type="hidden" name="id" value="<?= $id ?>">

<table border="0">
    <tr bgcolor="#001329">
        <td width="120" valign="top">
            Statement:
            <?php if (($config_text_format ?? 'N') === 'Y'): ?>
            <br><small><a href="../text-help.html" target="_blank">Hilfe</a></small>
            <?php endif; ?>
        </td>
        <td>
            <textarea name="edit_statement" rows="10" cols="50"><?= e($edit_statement ?? '') ?></textarea>
        </td>
    </tr>
    <tr bgcolor="#001930">
        <td width="120">&nbsp;</td>
        <td>
            <input type="submit" value="Statement speichern">
            <input type="reset" value="Zurücksetzen">
        </td>
    </tr>
</table>
</form>
</div>

<p><small>
<b>Hinweis:</b> Das Statement wird unter dem Namen "<b><?= e($admin_session['name'] ?? 'Admin') ?></b>" gespeichert.
Lassen Sie das Textfeld leer, um ein vorhandenes Statement zu löschen.
</small></p>

<?php endif; ?>

</td></tr>
