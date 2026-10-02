<?php
/**
 * PowerBook - PHP Guestbook System
 * Admins verwalten
 *
 * Regeln (siehe pb_admin_can_manage() und pb_admin_can_grant()):
 * - Superadmin ist das Konto mit der ID 1. Es hat immer alle Rechte, lässt
 *   sich nicht löschen und wird nur von ihm selbst unter „Mein Konto“ geändert.
 * - Das Recht „Admins verwalten“ vergibt nur der Superadmin; Konten mit diesem
 *   Recht ändert und löscht nur er.
 * - Niemand vergibt Rechte, die er selbst nicht hat.
 * - Passwörter werden hier nicht eingegeben: Neue Admins und auf Wunsch
 *   bestehende bekommen einen Link „Passwort festlegen“ (48 Stunden gültig).
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';
require_once __DIR__ . '/admin_email_helpers.inc.php';

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_admin */
/** @var array<string, mixed> $admin_session */

// Check permission
if (($admin_session['admins'] ?? 'N') !== 'Y') {
    pb_admin_card_open('Admins verwalten');
    echo pb_admin_inline_message('Sie haben keine Berechtigung, Admins zu verwalten.', 'danger');
    pb_admin_card_close();

    return;
}

$actor = $admin_session;
$actorName = (string) ($actor['name'] ?? '') !== '' ? (string) $actor['name'] : 'Ein Admin';
$rights = pb_admin_rights();
$action = (string) ($_POST['action'] ?? '');
$postedId = (int) ($_POST['edit_id'] ?? 0);

$message = '';
$messageType = 'danger';
$messageIsHtml = false;
/** @var array<string, array<string, string>> $fieldErrors Fehler je Formular ('add' oder Admin-ID) und Feld */
$fieldErrors = [];
/** @var array<int, array<string, string>> $rowValues gesendete Werte je Admin-ID nach einem Fehler */
$rowValues = [];
$confirmDelete = null;

// Vorgaben für „Neuen Admin anlegen“: Moderator (freischalten + bearbeiten), soweit erlaubt
$addDefaults = ['name' => '', 'email' => ''];
$canGrantAny = false;
foreach (array_keys($rights) as $right) {
    $addDefaults[$right] = (in_array($right, ['release', 'entries'], true) && pb_admin_can_grant($actor, $right)) ? 'Y' : 'N';
    $canGrantAny = $canGrantAny || pb_admin_can_grant($actor, $right);
}
$addValues = $addDefaults;

/**
 * Gesendete Rechte übernehmen: nur die, die der Bearbeiter vergeben darf,
 * alle anderen bleiben wie in $current.
 *
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $current
 *
 * @return array<string, string>
 */
$mergeRights = static function (string $prefix, array $actor, array $current) use ($rights): array {
    $result = [];
    foreach (array_keys($rights) as $right) {
        $result[$right] = pb_admin_can_grant($actor, $right)
            ? ((($_POST[$prefix . $right] ?? '') === 'Y') ? 'Y' : 'N')
            : ((($current[$right] ?? 'N') === 'Y') ? 'Y' : 'N');
    }

    return $result;
};

$hasRight = static function (array $values) use ($rights): bool {
    foreach (array_keys($rights) as $right) {
        if (($values[$right] ?? 'N') === 'Y') {
            return true;
        }
    }

    return false;
};

/** Grund, warum $actor das Konto $target nicht ändern darf. */
$denyReason = static function (array $actor, array $target): string {
    if ((int) ($target['id'] ?? 0) === (int) ($actor['id'] ?? 0)) {
        return 'Ihr eigenes Konto ändern Sie unter „Mein Konto“.';
    }
    if (pb_admin_is_superadmin($target)) {
        return 'Den Superadmin ändert nur er selbst unter „Mein Konto“.';
    }
    if (($target['admins'] ?? 'N') === 'Y') {
        return 'Konten mit dem Recht „Admins verwalten“ ändert nur der Superadmin.';
    }

    return 'Dieses Konto hat Rechte, die Sie selbst nicht haben. Sie können es deshalb nicht ändern.';
};

