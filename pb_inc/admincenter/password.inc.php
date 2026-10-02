<?php

/**
 * PowerBook - Passwort vergessen und Passwort festlegen
 *
 * Schritt 1: Name oder E-Mail-Adresse eingeben. Gibt es das Konto, geht ein
 *            Link (30 Minuten gültig, nur einmal verwendbar) an seine Adresse.
 *            Die Antwort ist immer dieselbe, damit niemand Konten erraten kann.
 *            Drossel über pb_login_attempts (name 'reset:…'): höchstens fünf
 *            Anfragen je IP in 15 Minuten, höchstens eine Mail je Konto in
 *            5 Minuten.
 * Schritt 2: Der Link (?page=password&token=…) zeigt das Formular für das
 *            neue Passwort. Dasselbe Formular nutzen neue Admins (Link aus der
 *            Willkommensmail, 48 Stunden gültig, &welcome=1).
 *            Das alte Passwort gilt bis zum Speichern; danach setzt PowerBook
 *            pw_changed und meldet damit alle Sitzungen des Kontos ab.
 *
 * In der Datenbank steht nur der SHA-256-Wert des Link-Schlüssels.
 *
 * @license MIT
 * @copyright PowerScripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';
require_once __DIR__ . '/admin_email_helpers.inc.php';

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_admin */
$message = '';
$messageType = 'danger';
$showRequestForm = true;
$tokenAdmin = null;
$action = (string) ($_POST['action'] ?? '');
$tokenParam = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$welcome = (string) ($_POST['welcome'] ?? $_GET['welcome'] ?? '') === '1';
$passwordError = '';
$recoverValue = '';

$genericRecoveryResponse = 'Falls ein Konto zu diesem Namen oder dieser E-Mail-Adresse existiert, '
    . 'haben wir eine E-Mail mit einem Link zum Festlegen eines neuen Passworts geschickt. '
    . 'Der Link ist 30 Minuten gültig.';
$invalidLinkMessage = 'Der Link ist ungültig oder abgelaufen. Fordern Sie unten einen neuen an.';

// --- Schritt 2: Link aus der Mail aufgerufen oder neues Passwort gesendet ---
if ($tokenParam !== '' && $action !== 'recover') {
    try {
        $tokenAdmin = pb_admin_find_by_token($pdo, $pb_admin, $tokenParam);
    } catch (PDOException $e) {
        logDbError('Password token lookup: ' . $e->getMessage());
        $tokenAdmin = null;
    }

    if ($tokenAdmin === null) {
        $message = $invalidLinkMessage;
    } else {
        $showRequestForm = false;
    }
}

if ($action === 'set_password' && $tokenAdmin !== null) {
    $newPw1 = (string) ($_POST['new_password1'] ?? '');
    $newPw2 = (string) ($_POST['new_password2'] ?? '');

    if (!validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        logCsrfFailure('password_set');
        $message = pb_csrf_failed_message();
    } else {
        $error = pb_admin_validate_new_password($newPw1, $newPw2);
        if ($error !== null) {
            $passwordError = $error;
            $message = 'Das Passwort wurde nicht gespeichert. ' . $error;
        } else {
            try {
                $now = time();
                $stmt = $pdo->prepare(
                    "UPDATE {$pb_admin} SET password = ?, reset_token = NULL, reset_token_expires = NULL, pw_changed = ? WHERE id = ?"
                );
                $stmt->execute([password_hash($newPw1, PASSWORD_DEFAULT), $now, (int) $tokenAdmin['id']]);
                logSecurityEvent('PASSWORD_SET_BY_LINK', ['id' => (int) $tokenAdmin['id']]);

                // Wer im selben Browser als dieses Konto angemeldet ist, bleibt es.
                if ((int) ($_SESSION['admin_id'] ?? 0) === (int) $tokenAdmin['id']) {
                    $_SESSION['pb_login_time'] = $now;
                }

                $text = 'Ihr Passwort ist gespeichert. Melden Sie sich jetzt mit Ihrem Namen oder Ihrer E-Mail-Adresse und dem neuen Passwort an.';
                if (pb_admin_finish_post($text, '?page=login')) {
                    return;
                }
                $message = $text;
                $messageType = 'success';
                $tokenAdmin = null;
                $showRequestForm = false;
            } catch (PDOException $e) {
                logDbError('Password update: ' . $e->getMessage());
                $message = 'Das Passwort konnte wegen eines Datenbankfehlers nicht gespeichert werden.';
            }
        }
    }
} elseif ($action === 'set_password' && $tokenAdmin === null) {
    $message = $invalidLinkMessage;
}

