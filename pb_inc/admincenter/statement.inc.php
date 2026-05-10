<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Statements
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

// Check permission
if (($admin_session['entries'] ?? 'N') !== 'Y') {
    pb_admin_card_open('Statements');
    echo pb_admin_alert('Sie haben keine Berechtigung für Statements.', 'danger');
    pb_admin_card_close();

    return;
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$message = '';
$messageType = '';
$showForm = true;

if ($id === 0) {
    // BUG-005: Echter Link statt history.back().
    $message = 'Es ist ein Fehler aufgetreten: <b>ID unbekannt!</b> <a class="alert-link" href="?page=entries">Zur Eintragsliste</a>';
    $messageType = 'error';
    $showForm = false;
}

// Update statement
if ($action === 'update' && $id > 0 && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $edit_statement = $_POST['edit_statement'] ?? '';

    try {
        $stmt = $pdo->prepare("UPDATE {$pb_entries} SET statement = ?, statement_by = ? WHERE id = ?");
        $stmt->execute([$edit_statement, $admin_session['name'] ?? 'Admin', $id]);

        $message = empty($edit_statement) ? 'Statement wurde gelöscht.' : 'Statement wurde erfolgreich aktualisiert.';
        $messageType = 'success';
        $showForm = false;
    } catch (PDOException $e) {
        logDbError('Statement update: ' . $e->getMessage());
        $message = 'Datenbankfehler beim Speichern des Statements.';
        $messageType = 'error';
    }
    regenerateCsrfToken();
}

// Load entry data
$entry = null;
if ($showForm && $id > 0) {
    try {
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
    } catch (PDOException $e) {
        logDbError('Statement entry load: ' . $e->getMessage());
        $message = 'Datenbankfehler beim Laden des Eintrags.';
        $messageType = 'error';
        $showForm = false;
    }
}

// Process statement for preview display
if (!function_exists('formatStatement')) {
    function formatStatement(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = str_replace("\n", '<br>', $text);

        // BBCode
        $text = (string) preg_replace('/\[b\]/i', '<b>', $text);
        $text = (string) preg_replace('/\[\/b\]/i', '</b>', $text);
        $text = (string) preg_replace('/\[u\]/i', '<u>', $text);
        $text = (string) preg_replace('/\[\/u\]/i', '</u>', $text);
        $text = (string) preg_replace('/\[i\]/i', '<i>', $text);
        $text = (string) preg_replace('/\[\/i\]/i', '</i>', $text);
        $text = (string) preg_replace('/\[small\]/i', '<small>', $text);
        $text = (string) preg_replace('/\[\/small\]/i', '</small>', $text);
        $text = (string) preg_replace('/(https?:\/\/[-~a-zA-Z0-9\/\.\+%&\?|=:]+)([^-~a-zA-Z0-9\/\.\+%&\?|=:]|$)/i', '<a href="$1" target="_blank" rel="noopener">$1</a>$2', $text);
        $text = (string) preg_replace('/(www\.[-~a-zA-Z0-9\/\.\+%&\?|=:]+)([^-~a-zA-Z0-9\/\.\+%&\?|=:]|$)/i', '<a href="http://$1" target="_blank" rel="noopener">$1</a>$2', $text);

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

        return str_replace(array_keys($smilies), array_values($smilies), $text);
    }
}

pb_admin_card_open('Statements');

if (!empty($message)) {
    echo pb_admin_alert($message, pb_admin_message_type($messageType));
    if ($messageType === 'success') {
        echo '<p><a class="btn btn-outline-secondary btn-sm" href="?page=entries">Zurück zur Eintrags-Übersicht</a></p>';
    }
}

if ($showForm && $entry) { ?>
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

<article class="card pb-entry-card shadow-sm mb-3">
    <header class="card-header d-flex flex-wrap justify-content-between align-items-center">
        <span><?= $show_icon ?><b><?= $date ?></b>, <small class="text-body-secondary"><?= $time ?></small></span>
        <span class="text-end"><?= $email_name ?></span>
    </header>
    <div class="card-body">
        <?= $entry['text'] ?>
        <?php if (!empty($statementPreview)) { ?>
        <hr class="my-3">
        <div class="fst-italic"><b><?= $currentBy ?></b>'s Statement:<br><br><?= $statementPreview ?></div>
        <?php } ?>
    </div>
    <footer class="card-footer d-flex flex-wrap justify-content-end gap-3">
        <?= $url ?>
        <?= $show_icq ?>
    </footer>
</article>

<form action="?page=statement" method="post" class="mt-4" novalidate>
<?= csrfField() ?>
<input type="hidden" name="action" value="update">
<input type="hidden" name="id" value="<?= $id ?>">

<div class="mb-3">
    <label for="pb_statement" class="form-label">
        Statement
        <?php if (($config_text_format ?? 'N') === 'Y') { ?>
        &nbsp;<small><a href="../text-help.html" target="_blank" rel="noopener noreferrer">Formatierungs-Hilfe</a></small>
        <?php } ?>
    </label>
    <textarea id="pb_statement" name="edit_statement" rows="8" class="form-control"><?= e($edit_statement ?? '') ?></textarea>
    <div class="form-text">Leer lassen, um ein vorhandenes Statement zu löschen.</div>
</div>

<div class="d-flex flex-wrap gap-2">
    <button type="submit" class="btn btn-primary">Statement speichern</button>
    <button type="reset" class="btn btn-outline-secondary">Zurücksetzen</button>
</div>
</form>

<p class="text-body-secondary mt-3 mb-0"><small>
<b>Hinweis:</b> Das Statement wird unter dem Namen "<b><?= e($admin_session['name'] ?? 'Admin') ?></b>" gespeichert.
</small></p>

<?php }

pb_admin_card_close();
