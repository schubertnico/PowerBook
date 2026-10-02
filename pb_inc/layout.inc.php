<?php

/**
 * PowerBook - PHP Guestbook System
 * Bootstrap-5-Rahmen für den öffentlichen Bereich
 *
 * Kopf, Fuß und Meldungen für alle öffentlichen Seiten (pbook.php und die
 * Seiten der Einrichtung).
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

/** Bootstrap vom CDN mit Prüfsumme (Subresource Integrity). */
const PB_BOOTSTRAP_CSS = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
const PB_BOOTSTRAP_CSS_SRI = 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH';
const PB_BOOTSTRAP_JS = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
const PB_BOOTSTRAP_JS_SRI = 'sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz';

if (!function_exists('pb_asset_url')) {
    /**
     * Adresse einer Datei aus assets/ mit Versionsparameter gegen alte Browser-Caches.
     */
    function pb_asset_url(string $file): string
    {
        $path = __DIR__ . '/../assets/' . $file;
        $version = is_file($path) ? (int) filemtime($path) : 1;

        return 'assets/' . $file . '?v=' . $version;
    }
}

if (!function_exists('pb_layout_header')) {
    /**
     * Seitenkopf: Doctype, head, Kopfleiste, Beginn des Inhalts.
     *
     * @param string $title Seitentitel (<title>, wird maskiert)
     * @param array<string, mixed> $opts Optionen:
     *                                   - bool   showNav      : Kopfleiste anzeigen (Vorgabe true)
     *                                   - string adminLink    : Adresse des AdminCenters, '' blendet den Link aus
     *                                   - string siteName     : Name in der Kopfleiste (Vorgabe „PowerBook“)
     *                                   - string homeLink     : Ziel des Namens (Vorgabe „#top-of-page“)
     *                                   - bool   brandHeading : Name als <h1 id="pbTitle"> ausgeben
     */
    function pb_layout_header(string $title, array $opts = []): void
    {
        $showNav = (bool) ($opts['showNav'] ?? true);
        $adminLink = (string) ($opts['adminLink'] ?? 'pb_inc/admincenter/');
        $siteName = (string) ($opts['siteName'] ?? 'PowerBook');
        $homeLink = (string) ($opts['homeLink'] ?? '#top-of-page');
        $brandHeading = (bool) ($opts['brandHeading'] ?? false);

        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<!DOCTYPE html>' . "\n";
        echo '<html lang="de">' . "\n";
        echo '<head>' . "\n";
        echo '    <meta charset="UTF-8">' . "\n";
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        echo '    <title>' . $esc($title) . '</title>' . "\n";
        echo '    <link href="' . PB_BOOTSTRAP_CSS . '" rel="stylesheet" integrity="' . PB_BOOTSTRAP_CSS_SRI . '" crossorigin="anonymous">' . "\n";
        echo '    <link href="' . $esc(pb_asset_url('powerbook.css')) . '" rel="stylesheet">' . "\n";
        echo '</head>' . "\n";
        echo '<body class="pb-body bg-body-tertiary">' . "\n";
        echo '<a id="top-of-page"></a>' . "\n";

        if ($showNav) {
            echo '<nav id="pbNav" class="navbar bg-primary navbar-dark mb-4 shadow-sm" aria-label="Kopfzeile">' . "\n";
            echo '    <div class="container flex-nowrap gap-3">' . "\n";
            if ($brandHeading) {
                echo '        <h1 id="pbTitle" class="navbar-brand pb-title mb-0"><a class="text-reset text-decoration-none" href="' . $esc($homeLink) . '">' . $esc($siteName) . '</a></h1>' . "\n";
            } else {
                echo '        <a id="pbBrand" class="navbar-brand pb-title" href="' . $esc($homeLink) . '">' . $esc($siteName) . '</a>' . "\n";
            }
            if ($adminLink !== '') {
                echo '        <a id="pbNavAdmin" class="nav-link text-white text-nowrap pb-nav-admin" href="' . $esc($adminLink) . '">AdminCenter</a>' . "\n";
            }
            echo '    </div>' . "\n";
            echo '</nav>' . "\n";
        }

        echo '<main class="container pb-public">' . "\n";
    }
}

if (!function_exists('pb_layout_footer')) {
    /**
     * Seitenfuß: Ende des Inhalts, Fußzeile, Skripte.
     */
    function pb_layout_footer(): void
    {
        echo '</main>' . "\n";
        echo '<footer id="pbFooter" class="container py-4 mt-4 text-center text-body-secondary border-top">' . "\n";
        // Bewusst ohne Versionsnummer und ohne persönliche Adressen.
        echo '    <small>Erstellt mit <a href="https://www.powerscripts.org" target="_blank" rel="noopener noreferrer">PowerBook</a> von powerscripts.org</small>' . "\n";
        echo '</footer>' . "\n";
        echo '<script src="' . PB_BOOTSTRAP_JS . '" integrity="' . PB_BOOTSTRAP_JS_SRI . '" crossorigin="anonymous"></script>' . "\n";
        echo '<script src="' . htmlspecialchars(pb_asset_url('powerbook.js'), ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
        echo '</body>' . "\n";
        echo '</html>' . "\n";
    }
}

if (!function_exists('pb_alert')) {
    /**
     * Bootstrap-Meldung.
     *
     * $message darf HTML enthalten; Eingaben von Besuchern muss der Aufrufer
     * vorher maskieren.
     *
     * @param string $message Maskiertes bzw. vertrauenswürdiges HTML
     * @param string $type    success, danger, warning, info, primary, secondary, dark, light
     * @param string $id      optionale ID des Elements
     */
    function pb_alert(string $message, string $type = 'info', string $id = ''): string
    {
        $allowed = ['success', 'danger', 'warning', 'info', 'primary', 'secondary', 'dark', 'light'];
        if (!in_array($type, $allowed, true)) {
            $type = 'info';
        }
        $role = in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status';
        $idAttribute = $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '';

        return '<div' . $idAttribute . ' class="alert alert-' . $type . '" role="' . $role . '">' . $message . '</div>';
    }
}
