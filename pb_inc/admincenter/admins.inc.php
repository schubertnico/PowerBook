<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Management
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

// BUG-006: Helper-Funktionen aus eigener Datei laden, damit sie im Root-Scope
// hoistet werden UND mehrfaches include dieser admins.inc.php (z. B. in Tests)
// keinen Redeclaration-Fatal ausloest.
require_once __DIR__ . '/admin_email_helpers.inc.php';

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_admin */
/** @var array $admin_session */
/** @var string $config_admin_url */

// Check permission
if (($admin_session['admins'] ?? 'N') !== 'Y') {
    pb_admin_card_open('Administration: Admins');
    echo pb_admin_alert('Sie haben keine Berechtigung für die Admin-Verwaltung.', 'danger');
    pb_admin_card_close();

    return;
}

// Get form data
$action = $_POST['action'] ?? '';
$add_name = trim($_POST['add_name'] ?? '');
$add_email = trim($_POST['add_email'] ?? '');
$add_config = ($_POST['add_config'] ?? '') === 'Y' ? 'Y' : 'N';
$add_admins = ($_POST['add_admins'] ?? '') === 'Y' ? 'Y' : 'N';
$add_entries = ($_POST['add_entries'] ?? '') === 'Y' ? 'Y' : 'N';
$add_release = ($_POST['add_release'] ?? '') === 'Y' ? 'Y' : 'N';

$edit_id = (int) ($_POST['edit_id'] ?? 0);
$edit_name = trim($_POST['edit_name'] ?? '');
$edit_email = trim($_POST['edit_email'] ?? '');
$edit_password1 = $_POST['edit_password1'] ?? '';
$edit_password2 = $_POST['edit_password2'] ?? '';
$edit_config = ($_POST['edit_config'] ?? '') === 'Y' ? 'Y' : 'N';
$edit_admins = ($_POST['edit_admins'] ?? '') === 'Y' ? 'Y' : 'N';
$edit_entries = ($_POST['edit_entries'] ?? '') === 'Y' ? 'Y' : 'N';
$edit_release = ($_POST['edit_release'] ?? '') === 'Y' ? 'Y' : 'N';
$delete = ($_POST['delete'] ?? '') === 'yes';

$message = '';
$messageType = '';