/** Hinweis mit dem Link zum Festlegen des Passworts, wenn die Mail nicht ankam. */
$linkNotice = static function (string $intro, string $name, string $link): string {
    return e($intro) . '<br>Über diesen Link legt ' . e($name)
        . ' das Passwort selbst fest (48 Stunden gültig, nur einmal verwendbar). Geben Sie ihn bitte auf sicherem Weg weiter:'
        . '<input id="pbAdminLink" type="text" class="form-control font-monospace mt-2" readonly value="' . e($link) . '">';
};

if ($action !== '') {
    if (!validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        logCsrfFailure('admins');
        $message = pb_csrf_failed_message();
        if ($action === 'add') {
            $addValues = array_merge(
                ['name' => trim((string) ($_POST['add_name'] ?? '')), 'email' => trim((string) ($_POST['add_email'] ?? ''))],
                $mergeRights('add_', $actor, $addValues)
            );
        } elseif ($action === 'save' && $postedId > 0) {
            $rowValues[$postedId] = [
                'name' => trim((string) ($_POST['edit_name'] ?? '')),
                'email' => trim((string) ($_POST['edit_email'] ?? '')),
            ];
        }
    } elseif ($action === 'add') {
        // ---------------------------------------------------------------
        // Neuen Admin anlegen
        // ---------------------------------------------------------------
        $addValues = array_merge(
            ['name' => trim((string) ($_POST['add_name'] ?? '')), 'email' => trim((string) ($_POST['add_email'] ?? ''))],
            $mergeRights('add_', $actor, ['admins' => 'N', 'config' => 'N', 'entries' => 'N', 'release' => 'N'])
        );

        $errors = [];
        $nameError = pb_admin_validate_name($addValues['name']);
        if ($nameError !== null) {
            $errors['name'] = $nameError;
        } elseif (pb_admin_value_taken($pdo, $pb_admin, 'name', $addValues['name'])) {
            $errors['name'] = 'Es gibt bereits einen Admin mit diesem Namen.';
        }
        $emailError = pb_admin_validate_email($addValues['email']);
        if ($emailError !== null) {
            $errors['email'] = $emailError;
        } elseif (pb_admin_value_taken($pdo, $pb_admin, 'email', $addValues['email'])) {
            $errors['email'] = 'Diese E-Mail-Adresse gehört schon zu einem anderen Admin.';
        }
        if (!$hasRight($addValues)) {
            $errors['rights'] = 'Bitte wählen Sie mindestens ein Recht aus.';
        }

        if ($errors !== []) {
            $fieldErrors['add'] = $errors;
            $message = 'Der Admin wurde nicht angelegt. Bitte prüfen Sie die markierten Felder.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO {$pb_admin} (name, email, password, config, admins, entries, `release`) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                // Zufälliges, nirgends bekanntes Passwort: Anmelden geht erst nach „Passwort festlegen“.
                $stmt->execute([
                    $addValues['name'],
                    $addValues['email'],
                    password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                    $addValues['config'],
                    $addValues['admins'],
                    $addValues['entries'],
                    $addValues['release'],
                ]);
                $newId = (int) $pdo->lastInsertId();
                $token = pb_admin_issue_password_token($pdo, $pb_admin, $newId, PB_TOKEN_LIFETIME_LINK);
                $link = pb_admin_password_link($token, true);

                logSecurityEvent('ADMIN_ADDED', ['id' => $newId, 'by' => (int) ($actor['id'] ?? 0)]);

                $mailSent = sendAdminEmail('added', array_merge($addValues, [
                    'to' => $addValues['email'],
                    'by' => $actorName,
                    'link' => $link,
                ]));

                if (!$mailSent) {
                    $message = $linkNotice(
                        'Der Zugang für ' . $addValues['name'] . ' ist angelegt, aber die E-Mail an '
                        . $addValues['email'] . ' konnte nicht verschickt werden.',
                        $addValues['name'],
                        $link
                    );
                    $messageType = 'warning';
                    $messageIsHtml = true;
                } else {
                    $text = 'Der Zugang für ' . $addValues['name'] . ' ist angelegt. Eine E-Mail mit dem Link zum Festlegen des Passworts ging an '
                        . $addValues['email'] . '.';
                    if (pb_admin_finish_post($text, '?page=admins')) {
                        return;
                    }
                    $message = $text;
                    $messageType = 'success';
                }

                $addValues = $addDefaults;
            } catch (PDOException $e) {
                logDbError('Admin add: ' . $e->getMessage());
                $message = 'Der Admin konnte wegen eines Datenbankfehlers nicht angelegt werden.';
            }
        }
    } elseif ($action === 'save' || $action === 'delete' || $action === 'delete_confirm') {
        // ---------------------------------------------------------------
        // Bestehendes Konto ändern oder löschen
        // ---------------------------------------------------------------
        $target = null;

        try {
            $target = pb_admin_load($pdo, $pb_admin, $postedId);
        } catch (PDOException $e) {
            logDbError('Admin load: ' . $e->getMessage());
        }

        if ($target === null) {
            $message = 'Dieses Konto gibt es nicht (mehr).';
        } elseif (($action === 'delete' || $action === 'delete_confirm') && (int) $target['id'] === (int) ($actor['id'] ?? 0)) {
            $message = 'Sie können sich nicht selbst löschen.';
        } elseif (($action === 'delete' || $action === 'delete_confirm') && pb_admin_is_superadmin($target)) {
            $message = 'Der Superadmin lässt sich nicht löschen.';
        } elseif (!pb_admin_can_manage($actor, $target)) {
            $message = $denyReason($actor, $target);
            if ($action === 'save') {
                logSecurityEvent('ADMIN_EDIT_DENIED', ['id' => (int) $target['id'], 'by' => (int) ($actor['id'] ?? 0)]);
            }
        } elseif ($action === 'delete') {
            $confirmDelete = $target;
        } elseif ($action === 'delete_confirm') {
            try {
                $stmt = $pdo->prepare("DELETE FROM {$pb_admin} WHERE id = ?");
                $stmt->execute([(int) $target['id']]);
                logSecurityEvent('ADMIN_DELETED', ['id' => (int) $target['id'], 'by' => (int) ($actor['id'] ?? 0)]);

                sendAdminEmail('deleted', [
                    'to' => (string) $target['email'],
                    'name' => (string) $target['name'],
                    'by' => $actorName,
                ]);

                $text = 'Der Zugang von ' . $target['name'] . ' ist gelöscht.';
                if (pb_admin_finish_post($text, '?page=admins')) {
                    return;
                }
                $message = $text;
                $messageType = 'success';
            } catch (PDOException $e) {
                logDbError('Admin delete: ' . $e->getMessage());
                $message = 'Der Zugang konnte wegen eines Datenbankfehlers nicht gelöscht werden.';
            }
        } else {
            // Speichern
            $id = (int) $target['id'];
            $new = array_merge(
                ['name' => trim((string) ($_POST['edit_name'] ?? '')), 'email' => trim((string) ($_POST['edit_email'] ?? ''))],
                $mergeRights('edit_', $actor, $target)
            );
            $sendLink = ($_POST['send_link'] ?? '') === '1';

            $errors = [];
            $nameError = pb_admin_validate_name($new['name']);
            if ($nameError !== null) {
                $errors['name'] = $nameError;
            } elseif (pb_admin_value_taken($pdo, $pb_admin, 'name', $new['name'], $id)) {
                $errors['name'] = 'Es gibt bereits einen Admin mit diesem Namen.';
            }
            $emailError = pb_admin_validate_email($new['email']);
            if ($emailError !== null) {
                $errors['email'] = $emailError;
            } elseif (pb_admin_value_taken($pdo, $pb_admin, 'email', $new['email'], $id)) {
                $errors['email'] = 'Diese E-Mail-Adresse gehört schon zu einem anderen Admin.';
            }
            if (!$hasRight($new)) {
                $errors['rights'] = 'Bitte wählen Sie mindestens ein Recht aus.';
            }

            if ($errors !== []) {
                $fieldErrors[(string) $id] = $errors;
                $rowValues[$id] = $new;
                $message = 'Die Änderungen für ' . $target['name'] . ' wurden nicht gespeichert. Bitte prüfen Sie die markierten Felder.';
            } else {
                $nameChanged = $new['name'] !== (string) $target['name'];
                $emailChanged = mb_strtolower($new['email'], 'UTF-8') !== mb_strtolower((string) $target['email'], 'UTF-8');
                $rightsChanged = false;
                foreach (array_keys($rights) as $right) {
                    if ($new[$right] !== ((($target[$right] ?? 'N') === 'Y') ? 'Y' : 'N')) {
                        $rightsChanged = true;
                    }
                }

                if (!$nameChanged && !$emailChanged && !$rightsChanged && !$sendLink && $new['email'] === (string) $target['email']) {
                    $text = 'Es gab nichts zu speichern: Die Angaben für ' . $target['name'] . ' sind unverändert.';
                    if (pb_admin_finish_post($text, '?page=admins', 'info')) {
                        return;
                    }
                    $message = $text;
                    $messageType = 'info';
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            "UPDATE {$pb_admin} SET name = ?, email = ?, config = ?, admins = ?, entries = ?, `release` = ? WHERE id = ?"
                        );
                        $stmt->execute([$new['name'], $new['email'], $new['config'], $new['admins'], $new['entries'], $new['release'], $id]);
                        logSecurityEvent('ADMIN_CHANGED', [
                            'id' => $id,
                            'by' => (int) ($actor['id'] ?? 0),
                            'email' => $emailChanged,
                            'rights' => $rightsChanged,
                            'link' => $sendLink,
                        ]);

                        $parts = ['Die Angaben für ' . $new['name'] . ' sind gespeichert.'];
                        $type = 'success';
                        $mailData = array_merge($new, ['by' => $actorName]);

                        if ($emailChanged) {
                            sendAdminEmail('email_changed', array_merge($mailData, [
                                'to' => (string) $target['email'],
                                'old_email' => (string) $target['email'],
                                'new_email' => $new['email'],
                            ]));
                        }
                        if ($emailChanged || $rightsChanged) {
                            if (sendAdminEmail('edited', array_merge($mailData, ['to' => $new['email']]))) {
                                $parts[] = 'Eine E-Mail mit den neuen Angaben ging an ' . $new['email'] . '.';
                            } else {
                                $parts[] = 'Die E-Mail an ' . $new['email'] . ' konnte nicht verschickt werden.';
                                $type = 'warning';
                            }
                        }

                        if ($sendLink) {
                            $token = pb_admin_issue_password_token($pdo, $pb_admin, $id, PB_TOKEN_LIFETIME_LINK);
                            $link = pb_admin_password_link($token);
                            if (sendAdminEmail('password_link', array_merge($mailData, ['to' => $new['email'], 'link' => $link]))) {
                                $parts[] = 'Der Link zum Festlegen eines neuen Passworts ging an ' . $new['email'] . '.';
                            } else {
                                $message = $linkNotice(
                                    implode(' ', $parts) . ' Die E-Mail mit dem Link zum Festlegen eines neuen Passworts konnte aber nicht verschickt werden.',
                                    $new['name'],
                                    $link
                                );
                                $messageType = 'warning';
                                $messageIsHtml = true;
                            }
                        }

                        if ($message === '') {
                            $text = implode(' ', $parts);
                            if (pb_admin_finish_post($text, '?page=admins', $type)) {
                                return;
                            }
                            $message = $text;
                            $messageType = $type;
                        }
                    } catch (PDOException $e) {
                        logDbError('Admin update: ' . $e->getMessage());
                        $rowValues[$id] = $new;
                        $message = 'Die Änderungen konnten wegen eines Datenbankfehlers nicht gespeichert werden.';
                    }
                }
            }
        }
    }
}