// --- Schritt 1: Link anfordern ---
if ($action === 'recover') {
    // Ein Feld für Name oder E-Mail-Adresse; email_known nimmt ältere Formulare an.
    $recoverValue = trim((string) ($_POST['name'] ?? ''));
    if ($recoverValue === '') {
        $recoverValue = trim((string) ($_POST['email_known'] ?? ''));
    }

    if (!validateCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        logCsrfFailure('password_recover');
        $message = pb_csrf_failed_message();
    } elseif ($recoverValue === '') {
        $message = 'Bitte geben Sie Ihren Namen oder Ihre E-Mail-Adresse ein.';
    } else {
        $ip = pb_admin_client_ip();
        pb_attempts_cleanup($pdo);

        if (pb_attempts_count($pdo, $ip, 'LIKE ?', 'reset:%', 900) >= 5) {
            $message = 'Zu viele Anfragen. Bitte warten Sie 15 Minuten.';
            logSecurityEvent('PASSWORD_RESET_THROTTLED', []);
        } else {
            try {
                $stmt = $pdo->prepare(
                    "SELECT id, name, email FROM {$pb_admin} WHERE LOWER(name) = LOWER(?) OR LOWER(email) = LOWER(?) "
                    . 'ORDER BY CASE WHEN LOWER(name) = LOWER(?) THEN 0 ELSE 1 END LIMIT 1'
                );
                $stmt->execute([$recoverValue, $recoverValue, $recoverValue]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if (is_array($admin)) {
                    $adminId = (int) $admin['id'];
                    // Höchstens eine Mail je Konto in 5 Minuten.
                    $recent = pb_attempts_count($pdo, null, '= ?', 'reset:' . $adminId, 300);
                    pb_attempts_add($pdo, $ip, 'reset:' . $adminId);

                    if ($recent === 0) {
                        $token = pb_admin_issue_password_token($pdo, $pb_admin, $adminId, PB_TOKEN_LIFETIME_RESET);
                        sendAdminEmail('reset', [
                            'to' => (string) $admin['email'],
                            'name' => (string) $admin['name'],
                            'link' => pb_admin_password_link($token),
                        ]);
                        logSecurityEvent('PASSWORD_RESET_REQUESTED', ['id' => $adminId]);
                    }
                } else {
                    pb_attempts_add($pdo, $ip, 'reset:');
                }
            } catch (PDOException $e) {
                logDbError('Password recovery: ' . $e->getMessage());
            }

            // Immer dieselbe Antwort, egal ob es das Konto gibt.
            if (pb_admin_finish_post($genericRecoveryResponse, '?page=login', 'info')) {
                return;
            }
            $message = $genericRecoveryResponse;
            $messageType = 'info';
            $showRequestForm = false;
        }
    }
}

if ($tokenAdmin !== null) {
    pb_admin_card_open($welcome ? 'Willkommen im AdminCenter' : 'Neues Passwort festlegen', 'pbSetPassword');
} else {
    pb_admin_card_open('Passwort vergessen', 'pbForgotPassword');
}

if ($message !== '') {
    echo pb_admin_inline_message($message, $messageType);
}

if ($tokenAdmin !== null) { ?>
<div class="pb-card-narrow">
    <?php if ($welcome) { ?>
    <p id="pbSetPasswordIntro">Willkommen, <b><?= e($tokenAdmin['name']) ?></b>! Legen Sie jetzt Ihr Passwort für das AdminCenter fest.
    Danach melden Sie sich mit Ihrem Namen oder Ihrer E-Mail-Adresse und diesem Passwort an.</p>
    <?php } else { ?>
    <p id="pbSetPasswordIntro">Hallo <b><?= e($tokenAdmin['name']) ?></b>, legen Sie hier Ihr neues Passwort fest.
    Danach sind Sie auf allen Geräten abgemeldet und melden sich mit dem neuen Passwort an.</p>
    <?php } ?>
    <form action="?page=password" method="post" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="set_password">
        <input type="hidden" name="token" value="<?= e($tokenParam) ?>">
        <?php if ($welcome) { ?>
        <input type="hidden" name="welcome" value="1">
        <?php } ?>

        <div class="mb-3">
            <label for="pb_pw1" class="form-label">Neues Passwort <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="pb_pw1" type="password" class="form-control<?= $passwordError !== '' ? ' is-invalid' : '' ?>" name="new_password1" minlength="8" maxlength="200" required autocomplete="new-password" aria-describedby="pb_pw1_help">
            <div id="pb_pw1_help" class="form-text">Mindestens 8 Zeichen. Am sichersten ist ein langer Satz, den Sie sich merken können.</div>
        </div>

        <div class="mb-3">
            <label for="pb_pw2" class="form-label">Passwort wiederholen <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="pb_pw2" type="password" class="form-control<?= $passwordError !== '' ? ' is-invalid' : '' ?>" name="new_password2" minlength="8" maxlength="200" required autocomplete="new-password">
            <?php if ($passwordError !== '') { ?>
            <div class="invalid-feedback"><?= e($passwordError) ?></div>
            <?php } ?>
        </div>

        <button id="pbSetPasswordSubmit" type="submit" class="btn btn-primary">Passwort speichern</button>
    </form>
</div>
<?php } elseif ($showRequestForm) { ?>
<div class="pb-card-narrow">
    <p>Sie haben Ihr Passwort vergessen? Geben Sie Ihren Namen oder Ihre E-Mail-Adresse ein.
    Wir schicken Ihnen einen Link, mit dem Sie ein neues Passwort festlegen.</p>

    <form action="?page=password" method="post" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="recover">

        <div class="mb-3">
            <label for="pb_recover_name" class="form-label">Name oder E-Mail-Adresse</label>
            <input id="pb_recover_name" type="text" class="form-control" name="name" maxlength="250" autocomplete="username" value="<?= e($recoverValue) ?>">
        </div>

        <button id="pbRecoverSubmit" type="submit" class="btn btn-primary">Link anfordern</button>
    </form>

    <p class="text-body-secondary mt-3 mb-0"><small>
    Der Link ist 30 Minuten gültig und funktioniert nur einmal. Ihr bisheriges Passwort gilt weiter,
    bis Sie ein neues festlegen.
    </small></p>
</div>
<?php } ?>
<p class="pb-card-narrow mt-3 mb-0"><a id="pbBackToLogin" href="?page=login">Zurück zur Anmeldung</a></p>
<?php
pb_admin_card_close();
