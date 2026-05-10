<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Center Home Page
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

pb_admin_card_open('Willkommen');
?>

<p class="lead">Willkommen im AdminCenter von PowerBook!</p>

<p>
    Vielen Dank, dass Sie PowerBook benutzen. Bei Fragen oder Problemen besuchen Sie bitte
    <a href="https://www.powerscripts.org" target="_blank" rel="noopener noreferrer">powerscripts.org</a>.
</p>

<div class="row g-4 mt-2">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="h6 card-title">Schnellnavigation</h3>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><a href="?page=entries">Einträge verwalten</a></li>
                    <li class="list-group-item"><a href="?page=release">Einträge freischalten</a></li>
                    <li class="list-group-item"><a href="?page=admins">Administratoren verwalten</a></li>
                    <li class="list-group-item"><a href="?page=configuration">Konfiguration</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="h6 card-title">Systeminfo</h3>
                <dl class="row mb-0">
                    <?php /*
                     * Aus Sicherheitsgruenden werden konkrete PHP- oder
                     * PowerBook-Versionen NICHT mehr angezeigt — diese sind
                     * Information-Disclosure-Vektoren fuer Angreifer.
                     */ ?>
                    <dt class="col-sm-6">Öffentliche Einträge</dt>
                    <dd class="col-sm-6"><span class="badge bg-success"><?= (int) ($head_count_entries ?? 0) ?></span></dd>

                    <dt class="col-sm-6">Versteckte Einträge</dt>
                    <dd class="col-sm-6"><span class="badge bg-warning text-dark"><?= (int) ($head_count_unreleased ?? 0) ?></span></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<p class="text-body-secondary mt-4 mb-0"><small>
    PowerBook &middot;
    <a href="https://www.powerscripts.org" target="_blank" rel="noopener noreferrer">powerscripts.org</a>
</small></p>

<?php
pb_admin_card_close();