// -------------------------------------------------------------------------
// Rückfrage vor dem Löschen
// -------------------------------------------------------------------------
if ($confirmDelete !== null) {
    $delId = (int) $confirmDelete['id'];
    pb_admin_card_open('Admin löschen', 'pbAdminDeleteCard');
    ?>
<div class="alert alert-danger" role="alert" id="pbDeleteQuestion">
    <p class="mb-2"><b>Soll der Zugang von <?= e($confirmDelete['name']) ?> (<?= e($confirmDelete['email']) ?>) wirklich gelöscht werden?</b></p>
    <p class="mb-0"><?= e($confirmDelete['name']) ?> kann sich danach nicht mehr anmelden und bekommt darüber eine E-Mail.
    Antworten zu Einträgen, die unter diesem Namen stehen, bleiben erhalten.</p>
</div>
<form action="?page=admins" method="post" class="d-flex flex-wrap gap-2">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete_confirm">
    <input type="hidden" name="edit_id" value="<?= $delId ?>">
    <button id="pbDeleteConfirm" type="submit" class="btn btn-danger">Ja, löschen</button>
    <a id="pbDeleteCancel" href="?page=admins" class="btn btn-outline-secondary">Nein, abbrechen</a>
</form>
    <?php
    pb_admin_card_close();

    return;
}

