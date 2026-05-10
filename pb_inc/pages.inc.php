<?php

/**
 * PowerBook - PHP Guestbook System
 * Pagination Component
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// This file is included from guestbook.inc.php
// Required variables: $tmp_where, $tmp_search, $tmp_pages, $tmp_start, $tmp_page, $count_pages, $config_pages, $config_show_entries, $config_guestbook_name

// Build search parameters for pagination links
$tmp_search_page = '';
$tmp_search_page2 = '';
if (!empty($tmp_where) && !empty($tmp_search)) {
    $tmp_search_page = '&amp;tmp_where=' . urlencode($tmp_where) . '&amp;tmp_search=' . urlencode($tmp_search);
    $tmp_search_page2 = '?tmp_where=' . urlencode($tmp_where) . '&amp;tmp_search=' . urlencode($tmp_search);
}

$guestbookUrl = e($config_guestbook_name);

// Linear pagination (Previous/Next)
if (($config_pages ?? 'N') === 'L') {
    if ($tmp_pages > 1) {
        echo '<nav aria-label="Eintragsnavigation" class="my-3"><ul class="pagination justify-content-center flex-wrap">';

        // Start link
        if ($tmp_start !== 0) {
            echo '<li class="page-item"><a class="page-link" href="' . $guestbookUrl . $tmp_search_page2 . '">&laquo;&laquo; Anfang</a></li>';
        } else {
            echo '<li class="page-item disabled"><span class="page-link">&laquo;&laquo; Anfang</span></li>';
        }

        // Previous page link
        $last_page = $tmp_start - $config_show_entries;
        if ($last_page >= 0) {
            $last_page_param = ($last_page === 0) ? '' : (string) $last_page;
            echo '<li class="page-item"><a class="page-link" href="' . $guestbookUrl . '?tmp_start=' . $last_page_param . $tmp_search_page . '">&laquo; Vorherige Seite</a></li>';
        } else {
            echo '<li class="page-item disabled"><span class="page-link">&laquo; Vorherige Seite</span></li>';
        }

        // Next page link
        $next_page = $tmp_start + $config_show_entries;
        if ($next_page < $count_pages) {
            echo '<li class="page-item"><a class="page-link" href="' . $guestbookUrl . '?tmp_start=' . $next_page . $tmp_search_page . '">Nächste Seite &raquo;</a></li>';
        } else {
            echo '<li class="page-item disabled"><span class="page-link">Nächste Seite &raquo;</span></li>';
        }

        // End link
        $end_page = ($tmp_pages * $config_show_entries) - $config_show_entries;
        $check_last = $tmp_start + $config_show_entries;
        if ($check_last >= $count_pages) {
            echo '<li class="page-item disabled"><span class="page-link">Ende &raquo;&raquo;</span></li>';
        } else {
            echo '<li class="page-item"><a class="page-link" href="' . $guestbookUrl . '?tmp_start=' . $end_page . $tmp_search_page . '">Ende &raquo;&raquo;</a></li>';
        }

        echo '</ul></nav>';
    }

    // Direct page numbers pagination
} elseif (($config_pages ?? 'N') === 'D') {
    if ($tmp_pages > 1) {
        echo '<nav aria-label="Seitennummern" class="my-3"><ul class="pagination justify-content-center flex-wrap">';

        for ($i = 1; $i <= $tmp_pages; $i++) {
            if ($i === $tmp_page) {
                echo '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
            } else {
                $page_start = ($i * $config_show_entries) - $config_show_entries;
                echo '<li class="page-item"><a class="page-link" href="' . $guestbookUrl . '?tmp_start=' . $page_start . '&amp;tmp_page=' . $i . $tmp_search_page . '">' . $i . '</a></li>';
            }
        }

        echo '</ul></nav>';
    }
}
