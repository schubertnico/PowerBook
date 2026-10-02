<?php

/**
 * PowerBook - PHP Guestbook System
 * Blätterleiste der Eintragsliste im AdminCenter
 *
 * Erwartet $count_pages (Anzahl aller Einträge), $tmp_start (erster Eintrag
 * der Seite), $perPage (Einträge je Seite, Vorgabe 15) und optional
 * $pagerId (ID der Leiste, z. B. pbEntriesPagerTop).
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// Variables from parent scope
/** @var int $count_pages */
/** @var int $tmp_start */
$count_pages ??= 0;
$tmp_start ??= 0;
$perPage ??= 15;
$pagerId ??= '';
$perPage = max(1, (int) $perPage);
$tmp_pages = (int) ceil($count_pages / $perPage);

if ($tmp_pages > 1) {
    $currentPage = intdiv(max(0, (int) $tmp_start), $perPage) + 1;
    $pageLink = static fn (int $number): string => '?page=entries' . ($number > 1 ? '&amp;tmp_start=' . (($number - 1) * $perPage) : '');
    $pageItem = static function (string $label, ?int $target, bool $active = false) use ($pageLink): string {
        if ($target === null) {
            return '<li class="page-item disabled"><span class="page-link">' . $label . '</span></li>';
        }
        if ($active) {
            return '<li class="page-item active" aria-current="page"><span class="page-link">' . $label . '</span></li>';
        }

        return '<li class="page-item"><a class="page-link" href="' . $pageLink($target) . '">' . $label . '</a></li>';
    };

    echo '<nav' . ($pagerId !== '' ? ' id="' . e($pagerId) . '"' : '') . ' aria-label="Seiten der Eintragsliste" class="my-3">';
    echo '<ul class="pagination justify-content-center flex-wrap">';
    echo $pageItem('&laquo; Anfang', $currentPage > 1 ? 1 : null);
    echo $pageItem('&lsaquo; Vorherige Seite', $currentPage > 1 ? $currentPage - 1 : null);
    for ($number = 1; $number <= $tmp_pages; $number++) {
        echo $pageItem((string) $number, $number, $number === $currentPage);
    }
    echo $pageItem('Nächste Seite &rsaquo;', $currentPage < $tmp_pages ? $currentPage + 1 : null);
    echo $pageItem('Ende &raquo;', $currentPage < $tmp_pages ? $tmp_pages : null);
    echo '</ul></nav>';
}