// -------------------------------------------------------------------------
// Liste laden
// -------------------------------------------------------------------------
$admins = [];

try {
    $stmt = $pdo->query("SELECT * FROM {$pb_admin} ORDER BY CASE WHEN id = 1 THEN 0 ELSE 1 END, name ASC");
    $admins = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    logDbError('Admin list: ' . $e->getMessage());
    if ($message === '') {
        $message = 'Die Liste der Admins konnte wegen eines Datenbankfehlers nicht geladen werden.';
    }
}
$countAdmins = count($admins);

/** Feld mit Fehlerkennzeichnung */
$invalid = static fn (string $form, string $field): string => isset($fieldErrors[$form][$field]) ? ' is-invalid' : '';
$feedback = static fn (string $form, string $field): string => isset($fieldErrors[$form][$field])
    ? '<div class="invalid-feedback">' . e($fieldErrors[$form][$field]) . '</div>'
    : '';

/**
 * Häkchen für die Rechte eines Formulars.
 *
 * @param array<string, mixed> $values
 */
$rightsFieldset = static function (string $idPrefix, string $namePrefix, string $form, array $values) use ($rights, $actor, $fieldErrors): string {
    $html = '<fieldset class="col-12"><legend class="form-label fs-6">Rechte</legend><div class="row g-2">';
    foreach ($rights as $key => $right) {
        $id = $idPrefix . $key . ($form === 'add' ? '' : '_' . $form);
        $canGrant = pb_admin_can_grant($actor, $key);
        $note = '';
        if (!$canGrant && $key !== 'admins') {
            $note = ' Dieses Recht haben Sie selbst nicht.';
        }
        $html .= '<div class="col-sm-6 col-lg-3"><div class="form-check">'
            . '<input id="' . $id . '" class="form-check-input' . (isset($fieldErrors[$form]['rights']) ? ' is-invalid' : '') . '" type="checkbox" name="'
            . $namePrefix . $key . '" value="Y"' . ((($values[$key] ?? 'N') === 'Y') ? ' checked' : '')
            . ($canGrant ? '' : ' disabled') . ' aria-describedby="' . $id . '_help">'
            . '<label for="' . $id . '" class="form-check-label">' . e($right['label']) . '</label>'
            . '<div id="' . $id . '_help" class="form-text mt-0">' . e($right['help'] . $note) . '</div>'
            . '</div></div>';
    }
    $html .= '</div>';
    if (isset($fieldErrors[$form]['rights'])) {
        $html .= '<div class="invalid-feedback d-block">' . e($fieldErrors[$form]['rights']) . '</div>';
    }

    return $html . '</fieldset>';
};

