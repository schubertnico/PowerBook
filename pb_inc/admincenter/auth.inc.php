<?php

/**
 * PowerBook - PHP Guestbook System
 * Anmeldung, Anmeldedrossel und Sitzung des AdminCenters
 *
 * Wird von index.php eingebunden. Die Funktionen arbeiten nur mit den
 * übergebenen Werten und lassen sich deshalb einzeln testen.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

if (!defined('PB_LOGIN_MAX_FAILURES')) {
    /** Höchstens so viele Fehlversuche je IP-Adresse im Zeitfenster. */
    define('PB_LOGIN_MAX_FAILURES', 10);
}
if (!defined('PB_LOGIN_WINDOW')) {
    /** Zeitfenster der Anmeldedrossel in Sekunden (15 Minuten). */
    define('PB_LOGIN_WINDOW', 900);
}
if (!defined('PB_SESSION_IDLE')) {
    /** Abmelden nach so vielen Sekunden ohne Aufruf (60 Minuten). */
    define('PB_SESSION_IDLE', 3600);
}
if (!defined('PB_LOGIN_ATTEMPTS_TABLE')) {
    define('PB_LOGIN_ATTEMPTS_TABLE', 'pb_login_attempts');
}

if (!function_exists('pb_admin_is_missing_table')) {
    /**
     * true, wenn die Datenbank meldet, dass eine Tabelle fehlt.
     */
    function pb_admin_is_missing_table(PDOException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, "doesn't exist")
            || str_contains($message, 'Base table or view not found')
            || str_contains($message, 'no such table');
    }
}

if (!function_exists('pb_admin_load_account')) {
    /**
     * Lädt ein Admin-Konto über die ID.
     *
     * @return array<string, mixed>|null
     */
    function pb_admin_load_account(PDO $pdo, string $table, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('pb_admin_find_login')) {
    /**
     * Sucht das Konto zur Eingabe im Anmeldeformular: mit „@“ nach der
     * E-Mail-Adresse, sonst nach dem Namen (Namen enthalten kein „@“).
     *
     * @return array<string, mixed>|null
     */
    function pb_admin_find_login(PDO $pdo, string $table, string $input): ?array
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        $column = str_contains($input, '@') ? 'email' : 'name';
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$column} = ? ORDER BY id LIMIT 1");
        $stmt->execute([$input]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('pb_admin_session_array')) {
    /**
     * Die Kontodaten, die den Seiten als $admin_session zur Verfügung stehen.
     *
     * @param array<string, mixed> $account
     *
     * @return array{id: int, name: string, email: string, config: string, release: string, entries: string, admins: string}
     */
    function pb_admin_session_array(array $account): array
    {
        $right = static fn (string $key): string => ($account[$key] ?? 'N') === 'Y' ? 'Y' : 'N';

        return [
            'id' => (int) ($account['id'] ?? 0),
            'name' => (string) ($account['name'] ?? ''),
            'email' => (string) ($account['email'] ?? ''),
            'config' => $right('config'),
            'release' => $right('release'),
            'entries' => $right('entries'),
            'admins' => $right('admins'),
        ];
    }
}

if (!function_exists('pb_admin_session_problem')) {
    /**
     * Prüft eine bestehende Anmeldung. Liefert [Typ, Meldung], wenn die
     * Sitzung beendet werden muss, sonst null.
     *
     * @param array<string, mixed>|null $account Konto aus der Datenbank
     * @param array<string, mixed>      $session Inhalt von $_SESSION
     *
     * @return array{0: string, 1: string}|null
     */
    function pb_admin_session_problem(?array $account, array $session, int $now): ?array
    {
        if ($account === null) {
            return ['warning', 'Sie wurden abgemeldet, weil es Ihr Konto nicht mehr gibt.'];
        }
        if ((int) ($account['pw_changed'] ?? 0) > (int) ($session['pb_login_time'] ?? 0)) {
            return ['warning', 'Ihr Passwort wurde geändert. Bitte melden Sie sich neu an.'];
        }
        if (isset($session['pb_last_activity']) && $now - (int) $session['pb_last_activity'] > PB_SESSION_IDLE) {
            return ['info', 'Sie wurden nach 60 Minuten ohne Aktivität abgemeldet. Bitte melden Sie sich neu an.'];
        }

        return null;
    }
}

if (!function_exists('pb_admin_start_session')) {
    /**
     * Meldet ein Konto an: neue Sitzungs-ID, neues Formular-Token,
     * Anmeldezeit für die Prüfung auf Passwortwechsel und Leerlauf.
     *
     * @param array<string, mixed> $account
     */
    function pb_admin_start_session(array $account, int $now): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            // Schutz vor Session-Fixation: neue ID vor dem Speichern der Anmeldung.
            session_regenerate_id(true);
        }

        $_SESSION['admin_id'] = (int) ($account['id'] ?? 0);
        $_SESSION['admin_name'] = (string) ($account['name'] ?? '');
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['pb_login_time'] = $now;
        $_SESSION['pb_last_activity'] = $now;
        unset($_SESSION['pb_db_outdated']);

        regenerateCsrfToken();
    }
}

