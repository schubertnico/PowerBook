<?php

/**
 * PowerBook - PHP Guestbook System
 * Rahmen und Hilfsfunktionen des AdminCenters (Bootstrap 5)
 *
 * Kopf mit Menü nach Rechten, Statuszeile, Meldungen (Flash), Fuß sowie
 * Helfer für Karten, Weiterleitungen, Datumsausgabe und Textformatierung.
 * Alle Funktionen sind gegen doppeltes Einbinden geschützt.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/../functions.inc.php';

if (!class_exists('PbAdminRedirect')) {
    /**
     * Wird von pb_admin_redirect() geworfen. index.php fängt sie ab, verwirft
     * die bisherige Ausgabe und leitet weiter (Post/Redirect/Get).
     */
    final class PbAdminRedirect extends RuntimeException
    {
        public function __construct(public readonly string $location)
        {
            parent::__construct('Weiterleitung nach ' . $location);
        }
    }
}

if (!function_exists('pb_admin_redirect')) {
    /**
     * Beendet die Seite und leitet weiter, z. B. nach dem Speichern auf
     * dieselbe Seite (die Meldung vorher mit pb_admin_flash() setzen).
     *
     * @param string $location Relativ wie im Menü, z. B. "?page=release"
     *
     * @throws PbAdminRedirect immer
     */
    function pb_admin_redirect(string $location): never
    {
        throw new PbAdminRedirect($location);
    }
}

if (!function_exists('pb_admin_alert_type')) {
    /**
     * Normalisiert einen Meldungstyp auf eine Bootstrap-Variante.
     */
    function pb_admin_alert_type(string $type): string
    {
        $type = $type === 'error' ? 'danger' : $type;

        return in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'info';
    }
}

if (!function_exists('pb_admin_flash')) {
    /**
     * Merkt eine Meldung vor. Das Layout gibt sie beim nächsten Seitenaufbau
     * einmal als #pbMessage aus – auch noch im selben Aufruf, weil index.php
     * den Kopf erst nach der Seite ausgibt. Es gilt immer die zuletzt gesetzte
     * Meldung.
     *
     * @param string $type success|danger|warning|info
     * @param string $text Klartext, wird bei der Ausgabe escaped
     */
    function pb_admin_flash(string $type, string $text): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION['pb_flash'] = [
            'type' => pb_admin_alert_type($type),
            'text' => $text,
        ];
    }
}

if (!function_exists('pb_admin_flash_take')) {
    /**
     * Holt die vorgemerkte Meldung und löscht sie.
     *
     * @return array{type: string, text: string}|null
     */
    function pb_admin_flash_take(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['pb_flash']) || !is_array($_SESSION['pb_flash'])) {
            return null;
        }

        $flash = $_SESSION['pb_flash'];
        unset($_SESSION['pb_flash']);

        $text = (string) ($flash['text'] ?? '');
        if ($text === '') {
            return null;
        }

        return ['type' => pb_admin_alert_type((string) ($flash['type'] ?? 'info')), 'text' => $text];
    }
}

