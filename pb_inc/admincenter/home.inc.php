<?php
/**
 * PowerBook - PHP Guestbook System
 * AdminCenter: Startseite
 *
 * Übersicht mit Zählern, Schnellzugriff nach Rechten und den Rechten des
 * angemeldeten Kontos.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

// Variables from parent scope (index.php)
/** @var PDO $pdo */
/** @var string $pb_entries */
/** @var array<string, mixed> $admin_session */
$admin_session ??= [];
$homeCounts = pb_admin_count_entries($pdo, $pb_entries ?? 'pb_entries');
$homeName = trim((string) ($admin_session['name'] ?? ''));
$isSuperadmin = (int) ($admin_session['id'] ?? 0) === 1;

$quickLinks = [
    ['entries', 'pbQuickEntries', 'Einträge bearbeiten und beantworten', pb_admin_can($admin_session, 'entries')],
    ['release', 'pbQuickRelease', 'Einträge freischalten', pb_admin_can($admin_session, 'release')],
    ['admins', 'pbQuickAdmins', 'Admins verwalten', pb_admin_can($admin_session, 'admins')],
    ['configuration', 'pbQuickConfig', 'Konfiguration ändern', pb_admin_can($admin_session, 'config')],
    ['account', 'pbQuickAccount', 'Mein Konto: Name, E-Mail-Adresse, Passwort', true],
];
$rights = [
    'release' => 'Einträge freischalten',
    'entries' => 'Einträge bearbeiten und löschen',
    'config' => 'Konfiguration ändern',
    'admins' => 'Admins verwalten',
];

pb_admin_card_open('Willkommen im AdminCenter', 'pbHome');
?>

<p class="lead mb-4">Hallo <?= e($homeName) ?>, hier verwalten Sie Ihr Gästebuch.</p>

<?php if ($homeCounts['pending'] > 0 && pb_admin_can($admin_session, 'release')) { ?>
<div id="pbHomePending" class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><?= e(pb_admin_plural($homeCounts['pending'], 'Eintrag wartet', 'Einträge warten')) ?> auf Freischaltung.</span>
    <a class="btn btn-warning btn-sm" href="?page=release">Jetzt freischalten</a>
</div>
<?php } ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="h6 card-title">Schnellzugriff</h3>
                <ul class="list-group list-group-flush">
                    <?php foreach ($quickLinks as [$quickPage, $quickId, $quickLabel, $quickVisible]) {
                        if ($quickVisible) { ?>
                    <li class="list-group-item"><a id="<?= $quickId ?>" href="?page=<?= $quickPage ?>"><?= $quickLabel ?></a></li>
                    <?php }
                        } ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="h6 card-title">Übersicht</h3>
                <dl class="row mb-3">
                    <dt class="col-8">Freigeschaltete Einträge</dt>
                    <dd class="col-4"><span id="pbOverviewPublic" class="badge text-bg-success"><?= $homeCounts['public'] ?></span></dd>
                    <dt class="col-8">Wartende Einträge</dt>
                    <dd class="col-4 mb-0"><span id="pbOverviewPending" class="badge text-bg-warning"><?= $homeCounts['pending'] ?></span></dd>
                </dl>
                <h3 class="h6 card-title">Ihre Rechte</h3>
                <?php if ($isSuperadmin) { ?>
                <p id="pbHomeRights" class="mb-0">Superadmin – Sie haben immer alle Rechte.</p>
                <?php } else { ?>
                <ul id="pbHomeRights" class="list-unstyled mb-0">
                    <?php foreach ($rights as $right => $rightLabel) {
                        $has = pb_admin_can($admin_session, $right); ?>
                    <li class="<?= $has ? '' : 'text-body-secondary' ?>"><?= $has ? '&#10003;' : '&ndash;' ?> <?= $rightLabel ?></li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<p class="text-body-secondary mt-4 mb-0"><small>
    Fragen zu PowerBook beantwortet
    <a href="https://www.powerscripts.org" target="_blank" rel="noopener">powerscripts.org</a>.
</small></p>

<?php
pb_admin_card_close();
