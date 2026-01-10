<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Pagination
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// Variables from parent scope
/** @var int $tmp_pages */
/** @var int $tmp_start */
/** @var int $count_pages */

$tmp_pages = $tmp_pages ?? 0;
$tmp_start = $tmp_start ?? 0;
$count_pages = $count_pages ?? 0;
$tmp_search_page = $tmp_search_page ?? '';

if ($tmp_pages > 1) {
    echo '<table border="0" width="100%"><tr>';

    // Beginning link
    if ($tmp_start != 0) {
        echo '<td width="10%" align="left"><small><a href="?page=entries">&laquo;&laquo; Beginn</a></small></td>';
    } else {
        echo '<td width="10%" align="left"><small>&laquo;&laquo; Beginn</small></td>';
    }

    // Previous page link
    $last_page = $tmp_start - 15;
    if ($last_page >= 0) {
        $last_page_param = ($last_page === 0) ? '' : '&tmp_start=' . $last_page;
        echo '<td width="40%" align="right"><small><a href="?page=entries' . e($last_page_param) . '">&laquo; Vorherige Seite</a> &nbsp;&nbsp;</small></td>';
    } else {
        echo '<td width="40%" align="right"><small>&laquo; Vorherige Seite &nbsp;&nbsp;</small></td>';
    }

    // Next page link
    $next_page = $tmp_start + 15;
    if ($next_page < $count_pages) {
        echo '<td width="40%" align="left"><small>&nbsp;&nbsp;<a href="?page=entries&tmp_start=' . $next_page . '">Nächste Seite &raquo;</a></small></td>';
    } else {
        echo '<td width="40%" align="left"><small>&nbsp;&nbsp; Nächste Seite &raquo;</small></td>';
    }

    // End link
    $end_page = ($tmp_pages * 15) - 15;
    $check_last = $tmp_start + 15;
    if ($check_last >= $count_pages) {
        echo '<td width="10%" align="right"><small>Ende &raquo;&raquo;</small></td>';
    } else {
        echo '<td width="10%" align="right"><small><a href="?page=entries&tmp_start=' . $end_page . e($tmp_search_page) . '">Ende &raquo;&raquo;</a></small></td>';
    }

    echo '</tr></table>';
}
