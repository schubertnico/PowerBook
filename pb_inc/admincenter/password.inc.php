<?php
/**
 * PowerBook - PHP Guestbook System
 * Password Recovery
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_admin */

$message = '';
$messageType = '';
$showForm = true;
$action = $_POST['action'] ?? '';

// Process password recovery request
if ($action === 'recover' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $name = trim($_POST['name'] ?? '');
    $email_known = trim($_POST['email_known'] ?? '');

    if (empty($name) && empty($email_known)) {
        $message = 'Bitte geben Sie Ihren Namen oder Ihre E-Mail-Adresse ein!';
        $messageType = 'error';
    } else {
        try {
            $admin = null;

            if (!empty($name)) {
                $stmt = $pdo->prepare("SELECT id, name, email FROM {$pb_admin} WHERE name = ?");
                $stmt->execute([$name]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif (!empty($email_known)) {
                $stmt = $pdo->prepare("SELECT id, name, email FROM {$pb_admin} WHERE email = ?");
                $stmt->execute([$email_known]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$admin) {
                $message = 'Admin in Datenbank nicht gefunden!';
                $messageType = 'error';
            } else {
                // Generate new temporary password
                $newPassword = bin2hex(random_bytes(8));
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update password in database
                $stmt = $pdo->prepare("UPDATE {$pb_admin} SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $admin['id']]);

                // Send email with new password
                $to = sanitizeEmailHeader($admin['email']);
                $subject = 'PowerBook: Neues Passwort';
                $headers = "From: PowerBook <noreply@powerbook.local>\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                $body = "Hallo, {$admin['name']}!\n\n";
                $body .= "Hier ist Ihr neues PowerBook AdminCenter Passwort:\n\n";
                $body .= "{$newPassword}\n\n";
                $body .= "Bitte loggen Sie sich ein und ändern Sie Ihr Passwort!\n";
                $body .= "Geben Sie dieses Passwort niemals weiter!\n\n";
                $body .= "--------------------------------------------------------\n";
                $body .= "PowerBook - PHP Guestbook System\n";
                $body .= "https://github.com/schubertnico/PowerBook.git\n\n";
                $body .= "DIESE E-MAIL WURDE AUTOMATISCH GENERIERT!";

                sendEmail($to, $subject, $body, $headers, 'Password Recovery');

                $message = "E-Mail erfolgreich verschickt an <b>" . e($admin['email']) . "</b>. Sie sollten die E-Mail in ein paar Minuten erhalten.";
                $messageType = 'success';
                $showForm = false;
            }
        } catch (PDOException $e) {
            logDbError('Password recovery: ' . $e->getMessage());
            $message = 'Datenbankfehler bei der Passwort-Wiederherstellung.';
            $messageType = 'error';
        }
    }
    regenerateCsrfToken();
}
?>

<tr><td bgcolor="#3F5070" align="center">
    <b class="headline">P A S S W O R T &nbsp; &nbsp; Z U R Ü C K S E T Z E N</b>
</td></tr>

<tr><td bgcolor="#001F3F" valign="top">

<?php if (!empty($message)): ?>
<div style="padding: 10px; margin: 10px 0; background: <?= $messageType === 'success' ? '#003300' : '#330000' ?>; border: 1px solid <?= $messageType === 'success' ? '#00FF00' : '#FF0000' ?>;">
    <?= $message ?>
</div>
<?php endif; ?>

<?php if ($showForm): ?>
<p>
Sie haben Ihr Passwort vergessen? <b>Kein Problem!</b>
Falls Sie noch Ihre E-Mail-Adresse oder Ihren Admin-Namen wissen,
kann Ihnen PowerBook ein neues Passwort per E-Mail zuschicken.
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
        <td><input type="submit" value="Passwort anfordern"></td>
    </tr>
</table>
</form>

<p><small>
<b>Hinweis:</b> Nach der Anforderung wird Ihr altes Passwort ungültig
und Sie erhalten ein neues temporäres Passwort per E-Mail.
Bitte ändern Sie dieses Passwort nach dem Login.
</small></p>
<?php endif; ?>

</td></tr>
