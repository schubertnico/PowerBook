<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Center Login Page
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

$show_form = !empty($welcome_admin) ? 'no' : 'yes';

pb_admin_card_open('Login');

if (!empty($login_message)) {
    // Bei einem erfolgreichen Login startet die Meldung mit "Login erfolgreich" — als Erfolg darstellen.
    $isSuccess = stripos($login_message, 'erfolgreich') !== false;
    echo pb_admin_alert($login_message, $isSuccess ? 'success' : 'danger');
}

if ($show_form !== 'no') {
    ?>
<div class="pb-card-narrow">
    <p>Ein Login ist erforderlich, um administrative Funktionen zu nutzen.</p>

    <form action="?page=login" method="post" novalidate>
        <?= csrfField() ?>

        <div class="mb-3">
            <label for="pb_login_name" class="form-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="pb_login_name" type="text" class="form-control" name="name" required value="<?= e($name ?? '') ?>" autocomplete="username">
        </div>

        <div class="mb-3">
            <label for="pb_login_password" class="form-label">Passwort <span class="text-danger" aria-hidden="true">*</span></label>
            <input id="pb_login_password" type="password" class="form-control" name="password" required autocomplete="current-password">
            <div class="form-text">
                <a href="?page=password">Passwort vergessen?</a>
            </div>
        </div>

        <input type="hidden" name="login" value="yes">

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Login</button>
        </div>
    </form>

    <p class="text-body-secondary mt-3 mb-0"><small>
        Die Anmeldung erfolgt über eine sichere Session (kein Passwort im Cookie).
    </small></p>
</div>
<?php } else { ?>

<p class="mb-2">Sie sind bereits eingeloggt als <b><?= e($welcome_admin) ?></b>.</p>
<p class="mb-0">
    <a href="?page=home" class="btn btn-primary btn-sm">Zum AdminCenter</a>
    <a href="?page=logout" class="btn btn-outline-danger btn-sm">Logout</a>
</p>

<?php }

pb_admin_card_close();