if (!function_exists('pb_admin_message_html')) {
    /**
     * HTML der Meldung des letzten Vorgangs (#pbMessage).
     *
     * @param string $text Klartext, wird escaped
     */
    function pb_admin_message_html(string $type, string $text): string
    {
        $type = pb_admin_alert_type($type);
        $role = in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status';

        return '<div id="pbMessage" class="alert alert-' . $type . '" role="' . $role . '" data-type="' . $type . '">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

if (!function_exists('pb_admin_csrf_message')) {
    /**
     * Meldung für ein Formular mit ungültigem oder fehlendem Token (Klartext).
     */
    function pb_admin_csrf_message(): string
    {
        if (function_exists('pb_csrf_failed_message')) {
            return pb_csrf_failed_message();
        }

        return 'Das Formular war abgelaufen. Bitte senden Sie es erneut ab.';
    }
}

if (!function_exists('pb_admin_can')) {
    /**
     * Prüft ein Recht des angemeldeten Kontos (config, release, entries, admins).
     *
     * @param array<string, mixed> $admin
     */
    function pb_admin_can(array $admin, string $right): bool
    {
        return ($admin[$right] ?? 'N') === 'Y';
    }
}

if (!function_exists('pb_admin_count_entries')) {
    /**
     * Zählt freigeschaltete und wartende Einträge.
     *
     * @return array{public: int, pending: int}
     */
    function pb_admin_count_entries(PDO $pdo, string $table): array
    {
        $counts = ['public' => 0, 'pending' => 0];
        $stmt = $pdo->query("SELECT status, COUNT(*) AS anzahl FROM {$table} GROUP BY status");
        if ($stmt === false) {
            return $counts;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['status'] === 'R') {
                $counts['public'] = (int) $row['anzahl'];
            } elseif ($row['status'] === 'U') {
                $counts['pending'] = (int) $row['anzahl'];
            }
        }

        return $counts;
    }
}

if (!function_exists('pb_admin_format_date')) {
    /**
     * Datum nach dem Format aus der Konfiguration, mit deutschen Tages- und
     * Monatsnamen (pb_format_date() aus functions.inc.php).
     */
    function pb_admin_format_date(string $format, int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }
        if ($format === '') {
            $format = 'd.m.Y';
        }

        return pb_format_date($format, $timestamp);
    }
}

if (!function_exists('pb_admin_format_time')) {
    /**
     * Uhrzeit nach dem Format aus der Konfiguration; eine reine Zeitangabe
     * wie „18:42“ bekommt „Uhr“ angehängt.
     */
    function pb_admin_format_time(string $format, int $timestamp): string
    {
        $time = pb_admin_format_date($format !== '' ? $format : 'H:i', $timestamp);
        if (preg_match('/^\d{1,2}[:.]\d{2}(?:[:.]\d{2})?$/', $time) === 1) {
            $time .= ' Uhr';
        }

        return $time;
    }
}

if (!function_exists('pb_admin_layout_menu')) {
    /**
     * Gibt die Menüpunkte aus, die das angemeldete Konto sehen darf.
     *
     * @param array<string, mixed> $admin      Konto mit Rechten (config, release, entries, admins)
     * @param string               $activePage Menüpunkt, der als aktiv markiert wird
     */
    function pb_admin_layout_menu(array $admin, string $activePage): void
    {
        $items = [
            ['home', 'pbMenuHome', 'Start', true],
            ['entries', 'pbMenuEntries', 'Einträge', pb_admin_can($admin, 'entries')],
            ['release', 'pbMenuRelease', 'Freischalten', pb_admin_can($admin, 'release')],
            ['admins', 'pbMenuAdmins', 'Admins', pb_admin_can($admin, 'admins')],
            ['configuration', 'pbMenuConfig', 'Konfiguration', pb_admin_can($admin, 'config')],
            ['account', 'pbMenuAccount', 'Mein Konto', true],
            ['license', 'pbMenuLicense', 'Lizenz', true],
        ];
        foreach ($items as [$itemPage, $itemId, $itemLabel, $visible]) {
            if (!$visible) {
                continue;
            }
            // Aktiver Punkt: Klasse active und aria-current in einem Zug.
            $activeAttributes = $itemPage === $activePage ? ' active" aria-current="page' : '';
            echo '                <li class="nav-item"><a id="' . $itemId . '" class="nav-link' . $activeAttributes . '"'
                . ' href="?page=' . $itemPage . '">' . $itemLabel . '</a></li>' . "\n";
        }
    }
}

