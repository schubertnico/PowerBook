<?php

/**
 * PowerBook - PHP Guestbook System
 * Bootstrap 5 Layout Helper (Adminbereich)
 *
 * Stellt wiederverwendbare Header-/Footer-Funktionen sowie Helper für
 * Admin-Cards und Alerts bereit. Genutzt von admincenter/index.php und
 * Subseiten über pb_admin_card_open()/pb_admin_card_close().
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

if (!function_exists('pb_admin_layout_header')) {
    /**
     * Render the admin Bootstrap 5 page header.
     *
     * @param string $title          Page title (escaped)
     * @param array<string, mixed> $opts Optional flags:
     *   - string  $headMessage     Bereits formatiertes HTML für den Login-Status (z. B. "Hallo, Foo")
     *   - int     $publicCount     Anzahl öffentlicher Einträge
     *   - int     $hiddenCount     Anzahl versteckter Einträge
     *   - bool    $showCounts      Counts in der Statusleiste anzeigen
     *   - bool    $showSubNav      Subnavigation (Einträge/Admins) anzeigen
     *   - string  $guestbookUrl    URL zum öffentlichen Gästebuch (relativ)
     */
    function pb_admin_layout_header(string $title, array $opts = []): void
    {
        $headMessage = (string) ($opts['headMessage'] ?? '');
        $publicCount = (int) ($opts['publicCount'] ?? 0);
        $hiddenCount = (int) ($opts['hiddenCount'] ?? 0);
        $showCounts = (bool) ($opts['showCounts'] ?? true);
        $showSubNav = (bool) ($opts['showSubNav'] ?? true);
        $guestbookUrl = (string) ($opts['guestbookUrl'] ?? '../../pbook.php');

        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $guestbookUrlEsc = htmlspecialchars($guestbookUrl, ENT_QUOTES, 'UTF-8');

        echo '<!DOCTYPE html>' . "\n";
        echo '<html lang="de">' . "\n";
        echo '<head>' . "\n";
        echo '    <meta charset="UTF-8">' . "\n";
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        echo '    <title>' . $titleEsc . '</title>' . "\n";
        echo '    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">' . "\n";
        // Versions-Parameter aus filemtime() — schiebt den Browser-Cache nach jedem
        // CSS-Edit zuverlaessig zur Seite, ohne harte Cache-Header anpassen zu muessen.
        $cssPath = __DIR__ . '/../../assets/powerbook.css';
        $cssVersion = file_exists($cssPath) ? (int) filemtime($cssPath) : 1;
        echo '    <link href="../../assets/powerbook.css?v=' . $cssVersion . '" rel="stylesheet">' . "\n";
        echo '</head>' . "\n";
        echo '<body class="pb-body bg-body-tertiary">' . "\n";

        // Top-Navbar
        echo '<nav class="navbar navbar-expand-md bg-dark navbar-dark mb-4 shadow-sm">' . "\n";
        echo '    <div class="container">' . "\n";
        echo '        <a class="navbar-brand pb-admin-brand fw-bold" href="?page=home">PowerBook AdminCenter</a>' . "\n";
        echo '        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pbAdminNav" aria-controls="pbAdminNav" aria-expanded="false" aria-label="Navigation umschalten">' . "\n";
        echo '            <span class="navbar-toggler-icon"></span>' . "\n";
        echo '        </button>' . "\n";
        echo '        <div class="collapse navbar-collapse" id="pbAdminNav">' . "\n";
        echo '            <ul class="navbar-nav me-auto mb-2 mb-md-0">' . "\n";
        echo '                <li class="nav-item"><a class="nav-link" href="?page=home">Start</a></li>' . "\n";
        if ($showSubNav) {
            echo '                <li class="nav-item"><a class="nav-link" href="?page=entries">Einträge</a></li>' . "\n";
            echo '                <li class="nav-item"><a class="nav-link" href="?page=release">Freischaltung</a></li>' . "\n";
            echo '                <li class="nav-item"><a class="nav-link" href="?page=admins">Admins</a></li>' . "\n";
            echo '                <li class="nav-item"><a class="nav-link" href="?page=configuration">Konfiguration</a></li>' . "\n";
        }
        echo '                <li class="nav-item"><a class="nav-link" href="?page=license">Lizenz</a></li>' . "\n";
        echo '                <li class="nav-item"><a class="nav-link" href="' . $guestbookUrlEsc . '" target="_blank" rel="noopener noreferrer">Externe Seite &rarr;</a></li>' . "\n";
        echo '            </ul>' . "\n";
        echo '            <ul class="navbar-nav ms-auto mb-2 mb-md-0">' . "\n";
        if ($showCounts) {
            echo '                <li class="nav-item"><span class="nav-link disabled" aria-disabled="true">';
            echo 'Öffentlich <span class="badge bg-success">' . $publicCount . '</span>';
            echo ' &nbsp;Versteckt <span class="badge bg-warning text-dark">' . $hiddenCount . '</span>';
            echo '</span></li>' . "\n";
        }
        echo '            </ul>' . "\n";
        echo '        </div>' . "\n";
        echo '    </div>' . "\n";
        echo '</nav>' . "\n";

        // Status / Login-Hinweis
        echo '<main class="container pb-admin">' . "\n";
        if ($headMessage !== '') {
            echo '    <div class="alert alert-secondary py-2 mb-4" role="status">' . $headMessage . '</div>' . "\n";
        }
    }
}

if (!function_exists('pb_admin_layout_footer')) {
    /**
     * Render the admin Bootstrap 5 page footer.
     */
    function pb_admin_layout_footer(): void
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

if (!function_exists('pb_admin_card_open')) {
    /**
     * Open a Bootstrap 5 card with a header for an admin sub page.
     *
     * Subseiten (home.inc.php, login.inc.php, …) rufen das am Anfang ihres
     * Bodys auf, statt die alte <tr><td>-Struktur zu nutzen.
     *
     * @param string $title  Header-Text (escaped)
     */
    function pb_admin_card_open(string $title): void
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        echo '<section class="card shadow-sm mb-4">' . "\n";
        echo '    <header class="card-header bg-primary text-white">' . "\n";
        echo '        <h2 class="h5 mb-0">' . $titleEsc . '</h2>' . "\n";
        echo '    </header>' . "\n";
        echo '    <div class="card-body">' . "\n";
    }
}

if (!function_exists('pb_admin_card_close')) {
    /**
     * Close the Bootstrap 5 card opened by pb_admin_card_open().
     */
    function pb_admin_card_close(): void
    {
        echo '    </div>' . "\n";
        echo '</section>' . "\n";
    }
}

if (!function_exists('pb_admin_alert')) {
    /**
     * Render a Bootstrap 5 alert in the admin context.
     *
     * @param string $message Vertrauenswuerdiges/escaptes HTML-Markup
     * @param string $type    Bootstrap-Variant (success, danger, warning, info)
     */
    function pb_admin_alert(string $message, string $type = 'info'): string
    {
        $allowed = ['success', 'danger', 'warning', 'info', 'primary', 'secondary', 'dark', 'light'];
        if (!in_array($type, $allowed, true)) {
            $type = 'info';
        }

        return '<div class="alert alert-' . $type . '" role="alert">' . $message . '</div>';
    }
}

if (!function_exists('pb_admin_message_type')) {
    /**
     * Map internal messageType strings to Bootstrap alert variants.
     */
    function pb_admin_message_type(string $messageType): string
    {
        return match ($messageType) {
            'success' => 'success',
            'error'   => 'danger',
            'warning' => 'warning',
            'info'    => 'info',
            default   => 'info',
        };
    }
}
