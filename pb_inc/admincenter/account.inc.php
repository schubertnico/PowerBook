<?php
/**
 * PowerBook - PHP Guestbook System
 * Mein Konto: eigener Name, eigene E-Mail-Adresse, eigenes Passwort
 *
 * Für jedes angemeldete Konto erreichbar (?page=account). Jede Änderung
 * verlangt das aktuelle Passwort. Ein neues Passwort setzt pw_changed; damit
 * meldet index.php alle anderen Sitzungen dieses Kontos ab, die eigene
 * bleibt angemeldet (pb_login_time wird mitgezogen).
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
$accountId = (int) ($admin_session['id'] ?? 0);

if ($accountId <= 0) {
    pb_admin_card_open('Mein Konto', 'pbAccount');
    echo pb_admin_inline_message('Bitte melden Sie sich an.', 'danger');
    pb_admin_card_close();

    return;
}

$account = null;

try {
    $account = pb_admin_load($pdo, $pb_admin, $accountId);
} catch (PDOException $e) {
    logDbError('Account load: ' . $e->getMessage());
}

if ($account === null) {
    pb_admin_card_open('Mein Konto', 'pbAccount');
    echo pb_admin_inline_message('Ihr Konto wurde nicht gefunden. Bitte melden Sie sich neu an.', 'danger');
    pb_admin_card_close();

    return;
}

$message = '';
$messageType = 'danger';
/** @var array<string, string> $errors */
$errors = [];
$valName = (string) $account['name'];
$valEmail = (string) $account['email'];