if (!function_exists('pb_admin_layout_header')) {
    /**
     * Gibt Kopf, Menü, Statuszeile und die vorgemerkte Meldung aus.
     *
     * @param string               $title Seitentitel (escaped)
     * @param array<string, mixed> $opts  Optionen:
     *   - bool   loggedIn      angemeldet?
     *   - array  admin         Konto mit name und Rechten (config, release, entries, admins)
     *   - string activePage    Menüpunkt, der als aktiv markiert wird
     *   - int    publicCount   freigeschaltete Einträge
     *   - int    pendingCount  wartende Einträge
     *   - string guestbookUrl  Link zum Gästebuch
     *   - bool   dbOutdated    Hinweis auf update.php zeigen
     */
    function pb_admin_layout_header(string $title, array $opts = []): void
    {
        $loggedIn = (bool) ($opts['loggedIn'] ?? false);
        /** @var array<string, mixed> $admin */
        $admin = is_array($opts['admin'] ?? null) ? $opts['admin'] : [];
        $activePage = (string) ($opts['activePage'] ?? '');
        $publicCount = (int) ($opts['publicCount'] ?? 0);
        $pendingCount = (int) ($opts['pendingCount'] ?? $opts['hiddenCount'] ?? 0);
        $guestbookUrl = (string) ($opts['guestbookUrl'] ?? '../../pbook.php');
        $dbOutdated = (bool) ($opts['dbOutdated'] ?? false);

        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        echo '<!DOCTYPE html>' . "\n";
        echo '<html lang="de">' . "\n";
        echo '<head>' . "\n";
        echo '    <meta charset="UTF-8">' . "\n";
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        echo '    <meta name="robots" content="noindex, nofollow">' . "\n";
        echo '    <title>' . $esc($title) . '</title>' . "\n";
        echo '    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">' . "\n";
        // Versions-Parameter aus filemtime(): nach jeder Änderung am Stylesheet
        // lädt der Browser die neue Fassung.
        $cssPath = __DIR__ . '/../../assets/powerbook.css';
        $cssVersion = file_exists($cssPath) ? (int) filemtime($cssPath) : 1;
        echo '    <link href="../../assets/powerbook.css?v=' . $cssVersion . '" rel="stylesheet">' . "\n";
        echo '</head>' . "\n";
        echo '<body class="pb-body bg-body-tertiary">' . "\n";

        echo '<nav class="navbar navbar-expand-md bg-dark navbar-dark mb-4 shadow-sm" aria-label="AdminCenter">' . "\n";
        echo '    <div class="container">' . "\n";
        echo '        <a class="navbar-brand pb-admin-brand fw-bold" href="?page=home">PowerBook AdminCenter</a>' . "\n";
        echo '        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pbAdminNav" aria-controls="pbAdminNav" aria-expanded="false" aria-label="Menü ein- und ausklappen">' . "\n";
        echo '            <span class="navbar-toggler-icon"></span>' . "\n";
        echo '        </button>' . "\n";
        echo '        <div class="collapse navbar-collapse" id="pbAdminNav">' . "\n";
        echo '            <ul class="navbar-nav me-auto mb-2 mb-md-0">' . "\n";
        if ($loggedIn) {
            pb_admin_layout_menu($admin, $activePage);
        }
        echo '            </ul>' . "\n";
        echo '            <ul class="navbar-nav ms-auto mb-2 mb-md-0">' . "\n";
        echo '                <li class="nav-item"><a id="pbMenuGuestbook" class="nav-link" href="' . $esc($guestbookUrl) . '" target="_blank" rel="noopener">Gästebuch ansehen</a></li>' . "\n";
        echo '            </ul>' . "\n";
        echo '        </div>' . "\n";
        echo '    </div>' . "\n";
        echo '</nav>' . "\n";

        echo '<main class="container pb-admin">' . "\n";
        if ($loggedIn) {
            echo '    <div id="pbStatusLine" class="d-flex flex-wrap justify-content-between align-items-center gap-2 bg-body border rounded px-3 py-2 mb-4">' . "\n";
            echo '        <div>Angemeldet als <b id="pbStatusName">' . $esc((string) ($admin['name'] ?? '')) . '</b> &middot; ';
            echo '<form action="?page=logout" method="post" class="d-inline">' . csrfField()
                . '<button type="submit" id="pbLogout" class="btn btn-link p-0 align-baseline">Abmelden</button></form></div>' . "\n";
            echo '        <div>Freigeschaltet <span id="pbCountPublic" class="badge text-bg-success">' . $publicCount . '</span>'
                . ' &nbsp;Wartend <span id="pbCountPending" class="badge text-bg-warning">' . $pendingCount . '</span></div>' . "\n";
            echo '    </div>' . "\n";
            if ($dbOutdated) {
                echo '    <div id="pbUpdateHint" class="alert alert-warning">Die Datenbank ist noch auf einem älteren Stand. Rufen Sie <a href="../../update.php">update.php</a> auf.</div>' . "\n";
            }
        }

        $flash = pb_admin_flash_take();
        if ($flash !== null) {
            echo '    ' . pb_admin_message_html($flash['type'], $flash['text']) . "\n";
        }
    }
}

