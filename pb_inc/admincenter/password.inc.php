<?php

/**
 * PowerBook - Password Recovery (Token-Flow, BUG-009 + BUG-010 Fix)
 *
 * Schritt 1: User fordert Recovery an → Token wird in DB gespeichert +
 *            Mail mit Reset-Link versandt. Response IMMER generisch
 *            (keine User Enumeration, BUG-009).
 * Schritt 2: User klickt Link (?page=password&token=...) → Formular zum
 *            Setzen eines neuen Passworts. Altes Passwort bleibt gueltig
 *            bis neues gespeichert wird (BUG-010).
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 */

declare(strict_types=1);

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
 * Generische Response fuer Recovery-Anfragen — verraet nicht, ob der Account existiert.
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
        $message = 'Der Reset-Link ist ungueltig oder abgelaufen. Bitte erneut anfordern.';
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
        $message = 'Der Reset-Link ist ungueltig oder abgelaufen. Bitte erneut anfordern.';
        $messageType = 'error';
    } elseif (mb_strlen($newPw1) < 8) {
        $message = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        $messageType = 'error';
        $showSetPasswordForm = true;
        $showForm = false;
    } elseif ($newPw1 !== $newPw2) {
        $message = 'Die beiden Passwoerter stimmen nicht ueberein.';
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
            $message = 'Passwort erfolgreich geaendert. Sie koennen sich jetzt einloggen.';
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

                $adminUrl = (string) ($config_admin_url ?? '');
                $adminUrlTrimmed = rtrim($adminUrl, '/');
                $resetLink = $adminUrlTrimmed !== ''
                    ? $adminUrlTrimmed . '/index.php?page=password&token=' . $token
                    : '?page=password&token=' . $token;

                $to = sanitizeEmailHeader((string) $admin['email']);
                if ($to !== '') {
                    $subject = 'PowerBook: Passwort zuruecksetzen';
                    $headers = "From: PowerBook <noreply@powerbook.local>\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    $body = "Hallo,\n\n";
                    $body .= "klicken Sie auf den folgenden Link, um ein neues Passwort zu setzen:\n\n";
                    $body .= $resetLink . "\n\n";
                    $body .= "Der Link ist 30 Minuten gueltig.\n\n";
                    $body .= "Falls Sie kein Passwort zuruecksetzen wollten, ignorieren Sie diese Mail.\n\n";
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
?>

<tr><td bgcolor="#3F5070" align="center">
    <b class="headline">P A S S W O R T &nbsp; &nbsp; Z U R Ü C K S E T Z E N</b>
</td></tr>

<tr><td bgcolor="#001F3F" valign="top">

<?php if (!empty($message)) { ?>
<div style="padding: 10px; margin: 10px 0; background: <?= $messageType === 'success' ? '#003300' : ($messageType === 'error' ? '#330000' : '#002244') ?>; border: 1px solid <?= $messageType === 'success' ? '#00FF00' : ($messageType === 'error' ? '#FF0000' : '#6090FF') ?>;">
    <?= $message ?>
</div>
<?php } ?>

<?php if ($showSetPasswordForm) { ?>
<p><b>Neues Passwort festlegen</b></p>
<form action="?page=password" method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="set_password">
    <input type="hidden" name="token" value="<?= e($tokenParam) ?>">
    <table border="0">
        <tr>
            <td width="180">Neues Passwort:</td>
            <td><input type="password" name="new_password1" minlength="8" required></td>
        </tr>
        <tr>
            <td>Passwort wiederholen:</td>
            <td><input type="password" name="new_password2" minlength="8" required></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="Passwort setzen"></td>
        </tr>
    </table>
</form>
<p><small>Mindestens 8 Zeichen.</small></p>
<?php } elseif ($showForm) { ?>
<p>
Sie haben Ihr Passwort vergessen? <b>Kein Problem!</b>
Geben Sie Ihren Namen oder Ihre E-Mail-Adresse ein — wir schicken Ihnen einen Link zum Zuruecksetzen des Passworts.
</p>

<form action="?page=password" method="post">
<?= csrfField() ?>
<input type="hidden" name="action" value="recover">

<table border="0">
    <tr>
        <td width="100">Name:</td>
        <td>
            <input type="text" name="name" size="21" maxlength="250">
            <small>(oder)</small>
        </td>
    </tr>
    <tr>
        <td width="100">E-Mail:</td>
        <td><input type="text" name="email_known" size="29" maxlength="250"></td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td><input type="submit" value="Reset-Link anfordern"></td>
    </tr>
</table>
</form>

<p><small>
<b>Hinweis:</b> Nach Klick auf den Mail-Link koennen Sie ein neues Passwort festlegen.
Der Reset-Link ist 30 Minuten gueltig. Ihr altes Passwort bleibt bis zum erfolgreichen
Neu-Setzen gueltig.
</small></p>
<?php } ?>

</td></tr>
