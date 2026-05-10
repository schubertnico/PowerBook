<?php

/**
 * PowerBook - PHP Guestbook System
 * Admin Pagination
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// Variables from parent scope
/** @var int $tmp_pages */
/** @var int $tmp_start */
/** @var int $count_pages */
$tmp_pages ??= 0;
$tmp_start ??= 0;
$count_pages ??= 0;
$tmp_search_page ??= '';

if ($tmp_pages > 1) {
    echo '<nav aria-label="Eintragsseiten" class="my-3"><ul class="pagination justify-content-center flex-wrap">';

    // Beginning link
    if ($tmp_start !== 0) {
        echo '<li class="page-item"><a class="page-link" href="?page=entries">&laquo;&laquo; Beginn</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">&laquo;&laquo; Beginn</span></li>';
    }

    // Previous page link
    $last_page = $tmp_start - 15;
    if ($last_page >= 0) {
        $last_page_param = ($last_page === 0) ? '' : '&tmp_start=' . $last_page;
        echo '<li class="page-item"><a class="page-link" href="?page=entries' . e($last_page_param) . '">&laquo; Vorherige Seite</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">&laquo; Vorherige Seite</span></li>';
    }

    // Next page link
    $next_page = $tmp_start + 15;
    if ($next_page < $count_pages) {
        echo '<li class="page-item"><a class="page-link" href="?page=entries&tmp_start=' . $next_page . '">Nächste Seite &raquo;</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">Nächste Seite &raquo;</span></li>';
    }

    // End link
    $end_page = ($tmp_pages * 15) - 15;
    $check_last = $tmp_start + 15;
    if ($check_last >= $count_pages) {
        echo '<li class="page-item disabled"><span class="page-link">Ende &raquo;&raquo;</span></li>';
    } else {
        echo '<li class="page-item"><a class="page-link" href="?page=entries&tmp_start=' . $end_page . e($tmp_search_page) . '">Ende &raquo;&raquo;</a></li>';
    }

    echo '</ul></nav>';
}
