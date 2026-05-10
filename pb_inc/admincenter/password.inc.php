<?php

/**
 * PowerBook - Password Recovery (Token-Flow, BUG-009 + BUG-010 Fix)
 *
 * Schritt 1: User fordert Recovery an → Token wird in DB gespeichert +
 *            Mail mit Reset-Link versandt. Response IMMER generisch
 *            (keine User Enumeration, BUG-009).
 * Schritt 2: User klickt Link (?page=password&token=...) → Formular zum
 *            Setzen eines neuen Passworts. Altes Passwort bleibt gültig
 *            bis neues gespeichert wird (BUG-010).
 *
 * @license MIT
 * @copyright PowerScripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_admin */
/** @var string|null $config_admin_url */
$message = '';
$messageType = '';
$showForm = true;
$showSetPasswordForm = false;
$action = $_POST['action'] ?? '';
$tokenParam = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

/**
 * Generische Response für Recovery-Anfragen — verraet nicht, ob der Account existiert.
 */
$genericRecoveryResponse =
    'Falls ein Konto zu diesem Namen oder dieser E-Mail-Adresse existiert, '
    . 'wurde eine E-Mail mit einem Reset-Link verschickt.';

// --- Schritt 2: Token-Link aufgerufen (Anzeige des Set-Password-Formulars) ---
if ($tokenParam !== '' && $action !== 'set_password' && $action !== 'recover') {
    try {
        $stmt = $pdo->prepare("SELECT id, name, reset_token_expires FROM {$pb_admin} WHERE reset_token = ? LIMIT 1");
        $stmt->execute([$tokenParam]);
        $tokenAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDbError('Password token lookup: ' . $e->getMessage());
        $tokenAdmin = null;
    }

    if (!$tokenAdmin || (int) $tokenAdmin['reset_token_expires'] < time()) {
        $message = 'Der Reset-Link ist ungültig oder abgelaufen. Bitte erneut anfordern.';
        $messageType = 'error';
    } else {
        $showSetPasswordForm = true;
        $showForm = false;
    }
}

// --- Schritt 2b: Neues Passwort wird gespeichert ---
if ($action === 'set_password' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $token = trim((string) ($_POST['token'] ?? ''));
    $newPw1 = (string) ($_POST['new_password1'] ?? '');
    $newPw2 = (string) ($_POST['new_password2'] ?? '');

    try {
        $stmt = $pdo->prepare("SELECT id, reset_token_expires FROM {$pb_admin} WHERE reset_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $tokenAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDbError('Password token verify: ' . $e->getMessage());
        $tokenAdmin = null;
    }

    if (!$tokenAdmin || (int) $tokenAdmin['reset_token_expires'] < time()) {
        $message = 'Der Reset-Link ist ungültig oder abgelaufen. Bitte erneut anfordern.';
        $messageType = 'error';
    } elseif (mb_strlen($newPw1) < 8) {
        $message = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        $messageType = 'error';
        $showSetPasswordForm = true;
        $showForm = false;
    } elseif ($newPw1 !== $newPw2) {
        $message = 'Die beiden Passwörter stimmen nicht überein.';
        $messageType = 'error';
        $showSetPasswordForm = true;
        $showForm = false;
    } else {
        $hashed = password_hash($newPw1, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                "UPDATE {$pb_admin} SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?"
            );
            $stmt->execute([$hashed, (int) $tokenAdmin['id']]);
            $message = 'Passwort erfolgreich geändert. Sie können sich jetzt einloggen.';
            $messageType = 'success';
            $showForm = false;
            $showSetPasswordForm = false;
        } catch (PDOException $e) {
            logDbError('Password update: ' . $e->getMessage());
            $message = 'Datenbankfehler beim Speichern des neuen Passworts.';
            $messageType = 'error';
        }
    }
    regenerateCsrfToken();
}

