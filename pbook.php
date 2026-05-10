<?php
/**
 * PowerBook - PHP Guestbook System
 * Main Entry Point
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// BUG-013: Entry-Konstante setzen, damit guestbook.inc.php Direktaufruf ablehnen kann.
if (!defined('PB_ENTRY')) {
    define('PB_ENTRY', true);
}

// Include config early for session_start() before any output
require_once __DIR__ . '/pb_inc/config.inc.php';
require_once __DIR__ . '/pb_inc/layout.inc.php';

pb_layout_header('PowerBook Gästebuch', [
    'showNav' => true,
    'adminLink' => 'pb_inc/admincenter/',
    'siteName' => 'PowerBook Gästebuch',
]);
?>

<div class="row g-4">
    <div class="col-12">
        <?php include __DIR__ . '/pb_inc/guestbook.inc.php'; ?>
    </div>
</div>

<?php
pb_layout_footer();