if (($_POST['action'] ?? '') === 'account') {
    $valName = trim((string) ($_POST['account_name'] ?? ''));
    $valEmail = trim((string) ($_POST['account_email'] ?? ''));
    $newPassword = (string) ($_POST['account_password'] ?? '');
    $newPassword2 = (string) ($_POST['account_password2'] ?? '');
    $currentPassword = (string) ($_POST['account_current'] ?? '');
    $ip = pb_admin_client_ip();

    if (!validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        logCsrfFailure('account');
        $message = pb_csrf_failed_message();
    } elseif (pb_attempts_count($pdo, $ip, 'NOT LIKE ?', 'reset:%', 900) >= 10) {
        $message = 'Zu viele Fehlversuche. Bitte warten Sie 15 Minuten.';
    } else {
        $nameError = pb_admin_validate_name($valName);
        if ($nameError !== null) {
            $errors['name'] = $nameError;
        } elseif (pb_admin_value_taken($pdo, $pb_admin, 'name', $valName, $accountId)) {
            $errors['name'] = 'Es gibt bereits einen Admin mit diesem Namen.';
        }
        $emailError = pb_admin_validate_email($valEmail);
        if ($emailError !== null) {
            $errors['email'] = $emailError;
        } elseif (pb_admin_value_taken($pdo, $pb_admin, 'email', $valEmail, $accountId)) {
            $errors['email'] = 'Diese E-Mail-Adresse gehört schon zu einem anderen Admin.';
        }
        if ($newPassword !== '' || $newPassword2 !== '') {
            $passwordError = pb_admin_validate_new_password($newPassword, $newPassword2);
            if ($passwordError !== null) {
                $errors['password'] = $passwordError;
            }
        }

        if ($currentPassword === '') {
            $errors['current'] = 'Bitte geben Sie zur Bestätigung Ihr aktuelles Passwort ein.';
        } elseif (!password_verify($currentPassword, (string) $account['password'])) {
            $errors['current'] = 'Das aktuelle Passwort stimmt nicht.';
            pb_attempts_add($pdo, $ip, 'account:' . $accountId);
            logSecurityEvent('ACCOUNT_PASSWORD_WRONG', ['id' => $accountId]);
        }

        $nameChanged = $valName !== (string) $account['name'];
        $emailChanged = $valEmail !== (string) $account['email'];
        $passwordChanged = $newPassword !== '';

        if ($errors !== []) {
            $message = isset($errors['current']) && count($errors) === 1
                ? 'Nichts gespeichert: ' . $errors['current']
                : 'Nichts gespeichert. Bitte prüfen Sie die markierten Felder.';
        } elseif (!$nameChanged && !$emailChanged && !$passwordChanged) {
            $text = 'Es gab nichts zu speichern: Ihre Angaben sind unverändert.';
            if (pb_admin_finish_post($text, '?page=account', 'info')) {
                return;
            }
            $message = $text;
            $messageType = 'info';
        } else {
            try {
                if ($passwordChanged) {
                    $now = time();
                    $stmt = $pdo->prepare(
                        "UPDATE {$pb_admin} SET name = ?, email = ?, password = ?, pw_changed = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?"
                    );
                    $stmt->execute([$valName, $valEmail, password_hash($newPassword, PASSWORD_DEFAULT), $now, $accountId]);
                    // Die eigene Sitzung bleibt angemeldet, alle anderen meldet index.php ab.
                    $_SESSION['pb_login_time'] = $now;
                } else {
                    $stmt = $pdo->prepare("UPDATE {$pb_admin} SET name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$valName, $valEmail, $accountId]);
                }
                $_SESSION['admin_name'] = $valName;
                logSecurityEvent('ACCOUNT_CHANGED', [
                    'id' => $accountId,
                    'name' => $nameChanged,
                    'email' => $emailChanged,
                    'password' => $passwordChanged,
                ]);

                if ($emailChanged) {
                    sendAdminEmail('email_changed', [
                        'to' => (string) $account['email'],
                        'name' => $valName,
                        'by' => $valName,
                        'old_email' => (string) $account['email'],
                        'new_email' => $valEmail,
                    ]);
                }
                if ($passwordChanged) {
                    sendAdminEmail('password_changed', ['to' => $valEmail, 'name' => $valName]);
                }

                $text = 'Ihre Änderungen sind gespeichert.';
                if ($passwordChanged) {
                    $text .= ' Ihr neues Passwort gilt ab sofort. Andere Geräte, auf denen Sie angemeldet waren, sind jetzt abgemeldet.';
                }
                if (pb_admin_finish_post($text, '?page=account')) {
                    return;
                }
                $message = $text;
                $messageType = 'success';
                $account['name'] = $valName;
                $account['email'] = $valEmail;
            } catch (PDOException $e) {
                logDbError('Account update: ' . $e->getMessage());
                $message = 'Ihre Änderungen konnten wegen eines Datenbankfehlers nicht gespeichert werden.';
            }
        }
    }
}

$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>'
    : '';

$isSuper = pb_admin_is_superadmin($account);
$rightNames = [];
foreach (pb_admin_rights() as $key => $right) {
    if ($isSuper || ($account[$key] ?? 'N') === 'Y') {
        $rightNames[] = $right['label'];
    }
}

pb_admin_card_open('Mein Konto', 'pbAccount');

if ($message !== '') {
    echo pb_admin_inline_message($message, $messageType);
}
?>
<p>Hier ändern Sie Ihren Namen, Ihre E-Mail-Adresse und Ihr Passwort.
Zur Bestätigung geben Sie unten immer Ihr aktuelles Passwort ein.</p>

<p id="pbAccountRights" class="mb-4">
    <span class="text-body-secondary">Ihre Rechte:</span>
    <?php if ($isSuper) { ?>
        <span class="badge text-bg-warning">Superadmin</span> hat immer alle Rechte.
    <?php } elseif ($rightNames === []) { ?>
        keine
    <?php } else { ?>
        <?= e(implode(', ', $rightNames)) ?>.
    <?php } ?>
    <?php if (!$isSuper) { ?>
        <span class="text-body-secondary">Ihre Rechte ändert ein Admin mit dem Recht „Admins verwalten“.</span>
    <?php } ?>
</p>

<form action="?page=account" method="post" class="pb-card-narrow" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="account">

    <div class="mb-3">
        <label for="pbAccountName" class="form-label">Name</label>
        <input id="pbAccountName" type="text" class="form-control<?= $invalid('name') ?>" name="account_name" maxlength="100" required autocomplete="username" value="<?= e($valName) ?>">
        <?= $feedback('name') ?>
        <div class="form-text">Mit diesem Namen oder Ihrer E-Mail-Adresse melden Sie sich an.</div>
    </div>

    <div class="mb-3">
        <label for="pbAccountEmail" class="form-label">E-Mail-Adresse</label>
        <input id="pbAccountEmail" type="email" class="form-control<?= $invalid('email') ?>" name="account_email" maxlength="250" required autocomplete="email" value="<?= e($valEmail) ?>">
        <?= $feedback('email') ?>
        <div class="form-text">Hierhin schickt PowerBook den Link, wenn Sie Ihr Passwort vergessen.</div>
    </div>

    <div class="mb-3">
        <label for="pbAccountPassword" class="form-label">Neues Passwort <span class="text-body-secondary">(optional)</span></label>
        <input id="pbAccountPassword" type="password" class="form-control<?= $invalid('password') ?>" name="account_password" maxlength="200" autocomplete="new-password" aria-describedby="pbAccountPasswordHelp">
        <div id="pbAccountPasswordHelp" class="form-text">Mindestens 8 Zeichen. Leer lassen, wenn Sie Ihr Passwort behalten möchten.
        Nach dem Ändern sind Sie auf allen anderen Geräten abgemeldet.</div>
    </div>

    <div class="mb-3">
        <label for="pbAccountPassword2" class="form-label">Neues Passwort wiederholen</label>
        <input id="pbAccountPassword2" type="password" class="form-control<?= $invalid('password') ?>" name="account_password2" maxlength="200" autocomplete="new-password">
        <?= $feedback('password') ?>
    </div>

    <div class="mb-3">
        <label for="pbAccountCurrent" class="form-label">Aktuelles Passwort <span class="text-danger" aria-hidden="true">*</span></label>
        <input id="pbAccountCurrent" type="password" class="form-control<?= $invalid('current') ?>" name="account_current" maxlength="200" required autocomplete="current-password">
        <?= $feedback('current') ?>
        <div class="form-text">Zur Bestätigung, dass wirklich Sie die Änderung vornehmen.</div>
    </div>

    <button id="pbAccountSubmit" type="submit" class="btn btn-primary">Speichern</button>
</form>
<?php
pb_admin_card_close();