/**
 * Liste der Rechte eines Kontos als Text.
 *
 * @param array<string, mixed> $admin
 */
$rightsText = static function (array $admin) use ($rights): string {
    if (pb_admin_is_superadmin($admin)) {
        return 'alle Rechte';
    }
    $names = [];
    foreach ($rights as $key => $right) {
        if (($admin[$key] ?? 'N') === 'Y') {
            $names[] = $right['label'];
        }
    }

    return $names === [] ? 'keine Rechte' : implode(', ', $names);
};

pb_admin_card_open('Admins verwalten', 'pbAdmins');

if ($message !== '') {
    echo pb_admin_inline_message($message, $messageType, $messageIsHtml);
}
?>

<p>
Hier legen Sie fest, wer sich im AdminCenter anmelden darf und mit welchen Rechten.
Zurzeit gibt es <b id="pbAdminCount"><?= $countAdmins ?></b> <?= $countAdmins === 1 ? 'Admin' : 'Admins' ?>.
Ihren eigenen Namen, Ihre E-Mail-Adresse und Ihr Passwort ändern Sie unter <a href="?page=account">Mein Konto</a>.
</p>

<div class="card mb-4" id="pbAdminRightsHelp">
    <div class="card-body">
        <h3 class="h6">Die vier Rechte</h3>
        <dl class="row mb-2">
            <?php foreach ($rights as $right) { ?>
            <dt class="col-sm-4 col-lg-3"><?= e($right['label']) ?></dt>
            <dd class="col-sm-8 col-lg-9 mb-1"><?= e($right['help']) ?></dd>
            <?php } ?>
        </dl>
        <p class="small text-body-secondary mb-0">
            Der Superadmin ist das Konto aus der Installation. Er hat immer alle Rechte und lässt sich nicht löschen.
            Niemand kann Rechte vergeben, die er selbst nicht hat.
        </p>
    </div>
