<?php
/**
 * PowerBook - PHP Guestbook System
 * Main Guestbook Display and Entry Handler
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Include required files
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/functions.inc.php';
require_once __DIR__ . '/error-handler.inc.php';

// Get request parameters safely
$show_gb = $_GET['show_gb'] ?? $_POST['show_gb'] ?? 'yes';
$show_form = $_GET['show_form'] ?? $_POST['show_form'] ?? 'yes';
$preview = $_POST['preview'] ?? 'no';
$add_entry = $_POST['add_entry'] ?? 'no';
$search = $_GET['search'] ?? 'no';
$tmp_start = (int) ($_GET['tmp_start'] ?? 0);
$tmp_page = (int) ($_GET['tmp_page'] ?? 1);
$tmp_search = trim($_GET['tmp_search'] ?? $_POST['tmp_search'] ?? '');
$tmp_where = $_GET['tmp_where'] ?? $_POST['tmp_where'] ?? '';

// Form input variables
$name = trim($_POST['name'] ?? '');
$email2 = trim($_POST['email2'] ?? '');
$url = trim($_POST['url'] ?? '');
$icq2 = trim($_POST['icq2'] ?? '');
$text = $_POST['text'] ?? '';
$smilies2 = $_POST['smilies2'] ?? 'N';
$icon = $_POST['icon'] ?? '';

// Hidden form variables for preview submission
$name2 = trim($_POST['name2'] ?? '');
$text2 = $_POST['text2'] ?? '';
$url2 = trim($_POST['url2'] ?? '');
$icon2 = $_POST['icon2'] ?? '';

$installation_required = false;

if ($show_gb !== 'no') {
    // Build query based on search parameters
    $params = [];

    if ($tmp_search !== '' && $tmp_where === 'text') {
        $baseQuery = "SELECT * FROM {$pb_entries} WHERE status = 'R' AND text LIKE :search";
        $countQuery = "SELECT COUNT(*) FROM {$pb_entries} WHERE status = 'R' AND text LIKE :search";
        $params[':search'] = '%' . $tmp_search . '%';
    } elseif ($tmp_search !== '' && $tmp_where === 'name') {
        $baseQuery = "SELECT * FROM {$pb_entries} WHERE status = 'R' AND name LIKE :search";
        $countQuery = "SELECT COUNT(*) FROM {$pb_entries} WHERE status = 'R' AND name LIKE :search";
        $params[':search'] = '%' . $tmp_search . '%';
    } else {
        $baseQuery = "SELECT * FROM {$pb_entries} WHERE status = 'R'";
        $countQuery = "SELECT COUNT(*) FROM {$pb_entries} WHERE status = 'R'";
    }

    // Get total count
    try {
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($params);
        $count_pages = (int) $countStmt->fetchColumn();
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'doesn\'t exist') || str_contains($e->getMessage(), 'Base table or view not found')) {
            $installation_required = true;
            $count_pages = 0;
        } else {
            throw $e;
        }
    }

    if ($installation_required) {
        ?>
        <table border="0" cellpadding="4" cellspacing="1" width="100%" bgcolor="#6078A0">
            <tr><td bgcolor="#3F5070" align="center">
                <b style="color: #FFFFFF;">I N S T A L L A T I O N &nbsp; &nbsp; E R F O R D E R L I C H</b>
            </td></tr>
            <tr><td bgcolor="#001F3F" valign="top">
                <div style="padding: 20px; text-align: center; color: #FFFFFF;">
                    <p style="color: #FF6666; font-size: 14px;">
                        <b>Die PowerBook-Datenbanktabellen wurden nicht gefunden!</b>
                    </p>
                    <p>
                        Bitte führen Sie zuerst die Installation aus, um die erforderlichen<br>
                        Datenbanktabellen zu erstellen.
                    </p>
                    <p style="margin-top: 20px;">
                        <a href="install_deu.php" style="background: #3F5070; color: #FFFFFF; padding: 10px 20px; text-decoration: none; border: 1px solid #6078A0;">
                            &raquo; Zur Installation &laquo;
                        </a>
                    </p>
                </div>
            </td></tr>
        </table>
        <?php
        return;
    }

    $tmp_pages = (int) ceil($count_pages / $config_show_entries);
    $entries_word = ($count_pages === 1) ? 'Eintrag' : 'Einträge';

    // Get entries for current page
    $entriesQuery = $baseQuery . ' ORDER BY id DESC LIMIT :offset, :limit';
    $entriesStmt = $pdo->prepare($entriesQuery);

    foreach ($params as $key => $value) {
        $entriesStmt->bindValue($key, $value);
    }
    $entriesStmt->bindValue(':offset', $tmp_start, PDO::PARAM_INT);
    $entriesStmt->bindValue(':limit', $config_show_entries, PDO::PARAM_INT);
    $entriesStmt->execute();

    $message = '';
    if ($count_pages === 0) {
        if ($tmp_search === '') {
            $message = 'In diesem Gästebuch gibt es keine Einträge';
        } else {
            $message = '<a href="javascript:history.back()">Keine passenden Einträge.</a>';
        }
    }

    if ($tmp_search === '') {
        $count_entries = "Dieses Gästebuch enthält <b>{$count_pages}</b> {$entries_word}.";
    } else {
        $count_entries = "<b>{$count_pages}</b> {$entries_word} gefunden.<br><a href=\"" . e($config_guestbook_name) . '">Alle Einträge Auflisten</a>';
    }

    ?>

<!-- Navigation Header -->
<a name="#top-of-page"></a>
<div align="center">
   <a href="#write-entry">Eintrag Schreiben</a> | <a href="<?= e($config_guestbook_name) ?>?show_gb=no&amp;show_form=no&amp;search=yes">Nach Eintrag Suchen</a><br>
   <small><?= $count_entries ?></small><br><br>
</div>
   <?php include __DIR__ . '/pages.inc.php'; ?>
<br>

<!-- Entry List -->
<?php

    echo '<div align="center"><b>' . e($message) . '</b></div>';

    while ($entry = $entriesStmt->fetch(PDO::FETCH_ASSOC)) {
        include __DIR__ . '/entry.inc.php';
    }

    include __DIR__ . '/pages.inc.php';

    // Clear form variables
    unset($email, $url, $icq, $smilies, $text, $name);

    ?>

<br><a name="#write-entry"></a><br>

<div align="center">
   <a href="#top-of-page">Nach Oben</a>
</div><br><br>

<?php
}

// Preview handling
if ($preview === 'yes') {
    $error = '';
    $show_preview = 'no';

    // CSRF validation FIRST (before any processing)
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF-Token ungültig. Bitte die Seite neu laden.';
        $show_form = 'yes';
        logCsrfFailure('guestbook_preview');
    } elseif (strlen(trim($name)) === 0) {
        $error = 'Bitte einen <b>Name</b> eingeben!';
        $show_form = 'yes';
    } elseif (strlen(trim($text)) === 0) {
        $error = 'Bitte einen <b>Text</b> eingeben!!';
        $show_form = 'yes';
    } elseif (strlen($email2) >= 1 && !filter_var($email2, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige <b>eMail-Adresse</b>!';
        $show_form = 'yes';
    } else {
        $show_form = 'no';
        $show_preview = 'yes';
    }

    // Sanitize input for display AFTER validation
    $name = e($name);
    $email2 = e($email2);
    $url = e($url);
    $icq2 = e($icq2);

    if ($show_preview === 'yes') {

        $entry = [
            'text' => $text,
            'name' => $name,
            'email' => $email2,
            'smilies' => $smilies2,
            'homepage' => $url,
            'icq' => $icq2,
            'date' => time(),
            'icon' => ($icon === 'no' || $icon === '') ? '' : $icon,
            'statement' => '',
            'statement_by' => '',
        ];

        $text_escaped = str_replace('"', '&quot;', $text);

        include __DIR__ . '/entry.inc.php';

        echo '
         <form action="' . e($config_guestbook_name) . '" method="post">
            ' . csrfField() . '
            <input type="hidden" name="name2" value="' . e($name) . '">
            <input type="hidden" name="email2" value="' . e($email2) . '">
            <input type="hidden" name="url2" value="' . e($url) . '">
            <input type="hidden" name="icon2" value="' . e($icon) . '">
            <input type="hidden" name="icq2" value="' . e($icq2) . '">
            <input type="hidden" name="text2" value="' . e($text_escaped) . '">
            <input type="hidden" name="smilies2" value="' . e($smilies2) . '">
            <input type="hidden" name="show_gb" value="no">
            <input type="hidden" name="preview" value="no">
            <input type="hidden" name="add_entry" value="yes">
            <input type="hidden" name="show_form" value="no">
            <div align="center">
            <input type="submit" value="Eintragen!">
            <input type="button" value="Zurück" onClick="javascript:history.back()"></div>
         </form>
      ';
    }
}

// Show entry form
if ($show_form !== 'no') {
    if (isset($error) && strlen($error) > 1) {
        echo '<font color="' . e($config_color) . '">' . $error . '</font><br>';
    }

    $text = e($text ?? '');

    ?>

<script>
function SmiliesHelp()
{
 var Smilie = window.open("pb_inc/smilies-help.html", "SmilieHelp", "width=175,height=260");
 Smilie.moveTo(100,200);
 Smilie.focus();
}

function TextHelp()
{
 var Text = window.open("pb_inc/text-help.html", "TextHelp", "width=320,height=170");
 Text.moveTo(100,200);
 Text.focus();
}
</script>

<?php
    include __DIR__ . '/form.inc.php';
}

// Add entry to database
if ($add_entry === 'yes') {
    $message = '';
    $messageType = 'error';

    // CSRF validation FIRST
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF-Token ungültig. Bitte die Seite neu laden.';
        logCsrfFailure('guestbook_add');
    } else {
        $ip = getVisitorIp();
        $time = time();

        try {
            // Use transaction to prevent race condition in spam check
            $pdo->beginTransaction();

            // Check spam (time between entries from same IP) with row lock
            $spamStmt = $pdo->prepare("SELECT date FROM {$pb_entries} WHERE ip = :ip ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $spamStmt->execute([':ip' => $ip]);
            $lastEntry = $spamStmt->fetch(PDO::FETCH_ASSOC);

            $allow_add = true;

            if ($lastEntry) {
                $check_spam = $time - (int) $lastEntry['date'];

                if ($check_spam < $config_spam_check) {
                    $allow_add = false;
                    $left = $config_spam_check - $check_spam;
                    $message = "Um das Zuspammen des Gästebuchs zu verhindern, kann mit dieser IP-Adresse innerhalb der nächsten <b>{$left}</b> Sekunden kein weiterer Eintrag verfasst werden.";
                }
            }

            if ($allow_add) {
                $icon2 = ($icon2 === 'no' || $icon2 === '') ? '0' : $icon2;
                $smilies2 = ($smilies2 === 'Y') ? 'Y' : 'N';

                $insertStmt = $pdo->prepare("
                    INSERT INTO {$pb_entries}
                    (name, email, text, date, homepage, icq, ip, status, icon, smilies, statement, statement_by)
                    VALUES (:name, :email, :text, :date, :homepage, :icq, :ip, :status, :icon, :smilies, :statement, :statement_by)
                ");

                $insertStmt->execute([
                    ':name' => $name2,
                    ':email' => $email2,
                    ':text' => $text2,
                    ':date' => $time,
                    ':homepage' => $url2,
                    ':icq' => $icq2,
                    ':ip' => $ip,
                    ':status' => $config_release,
                    ':icon' => $icon2,
                    ':smilies' => $smilies2,
                    ':statement' => '',
                    ':statement_by' => '',
                ]);

                $pdo->commit();

                $message = 'Der Eintrag wurde erfolgreich in die Datenbank aufgenommen. Vielen Dank!';
                $messageType = 'success';

                if ($config_release === 'U') {
                    $message = 'Der Eintrag wurde erfolgreich in die Datenbank aufgenommen. Vielen Dank!<br> Da der Admin den Eintrag erst freischalten muss, kann es eine Weile dauern, bis dieser in der Liste erscheint.';
                }

                if ($config_send_email === 'Y') {
                    include __DIR__ . '/send-email.php';
                }

                if ($config_use_thanks === 'Y' && strlen($email2) >= 1) {
                    include __DIR__ . '/thank-email.php';
                }

                // Regenerate CSRF token after successful submission
                regenerateCsrfToken();
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('PowerBook DB Error: ' . $e->getMessage());
            $message = 'Ein Datenbankfehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
    }

    echo '<div align="center"><a href="' . e($config_guestbook_name) . '">' . $message . '</a></div>';
}

// Search form
if ($search === 'yes') {
    ?>
     <form action="<?= e($config_guestbook_name) ?>" method="get"><table border="0">
        <tr><td>
           Suchen nach:
        </td><td>
           <input type="text" size="15" maxlength="100" name="tmp_search">
        </td></tr>
        <tr><td>
           Ort der Suche:
        </td><td>
           <select size="1" name="tmp_where">
              <option value="name">Autor</option>
              <option value="text">Text</option>
           </select>
        </td></tr>
        <tr><td colspan="2" align="center">
              <input type="submit" value="Suchen!">
              <input type="button" value="Zurück" onClick="javascript:history.back()">
        </td></tr>
     </table></form>
  <?php
}

?>

<br><br>
<div align="center">
   <small><a href="https://github.com/schubertnico/PowerBook.git" target="_blank">PowerBook</a>
   &copy; 2002 <a href="mailto:expandable@powerscripts.org">Axel Habermaier</a>
   | PHP 8.4 Update: 2025
   </small>
</div>