// --- Schritt 1: Recovery anfordern ---
if ($action === 'recover' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $emailKnown = trim((string) ($_POST['email_known'] ?? ''));

    if ($name === '' && $emailKnown === '') {
        $message = 'Bitte geben Sie Ihren Namen oder Ihre E-Mail-Adresse ein.';
        $messageType = 'error';
    } else {
        try {
            $admin = null;
            if ($name !== '') {
                $stmt = $pdo->prepare("SELECT id, name, email FROM {$pb_admin} WHERE name = ? LIMIT 1");
                $stmt->execute([$name]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($emailKnown !== '') {
                $stmt = $pdo->prepare("SELECT id, name, email FROM {$pb_admin} WHERE email = ? LIMIT 1");
                $stmt->execute([$emailKnown]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($admin) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + 30 * 60;
                $stmt = $pdo->prepare(
                    "UPDATE {$pb_admin} SET reset_token = ?, reset_token_expires = ? WHERE id = ?"
                );
                $stmt->execute([$token, $expires, (int) $admin['id']]);

                // Reset-Link MUSS vollstaendig (Schema + Host + Pfad) sein, damit
                // er in der Mail anklickbar ist. Reihenfolge:
                //   1. Konfigurierte $config_admin_url (Admin → Konfiguration).
                //   2. Fallback aus aktueller Request-URL ($_SERVER) — funktioniert
                //      sofort nach Installation, ohne dass der Admin die Konfig
                //      anpassen muss.
                $adminUrl = trim((string) ($config_admin_url ?? ''));
                if ($adminUrl !== '') {
                    $base = rtrim($adminUrl, '/');
                } else {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                    $self = (string) ($_SERVER['PHP_SELF'] ?? '/pb_inc/admincenter/index.php');
                    // dirname() liefert das Verzeichnis ohne Trailing-Slash.
                    $base = $scheme . '://' . $host . rtrim(str_replace('\\', '/', dirname($self)), '/');
                }
                $resetLink = $base . '/index.php?page=password&token=' . $token;

                $to = sanitizeEmailHeader((string) $admin['email']);
                if ($to !== '') {
                    $subject = 'PowerBook: Passwort zurücksetzen';
                    $headers = "From: PowerBook <noreply@powerbook.local>\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    $body = "Hallo,\n\n";
                    $body .= "klicken Sie auf den folgenden Link, um ein neues Passwort zu setzen:\n\n";
                    $body .= $resetLink . "\n\n";
                    $body .= "Der Link ist 30 Minuten gültig.\n\n";
                    $body .= "Falls Sie kein Passwort zurücksetzen wollten, ignorieren Sie diese Mail.\n\n";
                    $body .= "--------------------------------------------------------\n";
                    $body .= "PowerBook - PHP Guestbook System\n";
                    $body .= 'DIESE E-MAIL WURDE AUTOMATISCH GENERIERT!';

                    sendEmail($to, $subject, $body, $headers, 'Password Reset Token');
                }
            }
        } catch (PDOException $e) {
            logDbError('Password recovery: ' . $e->getMessage());
        }

        // IMMER dieselbe generische Antwort (BUG-009: kein User Enumeration).
        $message = $genericRecoveryResponse;
        $messageType = 'info';
        $showForm = false;
    }
    regenerateCsrfToken();
}

pb_admin_card_open('Passwort zurücksetzen');

if (!empty($message)) {
    echo pb_admin_alert($message, pb_admin_message_type($messageType));
}

if ($showSetPasswordForm) { ?>
<div class="pb-card-narrow">
    <p><b>Neues Passwort festlegen</b></p>
    <form action="?page=password" method="post" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="set_password">
        <input type="hidden" name="token" value="<?= e($tokenParam) ?>">

        <div class="mb-3">
            <label for="pb_pw1" class="form-label">Neues Passwort <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="pb_pw1" type="password" class="form-control" name="new_password1" minlength="8" required autocomplete="new-password">
            <div class="form-text">Mindestens 8 Zeichen.</div>
        </div>

        <div class="mb-3">
            <label for="pb_pw2" class="form-label">Passwort wiederholen <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="pb_pw2" type="password" class="form-control" name="new_password2" minlength="8" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">Passwort setzen</button>
    </form>
</div>
<?php } elseif ($showForm) { ?>
<div class="pb-card-narrow">
    <p>
    Sie haben Ihr Passwort vergessen? <b>Kein Problem!</b>
    Geben Sie Ihren Namen oder Ihre E-Mail-Adresse ein — wir schicken Ihnen einen Link
    zum Zurücksetzen des Passworts.
    </p>

    <form action="?page=password" method="post" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="recover">

        <div class="mb-3">
            <label for="pb_recover_name" class="form-label">Name</label>
            <input id="pb_recover_name" type="text" class="form-control" name="name" maxlength="250" autocomplete="username">
            <div class="form-text">Oder geben Sie unten die E-Mail-Adresse an.</div>
        </div>

        <div class="mb-3">
            <label for="pb_recover_email" class="form-label">E-Mail-Adresse</label>
            <input id="pb_recover_email" type="email" class="form-control" name="email_known" maxlength="250" autocomplete="email">
        </div>

        <button type="submit" class="btn btn-primary">Reset-Link anfordern</button>
    </form>

    <p class="text-body-secondary mt-3 mb-0"><small>
    <b>Hinweis:</b> Nach Klick auf den Mail-Link können Sie ein neues Passwort festlegen.
    Der Reset-Link ist 30 Minuten gültig. Ihr altes Passwort bleibt bis zum erfolgreichen
    Neu-Setzen gültig.
    </small></p>
</div>
<?php }

pb_admin_card_close();
