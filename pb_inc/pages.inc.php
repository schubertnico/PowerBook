<?php

/**
 * PowerBook - PHP Guestbook System
 * Blätterleiste
 *
 * Wird von guestbook.inc.php über und unter der Liste eingebunden.
 * Erwartet: $count_pages (Anzahl Einträge), $tmp_start (erster Eintrag der
 * Seite), $config_show_entries, $config_pages ('L' = Vor/Zurück, sonst
 * Seitenzahlen), $config_guestbook_name, optional $tmp_where/$tmp_search
 * (laufende Suche) und $pbPagerPosition ('top' oder 'bottom').
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.inc.php';

if (!function_exists('pb_render_pager')) {
    /**
     * HTML der Blätterleiste ('' bei nur einer Seite).
     *
     * Beide Leisten tragen Klassen (.pb-pager-first, -prev, -next, -last,
     * -page mit data-page); IDs (#pbPagerFirst … #pbPagerLast) nur die untere,
     * damit jede ID eindeutig bleibt.
     *
     * @param array<string, string> $search z. B. ['tmp_where' => 'text', 'tmp_search' => 'Möwe']
     */
    function pb_render_pager(int $total, int $start, int $perPage, string $mode, string $baseUrl, array $search = [], string $position = 'top'): string
    {
        $perPage = max(1, $perPage);
        $pages = (int) ceil($total / $perPage);
        if ($pages <= 1) {
            return '';
        }
        $bottom = $position === 'bottom';
        $pager = [
            'current' => min($pages, max(1, intdiv(max(0, $start), $perPage) + 1)),
            'pages' => $pages,
            'perPage' => $perPage,
            'baseUrl' => $baseUrl,
            'search' => $search,
            'bottom' => $bottom,
        ];

        return '<nav id="' . ($bottom ? 'pbPagerBottom' : 'pbPagerTop') . '" class="pb-pager my-3" aria-label="' . ($bottom ? 'Seiten (unten)' : 'Seiten (oben)') . '">'
            . '<ul class="pagination justify-content-center flex-wrap mb-0">'
            . ($mode === 'L' ? pb_pager_steps($pager) : pb_pager_numbers($pager))
            . '</ul></nav>';
    }
}

if (!function_exists('pb_pager_item')) {
    /**
     * Ein Punkt der Blätterleiste: Link, aktuelle Seite oder gesperrt.
     *
     * @param array{current: int, pages: int, perPage: int, baseUrl: string, search: array<string, string>, bottom: bool} $pager
     * @param int|null $page Zielseite, null = gesperrt
     * @param string   $id   ID, nur in der unteren Leiste gesetzt
     */
    function pb_pager_item(array $pager, string $label, ?int $page, string $class, string $id = '', string $aria = '', bool $active = false): string
    {
        $attributes = ' class="page-link ' . $class . '"';
        if ($pager['bottom'] && $id !== '') {
            $attributes .= ' id="' . $id . '"';
        }
        if ($aria !== '') {
            $attributes .= ' aria-label="' . pb_h($aria) . '"';
        }
        if ($page !== null && !$active) {
            $url = pb_url_with($pager['baseUrl'], ['tmp_start' => ($page - 1) * $pager['perPage']] + $pager['search']);

            return '<li class="page-item"><a' . $attributes . ' data-page="' . $page . '" href="' . pb_h($url) . '">' . $label . '</a></li>';
        }
        if ($active) {
            return '<li class="page-item active" aria-current="page"><span' . $attributes . ' data-page="' . $page . '">' . $label . '</span></li>';
        }

        return '<li class="page-item disabled"><span' . $attributes . ' aria-disabled="true">' . $label . '</span></li>';
    }
}

