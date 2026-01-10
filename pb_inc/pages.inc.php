<?php
/**
 * PowerBook - PHP Guestbook System
 * Pagination Component
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
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
        echo '<table border="0" width="100%"><tr>';

        // Start link
        if ($tmp_start != 0) {
            echo '<td width="10%" align="left"><small>';
            echo '<a href="' . $guestbookUrl . $tmp_search_page2 . '">&laquo;&laquo; Anfang</a></small></td>';
        } else {
            echo '<td width="10%" align="left"><small>&laquo;&laquo; Anfang</small></td>';
        }

        // Previous page link
        $last_page = $tmp_start - $config_show_entries;
        if ($last_page >= 0) {
            $last_page_param = ($last_page === 0) ? '' : (string)$last_page;
            echo '<td width="40%" align="right"><small>';
            echo '<a href="' . $guestbookUrl . '?tmp_start=' . $last_page_param . $tmp_search_page . '">&laquo; Vorherige Seite</a> &nbsp; &nbsp;</small></td>';
        } else {
            echo '<td width="40%" align="right"><small>&laquo; Vorherige Seite &nbsp; &nbsp;</small></td>';
        }

        // Next page link
        $next_page = $tmp_start + $config_show_entries;
        if ($next_page < $count_pages) {
            echo '<td width="40%" align="left"><small>';
            echo '&nbsp; &nbsp; <a href="' . $guestbookUrl . '?tmp_start=' . $next_page . $tmp_search_page . '">Nächste Seite &raquo;</a></small></td>';
        } else {
            echo '<td width="40%" align="left"><small>&nbsp; &nbsp; Nächste Seite &raquo;</small></td>';
        }

        // End link
        $end_page = ($tmp_pages * $config_show_entries) - $config_show_entries;
        $check_last = $tmp_start + $config_show_entries;
        if ($check_last >= $count_pages) {
            echo '<td width="10%" align="right"><small>Ende &raquo;&raquo;</small></td>';
        } else {
            echo '<td width="10%" align="right"><small>';
            echo '<a href="' . $guestbookUrl . '?tmp_start=' . $end_page . $tmp_search_page . '">Ende &raquo;&raquo;</a></small></td>';
        }

        echo '</tr></table>';
    }

// Direct page numbers pagination
} elseif (($config_pages ?? 'N') === 'D') {
    if ($tmp_pages > 1) {
        echo '<small>Seite';

        for ($i = 1; $i <= $tmp_pages; $i++) {
            if ($i === $tmp_page) {
                echo ' &nbsp; - ' . $i . ' -';
            } else {
                $page_start = ($i * $config_show_entries) - $config_show_entries;
                echo ' &nbsp; <a href="' . $guestbookUrl . '?tmp_start=' . $page_start . '&amp;tmp_page=' . $i . $tmp_search_page . '">' . $i . '</a>';
            }
        }

        echo '</small><br>';
    }
}