// Process add admin
if ($action === 'add' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    if (empty($add_name) || empty($add_email)) {
        $message = 'Bitte geben Sie einen Namen und eine E-Mail-Adresse ein!';
        $messageType = 'error';
    } elseif (!filter_var($add_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Bitte geben Sie eine gültige E-Mail-Adresse ein!';
        $messageType = 'error';
    } else {
        try {
            // Check if name exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$pb_admin} WHERE name = ?");
            $stmt->execute([$add_name]);
            if ($stmt->fetchColumn() > 0) {
                $message = 'Es gibt bereits einen Admin namens <b>' . e($add_name) . '</b>!';
                $messageType = 'error';
            } else {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$pb_admin} WHERE email = ?");
                $stmt->execute([$add_email]);
                if ($stmt->fetchColumn() > 0) {
                    $message = 'Es gibt bereits einen Admin mit dieser E-Mail-Adresse!';
                    $messageType = 'error';
                } elseif ($add_config === 'N' && $add_admins === 'N' && $add_entries === 'N' && $add_release === 'N') {
                    $message = 'Admin muss mindestens eine Berechtigung haben!';
                    $messageType = 'error';
                } else {
                    // Generate temporary password
                    $tempPassword = bin2hex(random_bytes(8));
                    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("INSERT INTO {$pb_admin} (name, password, email, config, admins, entries, `release`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$add_name, $hashedPassword, $add_email, $add_config, $add_admins, $add_entries, $add_release]);

                    // Send email notification
                    // IMP-008: Rueckgabewert erfassen, um bei Mail-Fail das
                    // Temp-Passwort einmalig im UI anzuzeigen (Self-Lockout vermeiden).
                    $mailSent = sendAdminEmail('added', [
                        'to' => $add_email,
                        'name' => $add_name,
                        'email' => $add_email,
                        'password' => $tempPassword,
                        'config' => $add_config,
                        'admins' => $add_admins,
                        'entries' => $add_entries,
                        'release' => $add_release,
                        'admin_url' => $config_admin_url ?? '',
                        'by' => $admin_session['name'] ?? 'Admin',
                    ]);

                    if (!$mailSent) {
                        $message = 'Admin erfolgreich hinzugefügt, aber die E-Mail konnte NICHT versendet werden! '
                            . '<br><b>Notieren Sie das Initial-Passwort jetzt:</b> '
                            . '<code class="bg-dark text-warning px-2 py-1 rounded">'
                            . htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8')
                            . '</code><br>'
                            . 'Das Passwort wird nicht erneut angezeigt.';
                        $messageType = 'error';
                    } else {
                        $message = 'Admin erfolgreich hinzugefügt. Er wird eine E-Mail mit seinen Daten erhalten.';
                        $messageType = 'success';
                    }

                    // Reset form
                    $add_name = $add_email = '';
                    $add_config = $add_admins = $add_entries = $add_release = 'N';
                }
            }
        } catch (PDOException $e) {
            logDbError('Admin add: ' . $e->getMessage());
            $message = 'Datenbankfehler beim Hinzufügen des Admins.';
            $messageType = 'error';
        }
    }
    regenerateCsrfToken();
}

// Process edit/delete admin
if ($action === 'edit' && $edit_id > 0 && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    try {
        // Get current admin data
        $stmt = $pdo->prepare("SELECT * FROM {$pb_admin} WHERE id = ?");
        $stmt->execute([$edit_id]);
        $editAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDbError('Admin load: ' . $e->getMessage());
        $editAdmin = null;
        $message = 'Datenbankfehler beim Laden des Admins.';
        $messageType = 'error';
    }

    if (!$editAdmin && empty($message)) {
        $message = 'Admin nicht gefunden!';
        $messageType = 'error';
    } elseif ($editAdmin && $delete) {
        // Delete admin
        if ($edit_id === (int) $admin_session['id']) {
            $message = 'Sie können sich selbst nicht löschen!';
            $messageType = 'error';
        } elseif ($edit_id === 1) {
            $message = 'SuperAdmin kann nicht gelöscht werden!';
            $messageType = 'error';
        } else {
            try {
                $deletedEmail = $editAdmin['email'];
                $deletedName = $editAdmin['name'];

                $stmt = $pdo->prepare("DELETE FROM {$pb_admin} WHERE id = ?");
                $stmt->execute([$edit_id]);

                // Send email notification
                sendAdminEmail('deleted', [
                    'to' => $deletedEmail,
                    'name' => $deletedName,
                    'by' => $admin_session['name'] ?? 'Admin',
                ]);

                $message = 'Admin erfolgreich gelöscht.';
                $messageType = 'success';
            } catch (PDOException $e) {
                logDbError('Admin delete: ' . $e->getMessage());
                $message = 'Datenbankfehler beim Löschen des Admins.';
                $messageType = 'error';
            }
        }
    } elseif ($editAdmin) {
        // Update admin
        if (empty($edit_name) || empty($edit_email)) {
            $message = 'Bitte geben Sie einen Namen und eine E-Mail-Adresse ein!';
            $messageType = 'error';
        } elseif (!filter_var($edit_email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Bitte geben Sie eine gültige E-Mail-Adresse ein!';
            $messageType = 'error';
        } else {
            try {
                // Check if name exists (excluding current admin)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$pb_admin} WHERE name = ? AND id != ?");
                $stmt->execute([$edit_name, $edit_id]);
                if ($stmt->fetchColumn() > 0) {
                    $message = 'Es gibt bereits einen Admin namens <b>' . e($edit_name) . '</b>!';
                    $messageType = 'error';
                } else {
                    // Check if email exists (excluding current admin)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$pb_admin} WHERE email = ? AND id != ?");
                    $stmt->execute([$edit_email, $edit_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $message = 'Es gibt bereits einen Admin mit dieser E-Mail-Adresse!';
                        $messageType = 'error';
                    } else {
                        // Check password strength and match
                        $passwordErrors = validatePassword($edit_password1, 8, false);
                        $confirmErrors = validatePasswordConfirmation($edit_password1, $edit_password2);

                        if (!empty($passwordErrors)) {
                            $message = $passwordErrors['password'];
                            $messageType = 'error';
                        } elseif (!empty($confirmErrors)) {
                            $message = 'Passwörter stimmen nicht überein!';
                            $messageType = 'error';
                        } else {
                            // SuperAdmin (id=1) keeps all permissions
                            if ($edit_id === 1) {
                                $edit_config = $edit_admins = $edit_entries = $edit_release = 'Y';
                            }
                            // Users can't edit their own permissions
                            elseif ($edit_id === (int) $admin_session['id']) {
                                $edit_config = $editAdmin['config'];
                                $edit_admins = $editAdmin['admins'];
                                $edit_entries = $editAdmin['entries'];
                                $edit_release = $editAdmin['release'];
                            }
                            // Check at least one permission
                            elseif ($edit_config === 'N' && $edit_admins === 'N' && $edit_entries === 'N' && $edit_release === 'N') {
                                $message = 'Admin muss mindestens eine Berechtigung haben!';
                                $messageType = 'error';
                            }

                            if (empty($message)) {
                                // Update with or without password change
                                $newPassword = null;
                                if (!empty($edit_password1)) {
                                    $newPassword = $edit_password1;
                                    $hashedPassword = password_hash($edit_password1, PASSWORD_DEFAULT);
                                    $stmt = $pdo->prepare("UPDATE {$pb_admin} SET name = ?, email = ?, password = ?, config = ?, admins = ?, entries = ?, `release` = ? WHERE id = ?");
                                    $stmt->execute([$edit_name, $edit_email, $hashedPassword, $edit_config, $edit_admins, $edit_entries, $edit_release, $edit_id]);
                                } else {
                                    $stmt = $pdo->prepare("UPDATE {$pb_admin} SET name = ?, email = ?, config = ?, admins = ?, entries = ?, `release` = ? WHERE id = ?");
                                    $stmt->execute([$edit_name, $edit_email, $edit_config, $edit_admins, $edit_entries, $edit_release, $edit_id]);
                                }

                                // Send email notification
                                sendAdminEmail('edited', [
                                    'to' => $edit_email,
                                    'name' => $edit_name,
                                    'email' => $edit_email,
                                    'password' => $newPassword,
                                    'config' => $edit_config,
                                    'admins' => $edit_admins,
                                    'entries' => $edit_entries,
                                    'release' => $edit_release,
                                    'admin_url' => $config_admin_url ?? '',
                                    'by' => $admin_session['name'] ?? 'Admin',
                                ]);

                                $message = 'Admin erfolgreich aktualisiert.';
                                $messageType = 'success';
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                logDbError('Admin update: ' . $e->getMessage());
                $message = 'Datenbankfehler beim Aktualisieren des Admins.';
                $messageType = 'error';
            }
        }
    }
    regenerateCsrfToken();
}

// Get all admins
try {
    $stmt = $pdo->query("SELECT * FROM {$pb_admin} ORDER BY name ASC");
    $admins = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $countAdmins = count($admins);
} catch (PDOException $e) {
    logDbError('Admin list: ' . $e->getMessage());
    $admins = [];
    $countAdmins = 0;
    if (empty($message)) {
        $message = 'Datenbankfehler beim Laden der Admin-Liste.';
        $messageType = 'error';
    }
}

pb_admin_card_open('Administration: Admins');

if (!empty($message)) {
    echo pb_admin_alert($message, pb_admin_message_type($messageType));
}
?>

<p>
Dies ist eine Liste mit allen Admins (zur Zeit <b><?= $countAdmins ?></b>), die bei dieser PowerBook-Version registriert sind.
Falls Sie die Berechtigung haben, können Sie diese Admins bearbeiten.
Jedoch können Sie weder sich noch den SuperAdmin löschen oder Ihre bzw. seine Berechtigungen ändern.
Nutzen Sie das Formular ganz unten, um einen neuen Admin hinzuzufuegen.
</p>

<h3 class="h6 mt-4">Admins bearbeiten</h3>

<?php foreach ($admins as $admin) {
    $isSuper = ($admin['id'] === 1);
    $isSelf = ($admin['id'] === $admin_session['id']);
    $rowId = (int) $admin['id'];
    ?>
<form action="?page=admins" method="post" class="card mb-3 pb-table-wrap">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><strong><?= e($admin['name']) ?></strong>
            <?php if ($isSuper) { ?>
                <span class="badge bg-warning text-dark ms-2">SuperAdmin</span>
            <?php } ?>
            <?php if ($isSelf) { ?>
                <span class="badge bg-info text-dark ms-2">Sie</span>
            <?php } ?>
        </span>
        <small class="text-body-secondary">ID: <?= $rowId ?></small>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="pb_admin_name_<?= $rowId ?>" class="form-label">Name</label>
                <input id="pb_admin_name_<?= $rowId ?>" type="text" class="form-control" name="edit_name" maxlength="100" value="<?= e($admin['name']) ?>">
            </div>
            <div class="col-md-4">
                <label for="pb_admin_email_<?= $rowId ?>" class="form-label">E-Mail-Adresse</label>
                <input id="pb_admin_email_<?= $rowId ?>" type="email" class="form-control" name="edit_email" maxlength="250" value="<?= e($admin['email']) ?>">
            </div>
            <div class="col-md-4">
                <label for="pb_admin_pw1_<?= $rowId ?>" class="form-label">Neues Passwort</label>
                <input id="pb_admin_pw1_<?= $rowId ?>" type="password" class="form-control mb-2" name="edit_password1" maxlength="100" placeholder="Optional, leer lassen für keine Änderung">
                <input id="pb_admin_pw2_<?= $rowId ?>" type="password" class="form-control" name="edit_password2" maxlength="100" placeholder="Wiederholen">
                <div class="form-text">Mindestens 8 Zeichen, falls geändert.</div>
            </div>

            <?php if (!$isSuper && !$isSelf) { ?>
            <fieldset class="col-12">
                <legend class="form-label">Berechtigungen</legend>
                <div class="row g-2">
                    <div class="col-sm-6 col-md-3">
                        <div class="form-check">
                            <input id="pb_admin_perm_config_<?= $rowId ?>" class="form-check-input" type="checkbox" name="edit_config" value="Y" <?= $admin['config'] === 'Y' ? 'checked' : '' ?>>
                            <label for="pb_admin_perm_config_<?= $rowId ?>" class="form-check-label">Konfiguration</label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="form-check">
                            <input id="pb_admin_perm_admins_<?= $rowId ?>" class="form-check-input" type="checkbox" name="edit_admins" value="Y" <?= $admin['admins'] === 'Y' ? 'checked' : '' ?>>
                            <label for="pb_admin_perm_admins_<?= $rowId ?>" class="form-check-label">Admin-Verwaltung</label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="form-check">
                            <input id="pb_admin_perm_release_<?= $rowId ?>" class="form-check-input" type="checkbox" name="edit_release" value="Y" <?= $admin['release'] === 'Y' ? 'checked' : '' ?>>
                            <label for="pb_admin_perm_release_<?= $rowId ?>" class="form-check-label">Eintrags-Freischaltung</label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="form-check">
                            <input id="pb_admin_perm_entries_<?= $rowId ?>" class="form-check-input" type="checkbox" name="edit_entries" value="Y" <?= $admin['entries'] === 'Y' ? 'checked' : '' ?>>
                            <label for="pb_admin_perm_entries_<?= $rowId ?>" class="form-check-label">Eintrags-Verwaltung</label>
                        </div>
                    </div>
                </div>
            </fieldset>

            <div class="col-12">
                <div class="form-check text-danger">
                    <input id="pb_admin_delete_<?= $rowId ?>" class="form-check-input" type="checkbox" name="delete" value="yes">
                    <label for="pb_admin_delete_<?= $rowId ?>" class="form-check-label fw-semibold">
                        Diesen Admin endgültig löschen
                    </label>
                </div>
            </div>
            <?php } else { ?>
            <div class="col-12">
                <div class="alert alert-info py-2 mb-0" role="status">
                    <small>Berechtigungen können für diesen Eintrag nicht über dieses Formular geändert werden.</small>
                </div>
            </div>
            <?php } ?>

            <div class="col-12">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_id" value="<?= $rowId ?>">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-primary">Speichern / Update</button>
            </div>
        </div>
    </div>
</form>
<?php } ?>

<h3 class="h6 mt-5">Admin hinzufügen</h3>

<form action="?page=admins" method="post" class="card mb-3" novalidate>
    <div class="card-header bg-success text-white">
        <strong>Neuen Admin anlegen</strong>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="pb_add_name" class="form-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="pb_add_name" type="text" class="form-control" name="add_name" maxlength="100" required value="<?= e($add_name) ?>">
            </div>
            <div class="col-md-6">
                <label for="pb_add_email" class="form-label">E-Mail-Adresse <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="pb_add_email" type="email" class="form-control" name="add_email" maxlength="250" required value="<?= e($add_email) ?>">
            </div>

            <fieldset class="col-12">
                <legend class="form-label">Berechtigungen</legend>
                <div class="row g-2">
                    <div class="col-sm-6 col-md-3">
                        <div class="form-check">
                            <input id="pb_add_perm_config" class="form-check-input" type="checkbox" name="add_config" value="Y" <?= $add_config === 'Y' ? 'checked' : '' ?>>
                            <label for="pb_add_perm_config" class="form-check-label">Konfiguration</label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="form-check">
                            <input id="pb_add_perm_admins" class="form-check-input" type="checkbox" name="add_admins" value="Y" <?= $add_admins === 'Y' ? 'checked' : '' ?>>
                            <label for="pb_add_perm_admins" class="form-check-label">Admin-Verwaltung</label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="form-check">
                            <input id="pb_add_perm_release" class="form-check-input" type="checkbox" name="add_release" value="Y" <?= $add_release === 'Y' ? 'checked' : '' ?>>
                            <label for="pb_add_perm_release" class="form-check-label">Eintrags-Freischaltung</label>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="form-check">
                            <input id="pb_add_perm_entries" class="form-check-input" type="checkbox" name="add_entries" value="Y" <?= $add_entries === 'Y' ? 'checked' : '' ?>>
                            <label for="pb_add_perm_entries" class="form-check-label">Eintrags-Verwaltung</label>
                        </div>
                    </div>
                </div>
                <div class="form-text">Mindestens eine Berechtigung muss aktiv sein.</div>
            </fieldset>

            <div class="col-12">
                <input type="hidden" name="action" value="add">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-success">Admin hinzufügen</button>
            </div>
        </div>
    </div>
</form>

<?php
pb_admin_card_close();