</div>

<h3 class="h6 mt-4">Admins</h3>

<?php foreach ($admins as $admin) {
    $rowId = (int) $admin['id'];
    $isSuper = pb_admin_is_superadmin($admin);
    $isSelf = $rowId === (int) ($actor['id'] ?? 0);
    $canManage = pb_admin_can_manage($actor, $admin);
    $values = $rowValues[$rowId] ?? [];
    $valName = (string) ($values['name'] ?? $admin['name']);
    $valEmail = (string) ($values['email'] ?? $admin['email']);
    $valRights = [];
    foreach (array_keys($rights) as $key) {
        $valRights[$key] = (string) ($values[$key] ?? $admin[$key] ?? 'N');
    }
    $form = (string) $rowId;
    ?>
<section class="card mb-3" id="pbAdmin<?= $rowId ?>">
    <header class="card-header d-flex flex-wrap align-items-center gap-2">
        <strong><?= e($admin['name']) ?></strong>
        <?php if ($isSuper) { ?>
            <span class="badge text-bg-warning" id="pbAdminSuper<?= $rowId ?>">Superadmin</span>
        <?php } ?>
        <?php if ($isSelf) { ?>
            <span class="badge text-bg-info">Sie</span>
        <?php } ?>
    </header>
    <div class="card-body">
    <?php if ($canManage) { ?>
        <form action="?page=admins" method="post" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="edit_id" value="<?= $rowId ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="pb_admin_name_<?= $rowId ?>" class="form-label">Name</label>
                    <input id="pb_admin_name_<?= $rowId ?>" type="text" class="form-control<?= $invalid($form, 'name') ?>" name="edit_name" maxlength="100" value="<?= e($valName) ?>">
                    <?= $feedback($form, 'name') ?>
                </div>
                <div class="col-md-6">
                    <label for="pb_admin_email_<?= $rowId ?>" class="form-label">E-Mail-Adresse</label>
                    <input id="pb_admin_email_<?= $rowId ?>" type="email" class="form-control<?= $invalid($form, 'email') ?>" name="edit_email" maxlength="250" value="<?= e($valEmail) ?>">
                    <?= $feedback($form, 'email') ?>
                </div>

                <?= $rightsFieldset('pb_admin_perm_', 'edit_', $form, $valRights) ?>

                <div class="col-12">
                    <div class="form-check">
                        <input id="pb_admin_link_<?= $rowId ?>" class="form-check-input" type="checkbox" name="send_link" value="1" aria-describedby="pb_admin_link_<?= $rowId ?>_help">
                        <label for="pb_admin_link_<?= $rowId ?>" class="form-check-label">Link zum Festlegen eines neuen Passworts schicken</label>
                        <div id="pb_admin_link_<?= $rowId ?>_help" class="form-text mt-0">
                            <?= e($admin['name']) ?> bekommt eine E-Mail mit einem Link, der 48 Stunden gilt. Das bisherige Passwort gilt bis dahin weiter.
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                    <button id="pbAdminSave<?= $rowId ?>" type="submit" name="action" value="save" class="btn btn-primary">Speichern</button>
                    <button id="pbAdminDelete<?= $rowId ?>" type="submit" name="action" value="delete" class="btn btn-outline-danger">Löschen …</button>
                    <span class="form-text m-0">Eine E-Mail geht nur raus, wenn sich E-Mail-Adresse oder Rechte ändern.</span>
                </div>
            </div>
        </form>
    <?php } else { ?>
        <p class="mb-1"><span class="text-body-secondary">E-Mail-Adresse:</span> <?= e($admin['email']) ?></p>
        <p class="mb-2" id="pbAdminRights<?= $rowId ?>"><span class="text-body-secondary">Rechte:</span> <?= e($rightsText($admin)) ?></p>
        <?php if ($isSuper) { ?>
            <p class="form-text mb-0" id="pbAdminSuperNote">Superadmin – hat immer alle Rechte und lässt sich nicht löschen.
            <?= $isSelf
                ? 'Ihren Namen, Ihre E-Mail-Adresse und Ihr Passwort ändern Sie unter <a href="?page=account">Mein Konto</a>.'
                : 'Name, E-Mail-Adresse und Passwort ändert nur der Superadmin selbst.' ?></p>
        <?php } elseif ($isSelf) { ?>
            <p class="form-text mb-0">Das sind Sie. Name, E-Mail-Adresse und Passwort ändern Sie unter <a href="?page=account">Mein Konto</a>, Ihre eigenen Rechte nicht.</p>
        <?php } else { ?>
            <p class="form-text mb-0"><?= e($denyReason($actor, $admin)) ?></p>
        <?php } ?>
    <?php } ?>
    </div>
</section>
<?php } ?>