if (!function_exists('pb_pager_steps')) {
    /**
     * Blätterleiste „Vor/Zurück“ ($config_pages = 'L'): Anfang, Vorherige
     * Seite, „Seite x von y“, Nächste Seite, Ende.
     *
     * @param array{current: int, pages: int, perPage: int, baseUrl: string, search: array<string, string>, bottom: bool} $pager
     */
    function pb_pager_steps(array $pager): string
    {
        $current = $pager['current'];
        $pages = $pager['pages'];

        return pb_pager_item($pager, '&laquo;<span class="pb-pager-label"> Anfang</span>', $current > 1 ? 1 : null, 'pb-pager-first', 'pbPagerFirst', 'Erste Seite')
            . pb_pager_item($pager, '&lsaquo;<span class="pb-pager-label"> Vorherige Seite</span>', $current > 1 ? $current - 1 : null, 'pb-pager-prev', 'pbPagerPrev', 'Vorherige Seite')
            . '<li class="page-item disabled"><span class="page-link pb-pager-info">Seite ' . $current . ' von ' . $pages . '</span></li>'
            . pb_pager_item($pager, '<span class="pb-pager-label">Nächste Seite </span>&rsaquo;', $current < $pages ? $current + 1 : null, 'pb-pager-next', 'pbPagerNext', 'Nächste Seite')
            . pb_pager_item($pager, '<span class="pb-pager-label">Ende </span>&raquo;', $current < $pages ? $pages : null, 'pb-pager-last', 'pbPagerLast', 'Letzte Seite');
    }
}

if (!function_exists('pb_pager_numbers')) {
    /**
     * Blätterleiste mit Seitenzahlen; eine einzelne ausgelassene Seite wird
     * gezeigt, mehrere werden zu „…“.
     *
     * @param array{current: int, pages: int, perPage: int, baseUrl: string, search: array<string, string>, bottom: bool} $pager
     */
    function pb_pager_numbers(array $pager): string
    {
        $current = $pager['current'];
        $pages = $pager['pages'];

        $html = pb_pager_item($pager, '&lsaquo;', $current > 1 ? $current - 1 : null, 'pb-pager-prev', 'pbPagerPrev', 'Vorherige Seite');
        $previous = 0;
        foreach (pb_pager_page_numbers($current, $pages) as $n) {
            if ($n - $previous === 2) {
                $html .= pb_pager_item($pager, (string) ($n - 1), $n - 1, 'pb-pager-page', '', 'Seite ' . ($n - 1));
            } elseif ($n - $previous > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link pb-pager-gap" aria-hidden="true">…</span></li>';
            }
            $html .= pb_pager_page($pager, $n);
            $previous = $n;
        }

        return $html . pb_pager_item($pager, '&rsaquo;', $current < $pages ? $current + 1 : null, 'pb-pager-next', 'pbPagerNext', 'Nächste Seite');
    }
}

if (!function_exists('pb_pager_page_numbers')) {
    /**
     * Seitenzahlen der Leiste; ab 10 Seiten mit Auslassung (1 … 4 5 6 … 12).
     *
     * @return list<int>
     */
    function pb_pager_page_numbers(int $current, int $pages): array
    {
        if ($pages <= 9) {
            return range(1, $pages);
        }
        $numbers = array_values(array_unique(array_filter(
            [1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $pages],
            static fn (int $n): bool => $n >= 1 && $n <= $pages
        )));
        sort($numbers);

        return $numbers;
    }
}

if (!function_exists('pb_pager_page')) {
    /**
     * Eine Seitenzahl der Leiste; die erste und die letzte Seite tragen
     * zusätzlich .pb-pager-first bzw. .pb-pager-last und deren ID.
     *
     * @param array{current: int, pages: int, perPage: int, baseUrl: string, search: array<string, string>, bottom: bool} $pager
     */
    function pb_pager_page(array $pager, int $page): string
    {
        $isFirst = $page === 1;
        $isLast = $page === $pager['pages'];
        $class = 'pb-pager-page' . ($isFirst ? ' pb-pager-first' : '') . ($isLast ? ' pb-pager-last' : '');
        $id = $isFirst ? 'pbPagerFirst' : ($isLast ? 'pbPagerLast' : '');

        return pb_pager_item($pager, (string) $page, $page, $class, $id, 'Seite ' . $page, $page === $pager['current']);
    }
}

$pbPagerSearch = [];
if (($tmp_where ?? '') !== '' && ($tmp_search ?? '') !== '') {
    $pbPagerSearch = ['tmp_where' => (string) $tmp_where, 'tmp_search' => (string) $tmp_search];
}

echo pb_render_pager(
    (int) ($count_pages ?? 0),
    (int) ($tmp_start ?? 0),
    (int) ($config_show_entries ?? 10),
    (string) ($config_pages ?? 'D'),
    (string) ($config_guestbook_name ?? 'pbook.php'),
    $pbPagerSearch,
    (string) ($pbPagerPosition ?? 'top')
);
