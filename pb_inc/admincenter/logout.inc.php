<?php
/**
 * PowerBook - PHP Guestbook System
 * AdminCenter: Abmelden
 *
 * Abgemeldet wird nur per Formular (POST mit Token, siehe index.php). Diese
 * Seite erscheint, wenn jemand ?page=logout direkt aufruft oder das Token
 * abgelaufen war, und bietet den Knopf zum Abmelden an.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

// Variables from parent scope (index.php)
/** @var array<string, mixed> $admin_session */
$logoutName = trim((string) (($admin_session ?? [])['name'] ?? ''));

pb_admin_card_open('Abmelden', 'pbLogoutCard');
?>
<p>Möchten Sie sich abmelden<?= $logoutName !== '' ? ', ' . e($logoutName) : '' ?>?</p>
<div class="d-flex flex-wrap gap-2">
    <form action="?page=logout" method="post" class="d-inline">
        <?= csrfField() ?>
        <button type="submit" id="pbLogoutConfirm" class="btn btn-primary">Abmelden</button>
    </form>
    <a class="btn btn-outline-secondary" href="?page=home">Abbrechen</a>
</div>
<?php
pb_admin_card_close();