<h3 class="h6 mt-5">Admin hinzufügen</h3>

<?php if (!$canGrantAny) { ?>
<p class="form-text" id="pbAdminAddNone">Neue Admins kann nur anlegen, wer selbst mindestens eines der Rechte
„Einträge freischalten“, „Einträge bearbeiten und löschen“ oder „Konfiguration ändern“ hat.</p>
<?php } else { ?>
<form action="?page=admins" method="post" class="card mb-3" id="pbAdminAddForm" novalidate>
    <div class="card-header">
        <strong>Neuen Admin anlegen</strong>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="pb_add_name" class="form-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="pb_add_name" type="text" class="form-control<?= $invalid('add', 'name') ?>" name="add_name" maxlength="100" required value="<?= e($addValues['name']) ?>">
                <?= $feedback('add', 'name') ?>
            </div>
            <div class="col-md-6">
                <label for="pb_add_email" class="form-label">E-Mail-Adresse <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="pb_add_email" type="email" class="form-control<?= $invalid('add', 'email') ?>" name="add_email" maxlength="250" required value="<?= e($addValues['email']) ?>">
                <?= $feedback('add', 'email') ?>
            </div>

            <?= $rightsFieldset('pb_add_perm_', 'add_', 'add', $addValues) ?>

            <div class="col-12">
                <p class="form-text mt-0" id="pbAdminAddHelp">Ein Passwort vergeben Sie nicht: Die E-Mail an die neue Adresse enthält einen Link
                zum Festlegen des Passworts (48 Stunden gültig).</p>
                <input type="hidden" name="action" value="add">
                <?= csrfField() ?>
                <button id="pbAdminAddSubmit" type="submit" class="btn btn-success">Admin hinzufügen</button>
            </div>
        </div>
    </div>
</form>
<?php } ?>

<?php
pb_admin_card_close();
