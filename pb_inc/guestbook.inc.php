<?php

/**
 * PowerBook - PHP Guestbook System
 * Gästebuch: Liste, Suche, Formular, Vorschau und Speichern
 *
 * Ablauf: Formular → (Vorschau →) Eintragen. Vorschau und Speichern prüfen
 * mit denselben Regeln (pb_validate_entry()) und derselben Zeitsperre.
 * Nach dem Speichern leitet die Seite auf die Liste um (Post/Redirect/Get),
 * die Meldung kommt einmalig aus der Sitzung.
 *
 * Wird von pbook.php eingebunden, bevor der Seitenkopf ausgegeben ist; setzt
 * $pbPageTitle für den <title>.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// Direktaufruf von /pb_inc/guestbook.inc.php ablehnen.
if (!defined('PB_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/functions.inc.php';
require_once __DIR__ . '/validation.inc.php';
require_once __DIR__ . '/error-handler.inc.php';
require_once __DIR__ . '/layout.inc.php';
require_once __DIR__ . '/send-email.php';
require_once __DIR__ . '/thank-email.php';

if (!function_exists('pb_guestbook_insert')) {
    /**
     * Speichert einen geprüften Eintrag. Die Zeitsperre wird in derselben
     * Transaktion noch einmal geprüft (gegen doppeltes Absenden).
     *
     * @param array{name: string, email: string, url: string, text: string, icon: string, smilies: string} $values
     *
     * @return array{id: int, wait: int} id > 0 = gespeichert, wait > 0 = Zeitsperre
     */
    function pb_guestbook_insert(PDO $pdo, string $table, array $values, string $status, int $spamCheck, string $ip, int $now): array
    {
        $pdo->beginTransaction();

        try {
            $wait = pb_spam_wait($pdo, $table, $ip, $spamCheck, $now, true);
            if ($wait > 0) {
                $pdo->rollBack();

                return ['id' => 0, 'wait' => $wait];
            }

            $stmt = $pdo->prepare(
                "INSERT INTO {$table} (name, email, text, date, homepage, ip, status, icon, smilies, statement, statement_by)
                 VALUES (:name, :email, :text, :date, :homepage, :ip, :status, :icon, :smilies, '', '')"
            );
            $stmt->execute([
                ':name' => $values['name'],
                ':email' => $values['email'],
                ':text' => $values['text'],
                ':date' => $now,
                ':homepage' => $values['url'] !== '' ? pb_normalize_url($values['url']) : '',
                ':ip' => $ip,
                ':status' => $status,
                ':icon' => pb_icon_label($values['icon']) !== null ? $values['icon'] : 'no',
                ':smilies' => $values['smilies'] === 'Y' ? 'Y' : 'N',
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return ['id' => $id, 'wait' => 0];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}

if (!function_exists('pb_guestbook_search_form')) {
    /**
     * Suchformular (#pbSearchForm), auf der Suchseite als Karte, über den
     * Treffern einzeilig und mit dem Suchbegriff vorbelegt.
     */
    function pb_guestbook_search_form(string $action, string $term, string $where, bool $compact): string
    {
        $options = '';
        foreach (['text' => 'Eintragstext', 'name' => 'Name'] as $value => $label) {
            $options .= '<option value="' . $value . '"' . ($where === $value ? ' selected' : '') . '>' . $label . '</option>';
        }
        $input = '<input id="pbSearchInput" type="search" class="form-control" name="tmp_search" maxlength="100" value="' . pb_h($term) . '" placeholder="Begriff eingeben">';
        $select = '<select id="tmp_where" class="form-select" name="tmp_where">' . $options . '</select>';
        $submit = '<button id="pbSearchSubmit" type="submit" class="btn btn-primary">Suchen</button>';

        if ($compact) {
            return '<form id="pbSearchForm" action="' . pb_h($action) . '" method="get" class="row g-2 align-items-end mb-3" role="search">'
                . '<div class="col-12 col-sm"><label for="pbSearchInput" class="form-label small mb-1">Suchbegriff</label>' . $input . '</div>'
                . '<div class="col-8 col-sm-auto"><label for="tmp_where" class="form-label small mb-1">Suchen in</label>' . $select . '</div>'
                . '<div class="col-4 col-sm-auto d-grid">' . $submit . '</div>'
                . '</form>';
        }

        return '<section class="card shadow-sm mb-4 pb-card-narrow" aria-labelledby="pbSearchTitle">'
            . '<header class="card-header bg-secondary text-white"><h2 id="pbSearchTitle" class="h5 mb-0">Einträge durchsuchen</h2></header>'
            . '<div class="card-body">'
            . '<form id="pbSearchForm" action="' . pb_h($action) . '" method="get" role="search">'
            . '<div class="mb-3"><label for="pbSearchInput" class="form-label">Suchbegriff</label>' . $input
            . '<div class="form-text">Gesucht wird ein Wortteil, Groß- und Kleinschreibung spielt keine Rolle.</div></div>'
            . '<div class="mb-3"><label for="tmp_where" class="form-label">Suchen in</label>' . $select . '</div>'
            . '<div class="d-flex flex-wrap gap-2">' . $submit
            . '<a id="pbSearchBack" href="' . pb_h($action) . '" class="btn btn-outline-secondary">Zurück zum Gästebuch</a></div>'
            . '</form></div></section>';
    }
}

// ---------------------------------------------------------------------------
// Einstellungen
// ---------------------------------------------------------------------------

$pbGuestbookUrl = trim((string) ($config_guestbook_name ?? '')) !== '' ? (string) $config_guestbook_name : 'pbook.php';
$pbTitle = trim((string) ($config_title ?? '')) !== '' ? (string) $config_title : 'Gästebuch';
$pbPageTitle = $pbTitle;
$pbEntriesTable = (string) ($pb_entries ?? 'pb_entries');
$pbPerPage = max(1, (int) ($config_show_entries ?? 10));
$pbIconsOn = ($config_icons ?? 'N') === 'Y';
$pbSmileysOn = ($config_smilies ?? 'N') === 'Y';
$pbStatus = ($config_release ?? 'R') === 'R' ? 'R' : 'U';
$pbSpamCheck = (int) ($config_spam_check ?? 0);

$pbFormValues = ['name' => '', 'email' => '', 'url' => '', 'text' => '', 'icon' => 'no', 'smilies' => 'Y'];
$pbFormErrors = [];
$pbFormNotice = '';
$pbMode = ($_GET['search'] ?? '') === 'yes' ? 'search' : 'list';
if ($pbMode === 'list' && ($_GET['show_gb'] ?? '') === 'no') {
    $pbMode = 'form'; // Link aus 3.0: nur das Formular
}

// ---------------------------------------------------------------------------
// Formular abgeschickt: Vorschau, Ändern oder Eintragen
// ---------------------------------------------------------------------------

$pbInput = $_POST;
$pbAction = is_string($pbInput['action'] ?? null) ? $pbInput['action'] : '';
if ($pbAction === '' && ($pbInput['preview'] ?? '') === 'yes') {
    $pbAction = 'preview'; // Formular aus 3.0
} elseif ($pbAction === '' && ($pbInput['add_entry'] ?? '') === 'yes') {
    $pbAction = 'save'; // Vorschau aus 3.0
    foreach (['name', 'url', 'text', 'icon'] as $pbField) {
        $pbInput[$pbField] = $pbInput[$pbField . '2'] ?? '';
    }
}

if (in_array($pbAction, ['preview', 'save', 'edit'], true)) {
    $pbMode = 'form';
    $pbFormValues = pb_normalize_entry($pbInput);
    if (!$pbIconsOn) {
        $pbFormValues['icon'] = 'no';
    }
    if (!$pbSmileysOn) {
        $pbFormValues['smilies'] = 'Y';
    }

    if (!validateCsrfToken(is_string($pbInput['csrf_token'] ?? null) ? $pbInput['csrf_token'] : '')) {
        $pbFormNotice = pb_h(function_exists('pb_csrf_failed_message')
            ? pb_csrf_failed_message()
            : 'Das Formular war abgelaufen. Bitte senden Sie es erneut ab.');
        if (function_exists('logCsrfFailure')) {
            logCsrfFailure('guestbook_' . $pbAction);
        }
    } elseif ($pbAction !== 'edit') {
        $pbFormErrors = pb_validate_entry($pbFormValues, $pbIconsOn);
        $pbNow = time();
        $pbIp = getVisitorIp();
        $pbWait = 0;

        try {
            if ($pbFormErrors === []) {
                $pbWait = pb_spam_wait($pdo, $pbEntriesTable, $pbIp, $pbSpamCheck, $pbNow);
                if ($pbWait === 0 && $pbAction === 'save') {
                    $pbSaved = pb_guestbook_insert($pdo, $pbEntriesTable, $pbFormValues, $pbStatus, $pbSpamCheck, $pbIp, $pbNow);
                    $pbWait = $pbSaved['wait'];
                    if ($pbSaved['id'] > 0) {
                        if (($config_send_email ?? 'N') === 'Y') {
                            pb_send_admin_notification($pbFormValues, $pbStatus, $pbNow);
                        }
                        pb_send_thanks_mail($pdo, $pbEntriesTable, $pbFormValues, $pbSaved['id'], $pbNow, $pbIp);

                        $_SESSION['pb_entry_saved'] = ['status' => $pbStatus, 'id' => $pbSaved['id']];
                        if (PHP_SAPI !== 'cli' && !headers_sent()) {
                            header('Location: ' . $pbGuestbookUrl, true, 303);
                            exit;
                        }
                        // Ohne Umleitung (Kopf schon gesendet): Liste mit Meldung direkt zeigen.
                        $pbMode = 'list';
                        $pbFormValues = ['name' => '', 'email' => '', 'url' => '', 'text' => '', 'icon' => 'no', 'smilies' => 'Y'];
                    }
                }
            }
        } catch (PDOException $e) {
            logDbError('Gästebuch: ' . $e->getMessage());
            $pbFormNotice = 'Ihr Eintrag konnte wegen eines Datenbankfehlers nicht gespeichert werden. Bitte versuchen Sie es später noch einmal. Ihr Text bleibt erhalten.';
        }

        if ($pbWait > 0) {
            $pbFormNotice = 'Zum Schutz vor Spam sind zwischen zwei Einträgen ' . $pbSpamCheck . ' Sekunden Pause nötig. '
                . 'Bitte warten Sie noch <strong id="pbSpamSeconds">' . $pbWait . '</strong> Sekunden und klicken Sie dann erneut. Ihr Text bleibt erhalten.';
        } elseif ($pbMode === 'form' && $pbAction === 'preview' && $pbFormErrors === [] && $pbFormNotice === '') {
            $pbMode = 'preview';
        }
    }
}

// ---------------------------------------------------------------------------
// Ausgabe
// ---------------------------------------------------------------------------

if ($pbMode === 'preview') {
    $pbPageTitle = 'Vorschau – ' . $pbTitle;
    $pbPreviewEntry = [
        'id' => 0,
        'name' => $pbFormValues['name'],
        'email' => $pbFormValues['email'],
        'homepage' => $pbFormValues['url'],
        'text' => $pbFormValues['text'],
        'icon' => $pbFormValues['icon'],
        'smilies' => $pbFormValues['smilies'],
        'date' => time(),
        'statement' => '',
        'statement_by' => '',
    ];
    ?>
<section id="pbEntryPreview" class="card border-info shadow-sm mb-4" aria-labelledby="pbEntryPreviewTitle">
    <header class="card-header bg-info-subtle">
        <h2 id="pbEntryPreviewTitle" class="h5 mb-0">Vorschau</h2>
    </header>
    <div class="card-body">
        <p class="text-body-secondary">
            Ihr Eintrag ist noch nicht gespeichert. Prüfen Sie ihn und klicken Sie auf „Eintragen“.
            <?php if ($pbStatus === 'U') { ?>Er erscheint, sobald er freigeschaltet ist.<?php } ?>
        </p>
        <?php $entry = $pbPreviewEntry;
    include __DIR__ . '/entry.inc.php'; ?>
        <form action="<?= pb_h($pbGuestbookUrl) ?>" method="post" class="mt-3">
            <?= csrfField() ?>
            <input type="hidden" name="name" value="<?= pb_h($pbFormValues['name']) ?>">
            <input type="hidden" name="email2" value="<?= pb_h($pbFormValues['email']) ?>">
            <input type="hidden" name="url" value="<?= pb_h($pbFormValues['url']) ?>">
            <input type="hidden" name="text" value="<?= pb_h($pbFormValues['text']) ?>">
            <input type="hidden" name="icon" value="<?= pb_h($pbFormValues['icon']) ?>">
            <input type="hidden" name="smilies2" value="<?= $pbFormValues['smilies'] === 'Y' ? 'Y' : 'N' ?>">
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <button id="pbEntrySubmit" type="submit" name="action" value="save" class="btn btn-primary">Eintragen</button>
                <button id="pbPreviewBack" type="submit" name="action" value="edit" class="btn btn-outline-secondary">Ändern</button>
            </div>
        </form>
    </div>
</section>
    <?php
    return;
}

if ($pbMode === 'form') {
    $pbPageTitle = 'Eintrag schreiben – ' . $pbTitle;
    include __DIR__ . '/form.inc.php';

    return;
}

$tmp_search = mb_substr(trim(is_string($_GET['tmp_search'] ?? null) ? $_GET['tmp_search'] : ''), 0, 100);
$tmp_where = is_string($_GET['tmp_where'] ?? null) && in_array($_GET['tmp_where'], ['name', 'text'], true) ? $_GET['tmp_where'] : '';

if ($pbMode === 'search') {
    $pbPageTitle = 'Suche – ' . $pbTitle;
    echo pb_guestbook_search_form($pbGuestbookUrl, $tmp_search, $tmp_where !== '' ? $tmp_where : 'text', false);

    return;
}

// Liste (mit oder ohne Suche); gesucht wird nur mit Begriff und Bereich.
if ($tmp_where === '') {
    $tmp_search = '';
}
if ($tmp_search === '') {
    $tmp_where = '';
}
$pbWhereSql = "status = 'R'";
$pbParams = [];
if ($tmp_search !== '') {
    $pbWhereSql .= ' AND ' . ($tmp_where === 'name' ? 'name' : 'text') . " LIKE :search ESCAPE '!'";
    $pbParams[':search'] = '%' . pb_like_escape($tmp_search) . '%';
}

try {
    $pbCountStmt = $pdo->prepare("SELECT COUNT(*) FROM {$pbEntriesTable} WHERE {$pbWhereSql}");
    $pbCountStmt->execute($pbParams);
    $count_pages = (int) $pbCountStmt->fetchColumn();

    // Startwert auf eine gültige Seite begrenzen (negativ, krumm, hinter dem Ende).
    $tmp_start = max(0, (int) (is_numeric($_GET['tmp_start'] ?? null) ? $_GET['tmp_start'] : 0));
    $tmp_start = intdiv($tmp_start, $pbPerPage) * $pbPerPage;
    if ($count_pages > 0 && $tmp_start >= $count_pages) {
        $tmp_start = intdiv($count_pages - 1, $pbPerPage) * $pbPerPage;
    }

    $pbEntriesStmt = $pdo->prepare("SELECT * FROM {$pbEntriesTable} WHERE {$pbWhereSql} ORDER BY date DESC, id DESC LIMIT :limit OFFSET :offset");
    foreach ($pbParams as $pbKey => $pbValue) {
        $pbEntriesStmt->bindValue($pbKey, $pbValue);
    }
    $pbEntriesStmt->bindValue(':limit', $pbPerPage, PDO::PARAM_INT);
    $pbEntriesStmt->bindValue(':offset', $tmp_start, PDO::PARAM_INT);
    $pbEntriesStmt->execute();
    $pbEntries = $pbEntriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pbMessage = $e->getMessage();
    if (str_contains($pbMessage, "doesn't exist") || str_contains($pbMessage, 'Base table or view not found') || str_contains($pbMessage, 'no such table')) {
        ?>
<section class="card border-warning shadow-sm mb-4">
    <header class="card-header bg-warning text-dark"><h2 class="h5 mb-0">Installation erforderlich</h2></header>
    <div class="card-body text-center">
        <p class="lead fw-semibold">Die Datenbanktabellen von PowerBook wurden nicht gefunden.</p>
        <p class="mb-4">Bitte führen Sie zuerst die Installation aus. Sie legt die Tabellen an und richtet das erste Admin-Konto ein.</p>
        <a href="install.php" class="btn btn-primary">Zur Installation</a>
    </div>
</section>
        <?php
        return;
    }
    logDbError('Gästebuch: ' . $pbMessage);
    echo pb_alert('Das Gästebuch ist gerade nicht erreichbar. Bitte versuchen Sie es später noch einmal.', 'danger', 'pbGuestbookError');

    return;
}

$pbPage = intdiv($tmp_start, $pbPerPage) + 1;
if ($tmp_search !== '') {
    $pbPageTitle = 'Suche nach „' . $tmp_search . '“ – ' . $pbTitle;
} elseif ($pbPage > 1) {
    $pbPageTitle = 'Seite ' . $pbPage . ' – ' . $pbTitle;
}

// Einmalige Meldung nach dem Eintragen (Post/Redirect/Get)
$pbSaved = $_SESSION['pb_entry_saved'] ?? null;
unset($_SESSION['pb_entry_saved']);
if (is_array($pbSaved)) {
    if (($pbSaved['status'] ?? 'U') === 'R') {
        $pbSavedId = (int) ($pbSaved['id'] ?? 0);
        echo pb_alert(
            'Vielen Dank! Ihr Eintrag ist jetzt im Gästebuch.'
            . ($pbSavedId > 0 ? ' <a class="alert-link" href="#pbEntry' . $pbSavedId . '">Zum Eintrag</a>' : ''),
            'success',
            'pbEntryMessage'
        );
    } else {
        echo pb_alert('Vielen Dank! Ihr Eintrag erscheint, sobald er freigeschaltet ist.', 'success', 'pbEntryMessage');
    }
}

$pbCountText = number_format($count_pages, 0, ',', '.');
$pbEntriesWord = $count_pages === 1 ? 'Eintrag' : 'Einträge';
if ($tmp_search !== '') {
    $pbSearchArea = $tmp_where === 'name' ? 'im Namen' : 'im Eintragstext';
    $pbCountHtml = ($count_pages === 0 ? 'Keine Einträge' : '<b>' . $pbCountText . '</b> ' . $pbEntriesWord)
        . ' mit „' . pb_h($tmp_search) . '“ ' . $pbSearchArea;
} elseif ($count_pages === 0) {
    $pbCountHtml = 'Noch keine Einträge.';
} else {
    $pbCountHtml = 'Dieses Gästebuch enthält <b>' . $pbCountText . '</b> ' . $pbEntriesWord . '.';
}

if ($tmp_search !== '') {
    echo pb_guestbook_search_form($pbGuestbookUrl, $tmp_search, $tmp_where, true);
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 bg-body-secondary rounded p-3 mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a id="pbWriteEntryLink" href="#write-entry" class="btn btn-primary btn-sm">Eintrag schreiben</a>
        <a id="pbSearchLink" href="<?= pb_h(pb_url_with($pbGuestbookUrl, ['search' => 'yes'])) ?>" class="btn btn-outline-secondary btn-sm">Einträge durchsuchen</a>
    </div>
    <div class="small text-body-secondary">
        <span id="pbEntryCount"><?= $pbCountHtml ?></span>
        <?php if ($tmp_search !== '') { ?>
        · <a id="pbSearchReset" href="<?= pb_h($pbGuestbookUrl) ?>">Alle Einträge anzeigen</a>
        <?php } ?>
    </div>
</div>
<?php
$pbPagerPosition = 'top';
include __DIR__ . '/pages.inc.php';

if ($count_pages === 0 && $tmp_search !== '') {
    echo pb_alert(
        'Keine Einträge mit „' . pb_h($tmp_search) . '“ ' . $pbSearchArea . ' gefunden. Versuchen Sie einen anderen Begriff'
        . ($tmp_where === 'name' ? ' oder suchen Sie im Eintragstext.' : ' oder suchen Sie im Namen.'),
        'warning',
        'pbSearchEmpty'
    );
} elseif ($count_pages === 0) {
    echo pb_alert('In diesem Gästebuch gibt es noch keine Einträge. Schreiben Sie gern den ersten!', 'info', 'pbNoEntries');
}

foreach ($pbEntries as $entry) {
    include __DIR__ . '/entry.inc.php';
}

$pbPagerPosition = 'bottom';
include __DIR__ . '/pages.inc.php';
?>

<a id="write-entry"></a>
<?php include __DIR__ . '/form.inc.php'; ?>

<div class="text-center mt-4 mb-4">
    <a id="pbBackToTop" href="#top-of-page" class="btn btn-link btn-sm">Nach oben &uarr;</a>
</div>
