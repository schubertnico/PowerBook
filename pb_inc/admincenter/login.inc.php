<?php
/**
 * PowerBook - PHP Guestbook System
 * AdminCenter: Anmelden
 *
 * Das Formular schickt an index.php (?page=login). Dort laufen Prüfung,
 * Anmeldedrossel und die Weiterleitung zur Startseite; Meldungen kommen als
 * #pbMessage aus dem Layout.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

// Variables from parent scope (index.php)
/** @var string $loginName */
/** @var bool $loggedIn */
pb_admin_card_open('Anmelden', 'pbLogin');

if (!empty($loggedIn)) { ?>
<p class="mb-0">Sie sind bereits angemeldet. <a href="?page=home">Zur Startseite</a></p>
<?php } else { ?>
<div class="pb-card-narrow">
    <p>Bitte melden Sie sich an, um Ihr Gästebuch zu verwalten.</p>

    <form id="pbLoginForm" action="?page=login" method="post" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="login" value="yes">

        <div class="mb-3">
            <label for="pb_login_name" class="form-label">Name oder E-Mail-Adresse</label>
            <input id="pb_login_name" type="text" class="form-control" name="name" required value="<?= e($loginName ?? '') ?>" autocomplete="username" autocapitalize="none" spellcheck="false">
        </div>

        <div class="mb-3">
            <label for="pb_login_password" class="form-label">Passwort</label>
            <input id="pb_login_password" type="password" class="form-control" name="password" required autocomplete="current-password">
            <div class="form-text">
                <a id="pbForgotLink" href="?page=password">Passwort vergessen?</a>
            </div>
        </div>

        <button type="submit" id="pbLoginSubmit" class="btn btn-primary">Anmelden</button>
    </form>

    <p class="text-body-secondary mt-3 mb-0"><small>
        Nach 60 Minuten ohne Aktivität werden Sie aus Sicherheitsgründen automatisch abgemeldet.
    </small></p>
</div>
<?php }

pb_admin_card_close();