if (!function_exists('pb_admin_end_session')) {
    /**
     * Meldet ab: leert die Sitzung und vergibt eine neue Sitzungs-ID, damit
     * eine danach vorgemerkte Meldung noch ankommt.
     */
    function pb_admin_end_session(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }
}

if (!function_exists('pb_admin_login_cleanup')) {
    /**
     * Löscht Einträge der Drossel, die älter als das Zeitfenster sind.
     */
    function pb_admin_login_cleanup(PDO $pdo, int $now): void
    {
        try {
            $stmt = $pdo->prepare('DELETE FROM ' . PB_LOGIN_ATTEMPTS_TABLE . ' WHERE time < ?');
            $stmt->execute([$now - PB_LOGIN_WINDOW]);
        } catch (PDOException $e) {
            // Tabelle fehlt (Datenbank vor update.php): ohne Drossel weiter.
            logDbError('Login attempts cleanup: ' . $e->getMessage());
        }
    }
}

if (!function_exists('pb_admin_login_failures')) {
    /**
     * Zählt die Fehlversuche einer IP-Adresse im Zeitfenster. Zeilen von
     * „Passwort vergessen“ (name reset:…) zählen nicht mit.
     */
    function pb_admin_login_failures(PDO $pdo, string $ip, int $now): int
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM ' . PB_LOGIN_ATTEMPTS_TABLE
                . " WHERE ip = ? AND time >= ? AND name NOT LIKE 'reset:%'"
            );
            $stmt->execute([$ip, $now - PB_LOGIN_WINDOW]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            logDbError('Login attempts count: ' . $e->getMessage());

            return 0;
        }
    }
}

if (!function_exists('pb_admin_login_blocked')) {
    /**
     * true, wenn die IP-Adresse zu viele Fehlversuche hat.
     */
    function pb_admin_login_blocked(PDO $pdo, string $ip, int $now): bool
    {
        return pb_admin_login_failures($pdo, $ip, $now) >= PB_LOGIN_MAX_FAILURES;
    }
}

if (!function_exists('pb_admin_login_failed')) {
    /**
     * Speichert einen Fehlversuch (Name gekürzt).
     */
    function pb_admin_login_failed(PDO $pdo, string $ip, string $name, int $now): void
    {
        try {
            $stmt = $pdo->prepare('INSERT INTO ' . PB_LOGIN_ATTEMPTS_TABLE . ' (ip, name, time) VALUES (?, ?, ?)');
            $stmt->execute([$ip, mb_substr($name, 0, 50), $now]);
        } catch (PDOException $e) {
            logDbError('Login attempts insert: ' . $e->getMessage());
        }
    }
}

if (!function_exists('pb_admin_login_succeeded')) {
    /**
     * Nach erfolgreicher Anmeldung zählen die Fehlversuche dieser IP nicht mehr.
     */
    function pb_admin_login_succeeded(PDO $pdo, string $ip): void
    {
        try {
            $stmt = $pdo->prepare('DELETE FROM ' . PB_LOGIN_ATTEMPTS_TABLE . " WHERE ip = ? AND name NOT LIKE 'reset:%'");
            $stmt->execute([$ip]);
        } catch (PDOException $e) {
            logDbError('Login attempts reset: ' . $e->getMessage());
        }
    }
}

if (!function_exists('pb_admin_db_outdated')) {
    /**
     * true, wenn die Datenbank älter als 3.1 ist (update.php noch nicht gelaufen).
     */
    function pb_admin_db_outdated(PDO $pdo, string $configTable, string $adminTable): bool
    {
        try {
            $pdo->query("SELECT title, mail_from FROM {$configTable} LIMIT 1");
            $pdo->query("SELECT pw_changed FROM {$adminTable} LIMIT 1");
            $pdo->query('SELECT COUNT(*) FROM ' . PB_LOGIN_ATTEMPTS_TABLE);
        } catch (PDOException $e) {
            return true;
        }

        return false;
    }
}

if (!function_exists('pb_admin_verify_login')) {
    /**
     * Prüft Name (oder E-Mail-Adresse) und Passwort. Ein unbekannter Name
     * kostet genauso viel Zeit wie ein falsches Passwort.
     *
     * @return array<string, mixed>|null das Konto bei Erfolg
     */
    function pb_admin_verify_login(PDO $pdo, string $table, string $input, string $password): ?array
    {
        $account = pb_admin_find_login($pdo, $table, $input);
        if ($account === null) {
            password_verify($password, '$2y$12$VYMzZbGNELQBCZ8MGMp3Q.2HIoRFzV4ce2X0GiTToWqySUy89g70W');

            return null;
        }

        return verifyAndMigratePassword($password, (string) ($account['password'] ?? ''), (int) $account['id']) ? $account : null;
    }
}
