<?php

/**
 * PowerBook - PHP Guestbook System
 * Bootstrap 5 Layout Helper (öffentlicher Bereich)
 *
 * Stellt wiederverwendbare Header/Footer/Alert-Funktionen bereit, damit alle
 * öffentlichen Ausgabestellen (pbook.php, install_deu.php) das gleiche
 * Bootstrap-5-Skelett nutzen.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

if (!function_exists('pb_layout_header')) {
    /**
     * Render the public Bootstrap 5 page header (doctype, head, navbar, container open).
     *
     * @param string $title    Page title (will be html-escaped)
     * @param array<string, mixed> $opts Optional flags:
     *                                   - bool   showNav   : navbar einblenden (default true)
     *                                   - string adminLink : URL für Admin-Link (default 'pb_inc/admincenter/')
     *                                   - string siteName  : Marke in der Navbar (default 'PowerBook')
     */
    function pb_layout_header(string $title, array $opts = []): void
    {
        $showNav = $opts['showNav'] ?? true;
        $adminLink = $opts['adminLink'] ?? 'pb_inc/admincenter/';
        $siteName = $opts['siteName'] ?? 'PowerBook';

        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $adminLinkEsc = htmlspecialchars((string) $adminLink, ENT_QUOTES, 'UTF-8');
        $siteNameEsc = htmlspecialchars((string) $siteName, ENT_QUOTES, 'UTF-8');

        echo '<!DOCTYPE html>' . "\n";
        echo '<html lang="de">' . "\n";
        echo '<head>' . "\n";
        echo '    <meta charset="UTF-8">' . "\n";
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        echo '    <title>' . $titleEsc . '</title>' . "\n";
        echo '    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">' . "\n";
        // Versions-Parameter aus filemtime() — schiebt den Browser-Cache nach jedem
        // CSS-Edit zuverlaessig zur Seite, ohne harte Cache-Header anpassen zu muessen.
        $cssPath = __DIR__ . '/../assets/powerbook.css';
        $cssVersion = file_exists($cssPath) ? (int) filemtime($cssPath) : 1;
        echo '    <link href="assets/powerbook.css?v=' . $cssVersion . '" rel="stylesheet">' . "\n";
        echo '</head>' . "\n";
        echo '<body class="pb-body bg-body-tertiary">' . "\n";

        if ($showNav) {
            echo '<nav class="navbar navbar-expand-md bg-primary navbar-dark mb-4 shadow-sm">' . "\n";
            echo '    <div class="container">' . "\n";
            echo '        <a class="navbar-brand" href="#top-of-page">' . $siteNameEsc . '</a>' . "\n";
            echo '        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pbNavbar" aria-controls="pbNavbar" aria-expanded="false" aria-label="Navigation umschalten">' . "\n";
            echo '            <span class="navbar-toggler-icon"></span>' . "\n";
            echo '        </button>' . "\n";
            echo '        <div class="collapse navbar-collapse" id="pbNavbar">' . "\n";
            echo '            <ul class="navbar-nav ms-auto mb-2 mb-md-0 gap-md-2">' . "\n";
            echo '                <li class="nav-item"><a class="nav-link" href="' . $adminLinkEsc . '">Adminbereich</a></li>' . "\n";
            echo '            </ul>' . "\n";
            echo '        </div>' . "\n";
            echo '    </div>' . "\n";
            echo '</nav>' . "\n";
        }

        echo '<a id="top-of-page"></a>' . "\n";
        echo '<main class="container pb-public">' . "\n";
    }
}

if (!function_exists('pb_layout_footer')) {
    /**
     * Render the public Bootstrap 5 page footer (closing container, footer, JS bundle).
     */
    function pb_layout_footer(): void
    {
        echo '</main>' . "\n";
        echo '<footer class="container py-4 mt-4 text-center text-body-secondary border-top">' . "\n";
        echo '    <small>' . "\n";
        // Footer-Hinweis bewusst minimal: kein Hinweis auf konkrete PHP-Version
        // (Information-Disclosure-Schutz), kein interner Repo-Link, keine
        // persoenlichen Mail-Adressen.
        echo '        <a href="https://www.powerscripts.org" target="_blank" rel="noopener noreferrer">PowerBook</a> &middot; powered by powerscripts.org' . "\n";
        echo '    </small>' . "\n";
        echo '</footer>' . "\n";
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>' . "\n";
        echo '</body>' . "\n";
        echo '</html>' . "\n";
    }
}

if (!function_exists('pb_alert')) {
    /**
     * Render a Bootstrap 5 alert.
     *
     * Wichtig: $message darf bewusst HTML enthalten (z. B. <b>…</b> aus dem Bestand).
     * Wenn der Aufrufer rohen User-Input ausgibt, MUSS er ihn vorher escapen.
     *
     * @param string $message Bereits escaptes/vertrauenswuerdiges HTML-Markup
     * @param string $type    Bootstrap-Variant (success, danger, warning, info, primary, secondary)
     */
    function pb_alert(string $message, string $type = 'info'): string
    {
        $allowed = ['success', 'danger', 'warning', 'info', 'primary', 'secondary', 'dark', 'light'];
        if (!in_array($type, $allowed, true)) {
            $type = 'info';
        }

        return '<div class="alert alert-' . $type . '" role="alert">' . $message . '</div>';
    }
}
