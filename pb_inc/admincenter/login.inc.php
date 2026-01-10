<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Center Login Page
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

$show_form = !empty($welcome_admin) ? 'no' : 'yes';
?>

<tr>
    <td bgcolor="#3F5070" align="center">
        <b class="headline">L O G I N</b>
    </td>
</tr>
<tr>
    <td bgcolor="#001F3F" valign="top">

<?php
echo $login_message ?? '';

if ($show_form !== 'no'):
?>

<p>Ein Login ist erforderlich, um administrative Funktionen zu nutzen.</p>

<form action="?page=login" method="post">
    <?= csrfField() ?>
    <table border="0">
        <tr>
            <td width="100">Name:</td>
            <td><input type="text" name="name" value="<?= e($name ?? '') ?>"></td>
        </tr>
        <tr>
            <td width="100">Passwort:</td>
            <td>
                <input type="password" name="password">
                <small><a href="?page=password">Passwort vergessen?</a></small>
            </td>
        </tr>
        <tr>
            <td width="100">&nbsp;</td>
            <td>
                <input type="hidden" name="login" value="yes">
                <input type="submit" value="Login">
            </td>
        </tr>
    </table>
</form>

<p><small>Die Anmeldung erfolgt über eine sichere Session (kein Passwort im Cookie).</small></p>

<?php else: ?>

<p>Sie sind bereits eingeloggt als <b><?= e($welcome_admin) ?></b>.</p>
<p><a href="?page=home">Zum AdminCenter</a> | <a href="?page=logout">Logout</a></p>

<?php endif; ?>

    </td>
</tr>