if (!function_exists('pb_admin_layout_footer')) {
    /**
     * Gibt den Fuß des AdminCenters aus.
     */
    function pb_admin_layout_footer(): void
    {
        echo '</main>' . "\n";
        echo '<footer class="container py-4 mt-4 text-center text-body-secondary border-top">' . "\n";
        // Bewusst ohne Versionsnummern (keine Hinweise für Angreifer).
        echo '    <small>PowerBook &middot; <a href="https://www.powerscripts.org" target="_blank" rel="noopener">powerscripts.org</a></small>' . "\n";
        echo '</footer>' . "\n";
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>' . "\n";
        echo '</body>' . "\n";
        echo '</html>' . "\n";
    }
}

if (!function_exists('pb_admin_card_open')) {
    /**
     * Öffnet eine Karte mit Überschrift für eine Seite des AdminCenters.
     *
     * @param string $title Überschrift (escaped)
     * @param string $id    optionale ID der Karte
     */
    function pb_admin_card_open(string $title, string $id = ''): void
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $idAttr = $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '';
        echo '<section class="card shadow-sm mb-4"' . $idAttr . '>' . "\n";
        echo '    <header class="card-header bg-primary text-white">' . "\n";
        echo '        <h2 class="h5 mb-0">' . $titleEsc . '</h2>' . "\n";
        echo '    </header>' . "\n";
        echo '    <div class="card-body">' . "\n";
    }
}

if (!function_exists('pb_admin_card_close')) {
    /**
     * Schließt die mit pb_admin_card_open() geöffnete Karte.
     */
    function pb_admin_card_close(): void
    {
        echo '    </div>' . "\n";
        echo '</section>' . "\n";
    }
}

if (!function_exists('pb_admin_alert')) {
    /**
     * Bootstrap-Hinweis im AdminCenter.
     *
     * @param string $message Vertrauenswürdiges bzw. bereits escaptes HTML
     * @param string $type    success, danger (oder error), warning, info …
     * @param string $id      optionale ID, z. B. "pbMessage"
     */
    function pb_admin_alert(string $message, string $type = 'info', string $id = ''): string
    {
        $type = $type === 'error' ? 'danger' : $type;
        $allowed = ['success', 'danger', 'warning', 'info', 'primary', 'secondary', 'dark', 'light'];
        if (!in_array($type, $allowed, true)) {
            $type = 'info';
        }
        $idAttr = $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '';
        $role = in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status';

        return '<div' . $idAttr . ' class="alert alert-' . $type . '" role="' . $role . '">' . $message . '</div>';
    }
}

if (!function_exists('pb_admin_message_type')) {
    /**
     * Ordnet interne Meldungstypen den Bootstrap-Varianten zu.
     */
    function pb_admin_message_type(string $messageType): string
    {
        return match ($messageType) {
            'success' => 'success',
            'error' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            default => 'info',
        };
    }
}

if (!function_exists('pb_admin_plural')) {
    /**
     * „1 Eintrag“ / „2 Einträge“.
     */
    function pb_admin_plural(int $count, string $one, string $many): string
    {
        return $count === 1 ? '1 ' . $one : $count . ' ' . $many;
    }
}
