<?php
/**
 * PowerBook - PHP Guestbook System
 * Main Guestbook Display and Entry Handler
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// BUG-013: Direktaufruf /pb_inc/guestbook.inc.php blockieren.
if (!defined('PB_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

// Include required files
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/functions.inc.php';
require_once __DIR__ . '/error-handler.inc.php';
require_once __DIR__ . '/layout.inc.php';

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
        <div class="card border-warning shadow-sm mb-4">
            <div class="card-header bg-warning text-dark">
                <h2 class="h5 mb-0">Installation erforderlich</h2>
            </div>
            <div class="card-body text-center">
                <p class="lead text-danger fw-semibold">
                    Die PowerBook-Datenbanktabellen wurden nicht gefunden!
                </p>
                <p class="mb-4">
                    Bitte fuehren Sie zuerst die Installation aus, um die erforderlichen
                    Datenbanktabellen zu erstellen.
                </p>
                <a href="install_deu.php" class="btn btn-primary">
                    &raquo; Zur Installation &laquo;
                </a>
            </div>
        </div>
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
    $message_html = '';
    if ($count_pages === 0) {
        if ($tmp_search === '') {
            $message = 'In diesem Gästebuch gibt es keine Einträge';
        } else {
            // BUG-007: HTML-Markup separat halten, damit e() es nicht escapet.
            $message_html = '<a href="javascript:history.back()">Keine passenden Einträge.</a>';
        }
    }

    if ($tmp_search === '') {
        $count_entries = "Dieses Gästebuch enthaelt <b>{$count_pages}</b> {$entries_word}.";
    } else {
        $count_entries = "<b>{$count_pages}</b> {$entries_word} gefunden.<br><a href=\"" . e($config_guestbook_name) . '">Alle Einträge Auflisten</a>';
    }

    ?>

<!-- Navigation Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center bg-body-secondary rounded p-3 mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="#write-entry" class="btn btn-primary btn-sm">Eintrag schreiben</a>
        <a href="<?= e($config_guestbook_name) ?>?show_gb=no&amp;show_form=no&amp;search=yes" class="btn btn-outline-secondary btn-sm">Nach Eintrag suchen</a>
    </div>
    <small class="text-body-secondary"><?= $count_entries ?></small>
</div>
<?php include __DIR__ . '/pages.inc.php'; ?>

<!-- Entry List -->
<?php

    if ($message !== '') {
        echo '<div class="alert alert-info text-center" role="status"><b>' . e($message) . '</b></div>';
    } elseif ($message_html !== '') {
        // BUG-007: $message_html ist vertrauenswuerdiges HTML (keine User-Eingabe), daher KEIN e().
        echo '<div class="alert alert-warning text-center" role="status"><b>' . $message_html . '</b></div>';
    }

    while ($entry = $entriesStmt->fetch(PDO::FETCH_ASSOC)) {
        include __DIR__ . '/entry.inc.php';
    }

    include __DIR__ . '/pages.inc.php';

    // Clear form variables
    unset($email, $url, $smilies, $text, $name);

    ?>

<a id="write-entry"></a>

<div class="text-center mt-4 mb-4">
    <a href="#top-of-page" class="btn btn-link btn-sm">Nach oben &uarr;</a>
</div>

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
    } elseif (mb_strlen($name) > 100) {
        // BUG-008: serverseitige Laengenpruefung (client-maxlength umgehbar).
        $error = 'Der <b>Name</b> darf höchstens 100 Zeichen lang sein!';
        $show_form = 'yes';
    } elseif (strlen(trim($text)) === 0) {
        $error = 'Bitte einen <b>Text</b> eingeben!!';
        $show_form = 'yes';
    } elseif (mb_strlen($text) > 5000) {
        $error = 'Der <b>Text</b> darf höchstens 5000 Zeichen lang sein!';
        $show_form = 'yes';
    } elseif (strlen($email2) >= 1 && !filter_var($email2, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige <b>E-Mail-Adresse</b>!';
        $show_form = 'yes';
    } elseif (mb_strlen($email2) > 250) {
        $error = 'Die <b>E-Mail-Adresse</b> darf höchstens 250 Zeichen lang sein!';
        $show_form = 'yes';
    } elseif (mb_strlen($url) > 255) {
        $error = 'Die <b>Homepage-URL</b> darf höchstens 255 Zeichen lang sein!';
        $show_form = 'yes';
    } else {
        $show_form = 'no';
        $show_preview = 'yes';
    }

    // BUG-003: Roh-Werte vor der Escape-Runde bewahren, damit die Preview-
    // Hidden-Fields (url2, name2, ...) NICHT doppelt escaped werden.
    $raw_name = $name;
    $raw_email2 = $email2;
    $raw_url = $url;

    // Sanitize input for display AFTER validation
    $name = e($name);
    $email2 = e($email2);
    $url = e($url);

    if ($show_preview === 'yes') {

        $entry = [
            'text' => $text,
            'name' => $name,
            'email' => $email2,
            'smilies' => $smilies2,
            'homepage' => $url,
            'date' => time(),
            'icon' => ($icon === 'no' || $icon === '') ? '' : $icon,
            'statement' => '',
            'statement_by' => '',
        ];

        $text_escaped = str_replace('"', '&quot;', $text);

        echo '<div class="card border-info shadow-sm mb-4">';
        echo '<div class="card-header bg-info text-dark"><h2 class="h5 mb-0">Vorschau</h2></div>';
        echo '<div class="card-body">';

        include __DIR__ . '/entry.inc.php';

        echo '
         <form action="' . e($config_guestbook_name) . '" method="post" class="mt-3">
            ' . csrfField() . '
            <input type="hidden" name="name2" value="' . e($raw_name) . '">
            <input type="hidden" name="email2" value="' . e($raw_email2) . '">
            <input type="hidden" name="url2" value="' . e($raw_url) . '">
            <input type="hidden" name="icon2" value="' . e($icon) . '">
            <input type="hidden" name="text2" value="' . e($text_escaped) . '">
            <input type="hidden" name="smilies2" value="' . e($smilies2) . '">
            <input type="hidden" name="show_gb" value="no">
            <input type="hidden" name="preview" value="no">
            <input type="hidden" name="add_entry" value="yes">
            <input type="hidden" name="show_form" value="no">
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <button type="submit" class="btn btn-success">Eintragen!</button>
                <button type="button" class="btn btn-outline-secondary" onclick="javascript:history.back()">Zurück</button>
            </div>
         </form>
      ';

        echo '</div></div>';
    }
}

// Show entry form
if ($show_form !== 'no') {
    if (isset($error) && strlen($error) > 1) {
        echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';
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

                // ICQ-Spalte wird nicht mehr beschrieben (Legacy-Service eingestellt).
                // Bestehende DB-Spalten in alten Installationen behalten ihren
                // DEFAULT-Wert ('') beim INSERT.
                $insertStmt = $pdo->prepare("
                    INSERT INTO {$pb_entries}
                    (name, email, text, date, homepage, ip, status, icon, smilies, statement, statement_by)
                    VALUES (:name, :email, :text, :date, :homepage, :ip, :status, :icon, :smilies, :statement, :statement_by)
                ");

                $insertStmt->execute([
                    ':name' => $name2,
                    ':email' => $email2,
                    ':text' => $text2,
                    ':date' => $time,
                    ':homepage' => $url2,
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

    $alertVariant = $messageType === 'success' ? 'success' : 'danger';
    echo '<div class="alert alert-' . $alertVariant . ' text-center" role="alert">'
       . '<a class="alert-link" href="' . e($config_guestbook_name) . '">' . $message . '</a></div>';
}

// Search form
if ($search === 'yes') {
    ?>
    <div class="card shadow-sm mb-4 pb-card-narrow">
        <div class="card-header bg-secondary text-white">
            <h2 class="h5 mb-0">Einträge durchsuchen</h2>
        </div>
        <div class="card-body">
            <form action="<?= e($config_guestbook_name) ?>" method="get">
                <div class="mb-3">
                    <label for="tmp_search" class="form-label">Suchen nach</label>
                    <input id="tmp_search" type="text" class="form-control" maxlength="100" name="tmp_search" placeholder="Begriff eingeben">
                    <div class="form-text">Geben Sie einen Begriff ein, der im gewählten Feld vorkommt.</div>
                </div>
                <div class="mb-3">
                    <label for="tmp_where" class="form-label">Ort der Suche</label>
                    <select id="tmp_where" class="form-select" name="tmp_where">
                        <option value="name">Autor (Name)</option>
                        <option value="text">Eintragstext</option>
                    </select>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <button type="submit" class="btn btn-primary">Suchen!</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="javascript:history.back()">Zurück</button>
                </div>
            </form>
        </div>
    </div>
  <?php
}
