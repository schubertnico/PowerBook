# PowerBook Userbereich Bugs - Fix-Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Alle 14 im Audit (docs/2026-04-23-Userbereichs-bugs.md) dokumentierten Bugs im Userbereich beheben, jeweils mit begleitendem PHPUnit-Test, schrittweise Commits.

**Architecture:** Minimale Eingriffe pro Bug; bestehende Datei-Struktur (Include-basiert) bleibt. Tests als PHPUnit in `tests/Unit/` oder `tests/Integration/`. Keine neuen DB-Tabellen außer für BUG-010 (Token-Spalten in `powerbook_admin`).

**Tech Stack:** PHP 8.4, PHPUnit 11, PDO (MySQL in Prod, SQLite in-memory für Tests), Apache, Docker.

**Test-Ausführung:** `docker exec powerbook_web vendor/bin/phpunit --testsuite Unit` (oder Integration). Lokaler Fallback: `vendor/bin/phpunit`.

**Ausschluss:** Improvements aus `2026-04-23-Userbereichs-improvements.md` werden hier **nicht** adressiert, nur die im Bug-Report dokumentierten Fehler.

---

## Datei-Übersicht

Zu verändernde Dateien:
| Bug-ID  | Datei |
|---------|-------|
| BUG-001 | `pb_inc/entry.inc.php` (Variable `$url` → `$homepage_link`), `pb_inc/admincenter/entries.inc.php`, `pb_inc/guestbook.inc.php`, `pb_inc/admincenter/edit.inc.php` (Konsumenten) |
| BUG-002 | `pb_inc/guestbook.inc.php` (require_once error-handler) |
| BUG-003 | `pb_inc/guestbook.inc.php` (Preview doppel-Escape entfernen) |
| BUG-004 | `pb_inc/admincenter/index.php` (allowedPages bereinigen) |
| BUG-005 | `pb_inc/admincenter/edit.inc.php`, `pb_inc/admincenter/statement.inc.php` (history.back → echte Links) |
| BUG-006 | `pb_inc/admincenter/admins.inc.php` (Funktion aus `if (!function_exists)` herausziehen) |
| BUG-007 | `pb_inc/guestbook.inc.php` (`e($message)` → Roh-Echo mit getrennter Nachrichtenstruktur) |
| BUG-008 | `pb_inc/guestbook.inc.php`, `pb_inc/admincenter/edit.inc.php` (Längen-Validierung) |
| BUG-009 | `pb_inc/admincenter/password.inc.php` (generische Response) |
| BUG-010 | `pb_inc/admincenter/password.inc.php`, `install_deu.php` (Reset-Token-Flow, neue Spalten), evtl. Migration-Helper |
| BUG-011 | `install_deu.php`, `.installed` Lockfile |
| BUG-012 | `coverage_report.php` (Syntax-Fix) |
| BUG-013 | `pb_inc/guestbook.inc.php` (Direct-Access-Guard via `PB_ENTRY`-Konstante) |
| BUG-014 | `pb_inc/admincenter/index.php` (`session_regenerate_id(true)` nach Login) |

---

## Globale Vorbereitung

### Task 0: Branch + Baseline-Tests

**Files:** nur Git / Test-Lauf

- [ ] **Step 0.1:** Aktuellen Test-Status dokumentieren — wissen was vorher lief.

```bash
docker exec powerbook_web vendor/bin/phpunit --testsuite Unit --no-coverage 2>&1 | tail -10
```

Expected output: Anzahl ok/fail — notiere die Zahl.

- [ ] **Step 0.2:** Baseline-Commit (nur falls ungetrackte Files vorhanden wären — `git status` prüfen, nichts commiten wenn clean).

```bash
git status --short
```

Expected: Output wie vorher (evtl. `?? index.php`). Keine Aktion nötig, reiner Check-In-Punkt.

---

## BUG-002 zuerst: Fatal Error behebt Test-Infrastruktur für weitere Tests

### Task 1: BUG-002 — `logCsrfFailure()` undefiniert in guestbook.inc.php

**Files:**
- Modify: `pb_inc/guestbook.inc.php` (Zeile 14-16)
- Test: `tests/Integration/GuestbookCsrfIntegrationTest.php` (NEU)

- [ ] **Step 1.1: Failing Test schreiben**

Create `tests/Integration/GuestbookCsrfIntegrationTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookCsrfIntegrationTest extends TestCase
{
    public function testLogCsrfFailureIsAvailableInGuestbookScope(): void
    {
        // The guestbook.inc.php file must have access to logCsrfFailure().
        // We simulate the include chain: config.inc.php + functions.inc.php
        // + whatever guestbook.inc.php adds, and then verify the function exists.
        $guestbookContent = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        $this->assertNotFalse($guestbookContent);
        $this->assertStringContainsString(
            "error-handler.inc.php",
            $guestbookContent,
            'guestbook.inc.php muss error-handler.inc.php inkludieren, damit logCsrfFailure() verfügbar ist.'
        );
    }
}
```

- [ ] **Step 1.2: Test laufen lassen (FAIL erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookCsrfIntegrationTest.php --no-coverage
```

Expected: 1 failure — "guestbook.inc.php muss error-handler.inc.php inkludieren…"

- [ ] **Step 1.3: Fix anwenden**

In `pb_inc/guestbook.inc.php`, Zeile 14-16, nach `require_once __DIR__ . '/functions.inc.php';` ergänzen:

```php
// Include required files
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/functions.inc.php';
require_once __DIR__ . '/error-handler.inc.php';
```

- [ ] **Step 1.4: Test läuft durch (PASS erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookCsrfIntegrationTest.php --no-coverage
```

Expected: 1 test, 1 assertion, OK.

- [ ] **Step 1.5: Live-Verifikation (End-to-End Browser/Curl-Test)**

```bash
curl -s -o /tmp/pb_test_csrf.html -w "%{http_code} %{size_download}\n" \
  -X POST "http://localhost:8080/pbook.php" \
  -d "name=X&text=Y&preview=yes"
grep -c "CSRF" /tmp/pb_test_csrf.html || echo "no csrf text visible"
tail -3 logs/error.log
```

Expected:
- HTTP 200, size >4000 Byte (vollständige Seite, kein Truncate).
- Fehlermeldung "CSRF-Token ungültig" sichtbar.
- Kein neuer Fatal-Error-Eintrag in `logs/error.log` für diesen Request.

- [ ] **Step 1.6: Commit**

```bash
git add pb_inc/guestbook.inc.php tests/Integration/GuestbookCsrfIntegrationTest.php
git commit -m "Fix: BUG-002 - error-handler include in guestbook.inc.php ergaenzt

Ohne diesen Include fuehrte ein ungueltiger CSRF-Token zu einem PHP
Fatal Error (logCsrfFailure() undefined) und die Response wurde ab-
geschnitten. Jetzt wird die Fehlermeldung regulaer angezeigt."
```

---

## BUG-006: Admin-CRUD Fatal Error

### Task 2: BUG-006 — sendAdminEmail aus conditional block herausziehen

**Files:**
- Modify: `pb_inc/admincenter/admins.inc.php` (Zeilen 292-? und darüber hinaus)
- Test: `tests/Unit/AdminHelpersTest.php` (erweitern) ODER neu `tests/Unit/AdminEmailFunctionTest.php`

- [ ] **Step 2.1: Fehlenden Test schreiben**

Create `tests/Unit/AdminEmailFunctionTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminEmailFunctionTest extends TestCase
{
    public function testSendAdminEmailIsDefinedAtRootScope(): void
    {
        // admins.inc.php must declare sendAdminEmail() at root scope,
        // not inside a conditional, so PHP's function-hoisting makes
        // it available before the code that calls it.
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/admins.inc.php');
        $this->assertNotFalse($source);

        // The conditional wrapper must be gone:
        $this->assertStringNotContainsString(
            "if (!function_exists('sendAdminEmail'))",
            $source,
            'sendAdminEmail muss ausserhalb eines if-Blocks deklariert werden.'
        );

        // Function declaration must still exist:
        $this->assertStringContainsString(
            'function sendAdminEmail(',
            $source,
            'sendAdminEmail() muss weiterhin deklariert sein.'
        );
    }

    public function testSendAdminEmailCallableAfterInclude(): void
    {
        // Simulate inclusion of the file and ensure the function is defined.
        // We wrap in a scope to avoid variable leakage.
        (function (): void {
            /** @psalm-suppress UnresolvableInclude */
            require_once POWERBOOK_ROOT . '/pb_inc/admincenter/admins.inc.php';
        })();

        $this->assertTrue(
            function_exists('sendAdminEmail'),
            'sendAdminEmail() muss nach include von admins.inc.php aufrufbar sein.'
        );
    }
}
```

- [ ] **Step 2.2: Test laufen (FAIL erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/AdminEmailFunctionTest.php --no-coverage
```

Expected: `testSendAdminEmailIsDefinedAtRootScope` FAILS (findet "if (!function_exists…)").

- [ ] **Step 2.3: Fix — Funktion aus conditional herausziehen**

In `pb_inc/admincenter/admins.inc.php`:

1. Finde Zeile mit `if (!function_exists('sendAdminEmail')) {` (ca. Zeile 292).
2. Entferne die umschließende `if (...) {` und das zugehörige schließende `}` am Ende.
3. Die Funktionsdeklaration bleibt unverändert.

Vorher (gekürzt):

```php
if (!function_exists('sendAdminEmail')) {
    /**
     * Helper function to send admin notification emails.
     */
    function sendAdminEmail(string $type, array $data): void
    {
        // ... body ...
    }
}
```

Nachher:

```php
/**
 * Helper function to send admin notification emails.
 */
function sendAdminEmail(string $type, array $data): void
{
    // ... body unverändert ...
}
```

Dasselbe Muster gilt ggf. für `buildAddedEmailBody`, `buildEditedEmailBody`, `buildDeletedEmailBody`, `getEmailFooter`, falls diese ebenfalls in `if (!function_exists)`-Blöcken stehen. Prüfen und gleich behandeln.

- [ ] **Step 2.4: Test läuft (PASS erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/AdminEmailFunctionTest.php --no-coverage
```

Expected: 2 tests, both passing.

- [ ] **Step 2.5: Regressionstest — Gesamte Suite**

```bash
docker exec powerbook_web vendor/bin/phpunit --testsuite Unit --no-coverage 2>&1 | tail -5
```

Expected: Alle Tests grün (keine Regressionen durch Refactor).

- [ ] **Step 2.6: Live-Verifikation — Admin anlegen**

```bash
# Relogin
BASE=http://localhost:8080/pb_inc/admincenter/index.php
COOKIE=/tmp/pb_test_cookies.txt
curl -s -c $COOKIE "$BASE?page=login" > /tmp/loginform.html
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/loginform.html | head -1 | sed 's/.*value="//')
NEWPW=$(curl -s "http://localhost:8031/api/v1/messages" | grep -oE 'Passwort: [a-f0-9]+' | head -1 | sed 's/Passwort: //')
PW=${NEWPW:-powerbook}
curl -s -b $COOKIE -c $COOKIE -X POST "$BASE?page=login" \
  -d "csrf_token=$TOK&name=PowerBook&password=$PW&login=yes" > /dev/null

# Admin add
curl -s -b $COOKIE "$BASE?page=admins" > /tmp/adminspage.html
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/adminspage.html | head -1 | sed 's/.*value="//')
curl -s -b $COOKIE -X POST "$BASE?page=admins" \
  -d "csrf_token=$TOK&action=add&add_name=BUG006Fix&add_email=bug006@example.com&add_entries=Y&add_release=Y" \
  -o /tmp/addresult.html
echo "Size: $(wc -c < /tmp/addresult.html)"
grep -oE "erfolgreich.hinzugefuegt|erfolgreich hinzugefügt" /tmp/addresult.html
tail -2 logs/error.log
```

Expected:
- Size >5000 (nicht 2145 wie vor Fix).
- "erfolgreich hinzugefügt" sichtbar.
- KEIN neuer Fatal-Eintrag im Log.

- [ ] **Step 2.7: Commit**

```bash
git add pb_inc/admincenter/admins.inc.php tests/Unit/AdminEmailFunctionTest.php
git commit -m "Fix: BUG-006 - sendAdminEmail() aus conditional block loesen

PHP hoistet Funktionen nur, wenn sie im Root-Scope deklariert sind.
Die if (!function_exists) Kapselung verhinderte die Verfuegbarkeit
beim ersten Aufruf (Zeile 85) und fuehrte zu Fatal Error. Jetzt ist
die Funktion ab File-Start aufrufbar."
```

---

## BUG-014: Session Fixation

### Task 3: BUG-014 — session_regenerate_id nach Login

**Files:**
- Modify: `pb_inc/admincenter/index.php` (Login-Erfolgspfad)
- Test: `tests/Unit/SessionFixationTest.php` (NEU)

- [ ] **Step 3.1: Test schreiben**

Create `tests/Unit/SessionFixationTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SessionFixationTest extends TestCase
{
    public function testLoginFlowContainsSessionRegenerate(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/index.php');
        $this->assertNotFalse($source);

        // After successful login (where $_SESSION['admin_logged_in'] = true is set),
        // we expect session_regenerate_id(true) to be called nearby.
        $this->assertMatchesRegularExpression(
            '/session_regenerate_id\s*\(\s*true\s*\)/',
            $source,
            'Der Login-Handler muss session_regenerate_id(true) nach erfolgreichem Login aufrufen.'
        );
    }
}
```

- [ ] **Step 3.2: Test laufen (FAIL erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/SessionFixationTest.php --no-coverage
```

Expected: FAIL — "session_regenerate_id(true) nicht gefunden".

- [ ] **Step 3.3: Fix anwenden**

In `pb_inc/admincenter/index.php`, nach `$_SESSION['admin_logged_in'] = true;` (ca. Zeile 85), aber **unmittelbar davor**, ergänzen:

```php
// Login successful - regenerate session ID to prevent fixation
session_regenerate_id(true);
$_SESSION['admin_id'] = (int) $admin['id'];
$_SESSION['admin_name'] = $admin['name'];
$_SESSION['admin_logged_in'] = true;
```

Wichtig: `session_regenerate_id(true)` muss aufgerufen werden BEVOR Session-Daten geschrieben werden, damit die neue Session-ID sofort die Werte trägt.

Kontrollieren: Gleiche Ergänzung für den zweiten `$_SESSION['admin_logged_in']`-Zweig weiter unten (Session-Restore beim erneuten Aufruf mit bestehender Session — NICHT dort regenerieren, nur direkt nach dem erfolgreichen Login-POST).

- [ ] **Step 3.4: Test läuft (PASS)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/SessionFixationTest.php --no-coverage
```

Expected: PASS.

- [ ] **Step 3.5: Live-Verifikation**

```bash
SESS=/tmp/pb_sess.txt
> $SESS
BASE=http://localhost:8080/pb_inc/admincenter/index.php
curl -s -c $SESS "$BASE?page=login" > /tmp/sf_form.html
OLD_SID=$(grep PHPSESSID $SESS | awk '{print $7}')
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/sf_form.html | head -1 | sed 's/.*value="//')
NEWPW=$(curl -s "http://localhost:8031/api/v1/messages" | grep -oE 'Passwort: [a-f0-9]+' | head -1 | sed 's/Passwort: //')
PW=${NEWPW:-powerbook}
curl -s -b $SESS -c $SESS -X POST "$BASE?page=login" \
  -d "csrf_token=$TOK&name=PowerBook&password=$PW&login=yes" > /dev/null
NEW_SID=$(grep PHPSESSID $SESS | awk '{print $7}')
echo "OLD: $OLD_SID"
echo "NEW: $NEW_SID"
[ "$OLD_SID" = "$NEW_SID" ] && echo "❌ NICHT regeneriert" || echo "✅ Session regeneriert"
```

Expected: `✅ Session regeneriert` — OLD_SID ≠ NEW_SID.

- [ ] **Step 3.6: Commit**

```bash
git add pb_inc/admincenter/index.php tests/Unit/SessionFixationTest.php
git commit -m "Fix: BUG-014 - session_regenerate_id(true) nach erfolgreichem Login

Verhindert Session-Fixation-Angriffe. Die Session-ID wird nach
erfolgreicher Authentifizierung erneuert, bevor Admin-Daten in
\$_SESSION gespeichert werden."
```

---

## BUG-001: Homepage-Variable-Collision

### Task 4: BUG-001 — $url-Variable in entry.inc.php umbenennen

**Files:**
- Modify: `pb_inc/entry.inc.php` (Zeilen 69-79)
- Modify: `pb_inc/admincenter/entry.inc.php` (Zeilen 69-79 — analog)
- Modify: Konsumenten: `pb_inc/guestbook.inc.php`, `pb_inc/admincenter/entries.inc.php`, `pb_inc/admincenter/release.inc.php`, `pb_inc/admincenter/edit.inc.php` (überall wo `<?= $url ?>` nach `include entry.inc.php` steht)
- Test: `tests/Unit/EntryHomepageLinkTest.php` (NEU)

- [ ] **Step 4.1: Test schreiben**

Create `tests/Unit/EntryHomepageLinkTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EntryHomepageLinkTest extends TestCase
{
    public function testEntryIncPhpDoesNotClobberUrlVariable(): void
    {
        // entry.inc.php should not assign to a variable named $url
        // because guestbook.inc.php uses $url for the raw user-submitted URL
        // in its preview handler. Collision causes BUG-001.
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/entry.inc.php');
        $this->assertNotFalse($source);
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*\$url\s*=/m',
            $source,
            'entry.inc.php darf $url nicht neu zuweisen — Variable kollidiert mit User-Input.'
        );
    }

    public function testEntryIncPhpExposesHomepageLink(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/entry.inc.php');
        $this->assertMatchesRegularExpression(
            '/\$homepage_link\s*=/',
            $source,
            'entry.inc.php muss die Variable $homepage_link fuer Konsumenten setzen.'
        );
    }
}
```

- [ ] **Step 4.2: Test laufen (FAIL erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/EntryHomepageLinkTest.php --no-coverage
```

Expected: FAIL.

- [ ] **Step 4.3: Fix in `pb_inc/entry.inc.php`**

Ersetze den Block (Zeilen 69-79) — der setzt `$url`:

```php
// Homepage URL
$url = '<small>Keine Homepage</small>';
if (!empty($entry['homepage']) && strlen($entry['homepage']) > 1) {
    $homepage = $entry['homepage'];
    // Add http:// if not present
    if (!preg_match('/^https?:\/\//i', $homepage)) {
        $homepage = 'http://' . $homepage;
    }
    $url = '<small><a href="' . e($homepage) . '" target="_blank" rel="noopener noreferrer">Homepage</a></small>';
}
```

durch:

```php
// Homepage URL — bewusst eigene Variable $homepage_link, um nicht mit $url
// des Formular-Input-Scope (guestbook.inc.php Preview) zu kollidieren (BUG-001).
$homepage_link = '<small>Keine Homepage</small>';
if (!empty($entry['homepage']) && strlen($entry['homepage']) > 1) {
    $homepage = $entry['homepage'];
    if (!preg_match('/^https?:\/\//i', $homepage)) {
        $homepage = 'http://' . $homepage;
    }
    $homepage_link = '<small><a href="' . e($homepage) . '" target="_blank" rel="noopener noreferrer">Homepage</a></small>';
}
```

Dazu am Ende der Datei (vor `// Store processed text back`) einen Kompat-Alias für alte Konsumenten:

```php
// Backwards-compat alias (wird inkrementell durch $homepage_link ersetzt).
$url = $homepage_link;
```

Damit bleibt das Template-Rendering funktional, während neue Referenzen `$homepage_link` nutzen können.

Wichtig: In `pb_inc/guestbook.inc.php` (Preview-Pfad ab Zeile 214) wird `entry.inc.php` **vor** den Hidden-Feldern included. Die Hidden-Feldlogik nutzt `$url`. Um die Kollision zu beheben, muss dort expliziter Scope-Schutz kommen — siehe Step 4.5.

- [ ] **Step 4.4: Analoger Fix in `pb_inc/admincenter/entry.inc.php`** (Datei enthält identische Logik).

Zeilen 69-79 identisch ersetzen. Backwards-compat-Alias identisch anhängen.

- [ ] **Step 4.5: Scope-Schutz in `pb_inc/guestbook.inc.php`**

Im Preview-Block (Zeilen 213-215) vor dem `include __DIR__ . '/entry.inc.php';`:

Alt:

```php
        $text_escaped = str_replace('"', '&quot;', $text);

        include __DIR__ . '/entry.inc.php';

        echo '
         <form action="' . e($config_guestbook_name) . '" method="post">
            ' . csrfField() . '
            <input type="hidden" name="name2" value="' . e($name) . '">
            <input type="hidden" name="email2" value="' . e($email2) . '">
            <input type="hidden" name="url2" value="' . e($url) . '">
```

Neu — Roh-URL des Users in `$raw_url` sichern, vor include, und `url2` aus `$raw_url` befüllen:

```php
        $text_escaped = str_replace('"', '&quot;', $text);

        // Bewahre Roh-URL des Users bevor entry.inc.php $url temporaer belegt (BUG-001).
        $raw_url = $url;

        include __DIR__ . '/entry.inc.php';

        echo '
         <form action="' . e($config_guestbook_name) . '" method="post">
            ' . csrfField() . '
            <input type="hidden" name="name2" value="' . e($name) . '">
            <input type="hidden" name="email2" value="' . e($email2) . '">
            <input type="hidden" name="url2" value="' . e($raw_url) . '">
```

- [ ] **Step 4.6: Tests grün**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/EntryHomepageLinkTest.php --no-coverage
docker exec powerbook_web vendor/bin/phpunit --testsuite Unit --no-coverage 2>&1 | tail -5
```

Expected: Neuer Test PASS, Gesamt-Suite weiterhin grün.

- [ ] **Step 4.7: Live-Verifikation**

```bash
# Bestehenden korrupten Entry (id=1) per Admin-Edit korrigieren
BASE=http://localhost:8080/pb_inc/admincenter/index.php
COOKIE=/tmp/pb_cookies.txt
curl -s -c $COOKIE "$BASE?page=login" > /tmp/loginpage.html
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/loginpage.html | head -1 | sed 's/.*value="//')
NEWPW=$(curl -s "http://localhost:8031/api/v1/messages" | grep -oE 'Passwort: [a-f0-9]+' | head -1 | sed 's/Passwort: //')
PW=${NEWPW:-powerbook}
curl -s -b $COOKIE -c $COOKIE -X POST "$BASE?page=login" \
  -d "csrf_token=$TOK&name=PowerBook&password=$PW&login=yes" > /dev/null

# Korrigiere homepage Feld
curl -s -b $COOKIE "$BASE?page=edit&edit_id=1" > /tmp/editform.html
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/editform.html | head -1 | sed 's/.*value="//')
curl -s -b $COOKIE -X POST "$BASE?page=edit" \
  -d "csrf_token=$TOK&edit_id=1&action=update&edit_name=test&edit_email=test@example.com&edit_text=bugfix-test&edit_homepage=example.com&edit_status=R&edit_icon=no&edit_smilies=N" \
  > /dev/null

# Jetzt neuen Eintrag mit Homepage anlegen
COOKIE_GB=/tmp/gb_cookies.txt
curl -s -c $COOKIE_GB "http://localhost:8080/pbook.php" > /tmp/gbform.html
GBTOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/gbform.html | head -1 | sed 's/.*value="//')
# Preview
sleep 35 # spam protection cooldown
curl -s -b $COOKIE_GB -c $COOKIE_GB -X POST "http://localhost:8080/pbook.php" \
  --data-urlencode "csrf_token=$GBTOK" \
  --data-urlencode "name=Bug001Test" \
  --data-urlencode "text=Homepage-Test" \
  --data-urlencode "url=test-domain.example" \
  -d "icon=no&show_gb=no&preview=yes" > /tmp/gbpreview.html
NEW_TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/gbpreview.html | head -1 | sed 's/.*value="//')
# Check url2 hidden field — must contain raw "test-domain.example", not HTML tags
echo "url2-Wert im Preview:"
grep -oE 'name="url2" value="[^"]*"' /tmp/gbpreview.html
# Submit
curl -s -b $COOKIE_GB -c $COOKIE_GB -X POST "http://localhost:8080/pbook.php" \
  --data-urlencode "csrf_token=$NEW_TOK" \
  --data-urlencode "name2=Bug001Test" \
  --data-urlencode "text2=Homepage-Test" \
  --data-urlencode "url2=test-domain.example" \
  -d "icon2=no&icq2=&smilies2=Y&email2=&show_gb=no&show_form=no&preview=no&add_entry=yes" > /dev/null

# Verifiziere: Öffentliche Seite zeigt korrekten Link
curl -s "http://localhost:8080/pbook.php" | grep -oE 'href="http://test-domain.example"|href="http://&lt;small' | head -3
```

Expected:
- url2-Wert: `name="url2" value="test-domain.example"` (nicht `"&lt;small&gt;..."`).
- Öffentliche Seite: `href="http://test-domain.example"` — nicht `href="http://&lt;small..."`.

- [ ] **Step 4.8: Commit**

```bash
git add pb_inc/entry.inc.php pb_inc/admincenter/entry.inc.php pb_inc/guestbook.inc.php tests/Unit/EntryHomepageLinkTest.php
git commit -m "Fix: BUG-001 - Homepage-URL Variable-Kollision

entry.inc.php belegte \$url mit gerendertem HTML-Link. Das ueber-
schrieb die User-Input-Variable im guestbook.inc.php Preview-Pfad,
sodass Hidden-Field url2 das HTML-Markup enthielt und dieses als
rohe URL in die DB wanderte. Jetzt nutzt entry.inc.php \$homepage_link
und guestbook.inc.php bewahrt die Roh-URL in \$raw_url vor dem include."
```

---

## BUG-003: Double HTML-Escape in Preview

### Task 5: BUG-003 — Hidden-Field-Werte nicht doppelt escapen

**Files:**
- Modify: `pb_inc/guestbook.inc.php` (Preview-Block, Zeilen 195-200 und 221-229)
- Test: `tests/Integration/GuestbookPreviewEscapeTest.php` (NEU)

- [ ] **Step 5.1: Test schreiben**

Create `tests/Integration/GuestbookPreviewEscapeTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookPreviewEscapeTest extends TestCase
{
    public function testPreviewDoesNotDoubleEscapeHiddenFields(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        $this->assertNotFalse($source);

        // The preview block should NOT apply e() to variables that have already
        // been escaped earlier. Specifically: after the lines
        //   $name = e($name); $email2 = e($email2); $url = e($url);
        // the hidden fields should use the already-escaped raw form OR we should
        // keep a raw version for the hidden fields.
        // Our chosen approach: keep raw variables in $raw_name, $raw_email2, $raw_url, $raw_icq2
        // and use them in the hidden fields. Check these raw vars exist.
        $this->assertMatchesRegularExpression(
            '/\$raw_name\s*=/',
            $source,
            'Preview-Pfad muss Roh-Werte in $raw_name bewahren (kein Doppel-Escape).'
        );
    }
}
```

- [ ] **Step 5.2: Test laufen (FAIL erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookPreviewEscapeTest.php --no-coverage
```

Expected: FAIL.

- [ ] **Step 5.3: Fix in `pb_inc/guestbook.inc.php`**

Ersetze die Zeilen 192-229 im Preview-Block:

Alt (verkürzt):

```php
    // Sanitize input for display AFTER validation
    $name = e($name);
    $email2 = e($email2);
    $url = e($url);
    $icq2 = e($icq2);

    if ($show_preview === 'yes') {
        $entry = [
            'text' => $text,
            'name' => $name,
            'email' => $email2,
            ...
        ];
        ...
        echo '
         <form action="' . e($config_guestbook_name) . '" method="post">
            ' . csrfField() . '
            <input type="hidden" name="name2" value="' . e($name) . '">
            <input type="hidden" name="email2" value="' . e($email2) . '">
            <input type="hidden" name="url2" value="' . e($raw_url) . '">
            <input type="hidden" name="icon2" value="' . e($icon) . '">
            <input type="hidden" name="icq2" value="' . e($icq2) . '">
            ...
```

Neu — Roh-Werte bewahren:

```php
    // Preserve RAW values for hidden fields (BUG-003: no double-escape).
    $raw_name  = $name;
    $raw_email2 = $email2;
    $raw_url   = $url; // bereits in Task 4 eingefuehrt, hier vereinheitlicht
    $raw_icq2  = $icq2;

    // Sanitize input for display AFTER validation
    $name = e($name);
    $email2 = e($email2);
    $url = e($url);
    $icq2 = e($icq2);

    if ($show_preview === 'yes') {
        $entry = [
            'text' => $text,
            'name' => $name,
            'email' => $email2,
            ...
        ];
        ...

        echo '
         <form action="' . e($config_guestbook_name) . '" method="post">
            ' . csrfField() . '
            <input type="hidden" name="name2" value="' . e($raw_name) . '">
            <input type="hidden" name="email2" value="' . e($raw_email2) . '">
            <input type="hidden" name="url2" value="' . e($raw_url) . '">
            <input type="hidden" name="icon2" value="' . e($icon) . '">
            <input type="hidden" name="icq2" value="' . e($raw_icq2) . '">
            ...
```

Das `text_escaped` bleibt unverändert — das ist ein echter Preview-Text, nicht einfach-doppel-escape.

- [ ] **Step 5.4: Test läuft (PASS)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookPreviewEscapeTest.php --no-coverage
```

- [ ] **Step 5.5: Live-Verifikation — XSS-Probe im Namen**

```bash
sleep 35
COOKIE_GB=/tmp/gb_bug003.txt
curl -s -c $COOKIE_GB "http://localhost:8080/pbook.php" > /tmp/gb003_form.html
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/gb003_form.html | head -1 | sed 's/.*value="//')
curl -s -b $COOKIE_GB -c $COOKIE_GB -X POST "http://localhost:8080/pbook.php" \
  --data-urlencode "csrf_token=$TOK" \
  --data-urlencode "name=<scr-TEST>" \
  --data-urlencode "text=testtext" \
  -d "icon=no&show_gb=no&preview=yes" > /tmp/gb003_preview.html

# Hidden-Field sollte einmal escaped sein: &lt;scr-TEST&gt; — NICHT &amp;lt;scr-TEST&amp;gt;
grep -oE 'name="name2" value="[^"]*"' /tmp/gb003_preview.html
```

Expected: `name="name2" value="&lt;scr-TEST&gt;"` (genau ein Escape-Level, kein `&amp;lt;`).

- [ ] **Step 5.6: Commit**

```bash
git add pb_inc/guestbook.inc.php tests/Integration/GuestbookPreviewEscapeTest.php
git commit -m "Fix: BUG-003 - Doppel-Escape in Preview-Hidden-Fields entfernt

Preview escapte erst die Anzeige-Werte ($name = e(\$name)) und nutzte
dieselben Variablen dann nochmal mit e() als Hidden-Field-Werte. Das
schrieb doppelt-escapte Strings in die Submission. Jetzt werden Roh-
Werte in \$raw_*-Variablen gehalten und nur einmal escapet."
```

---

## BUG-007: "Keine passenden Einträge" wird HTML-escapet

### Task 6: BUG-007 — Suchergebnis-Leer-Meldung als HTML-Link rendern

**Files:**
- Modify: `pb_inc/guestbook.inc.php` (Zeilen 119-125 und 147)
- Test: `tests/Integration/GuestbookSearchEmptyTest.php` (NEU)

- [ ] **Step 6.1: Test schreiben**

Create `tests/Integration/GuestbookSearchEmptyTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookSearchEmptyTest extends TestCase
{
    public function testSearchEmptyMessageIsNotDoubleEscaped(): void
    {
        // Access guestbook.php with a non-matching search term and verify the
        // "Keine passenden Eintraege" hint renders as clickable anchor, not
        // as escaped text.
        $response = file_get_contents(
            'http://localhost:8080/pbook.php?tmp_where=name&tmp_search=UNLIKELY_STRING_XZY42'
        );
        $this->assertNotFalse($response);

        // Must contain a real anchor tag to history.back():
        $this->assertMatchesRegularExpression(
            '/<a href="javascript:history\.back\(\)">Keine passenden/',
            $response,
            'Empty-Suchresultat muss als echter Anchor-Tag gerendert werden.'
        );

        // Must NOT contain escaped HTML entities of the anchor:
        $this->assertStringNotContainsString(
            '&lt;a href=&quot;javascript:history.back()&quot;&gt;',
            $response,
            'Anchor-Markup darf nicht doppelt escapet sein.'
        );
    }
}
```

- [ ] **Step 6.2: Test laufen (FAIL erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookSearchEmptyTest.php --no-coverage
```

Expected: FAIL — escaped entities present.

- [ ] **Step 6.3: Fix**

In `pb_inc/guestbook.inc.php`, Zeile 118-125:

Alt:

```php
    $message = '';
    if ($count_pages === 0) {
        if ($tmp_search === '') {
            $message = 'In diesem Gästebuch gibt es keine Einträge';
        } else {
            $message = '<a href="javascript:history.back()">Keine passenden Einträge.</a>';
        }
    }
```

Neu — zwei getrennte Variablen: `$message` (sicher) und `$message_html` (bereits fertiges HTML):

```php
    $message = '';
    $message_html = '';
    if ($count_pages === 0) {
        if ($tmp_search === '') {
            $message = 'In diesem Gästebuch gibt es keine Einträge';
        } else {
            $message_html = '<a href="javascript:history.back()">Keine passenden Einträge.</a>';
        }
    }
```

In Zeile 147 (die Echo-Stelle):

Alt:

```php
    echo '<div align="center"><b>' . e($message) . '</b></div>';
```

Neu — beide Varianten verträglich ausgeben:

```php
    if ($message !== '') {
        echo '<div align="center"><b>' . e($message) . '</b></div>';
    } elseif ($message_html !== '') {
        echo '<div align="center"><b>' . $message_html . '</b></div>';
    }
```

- [ ] **Step 6.4: Tests laufen**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookSearchEmptyTest.php --no-coverage
docker exec powerbook_web vendor/bin/phpunit --testsuite Unit --no-coverage 2>&1 | tail -5
```

Expected: Neuer Test PASS, Gesamt grün.

- [ ] **Step 6.5: Commit**

```bash
git add pb_inc/guestbook.inc.php tests/Integration/GuestbookSearchEmptyTest.php
git commit -m "Fix: BUG-007 - Empty-Search-Nachricht als Anchor statt Escape-Text

Die Variable \$message enthielt HTML-Markup und wurde mit e() escapet,
wodurch der User den HTML-Code als Text sah. Jetzt getrennt in
\$message (plain) und \$message_html (vorbereitetes HTML, nicht escapet)."
```

---

## BUG-005: edit/statement history.back()-Link ohne ID

### Task 7: BUG-005 — History.back-Links durch echte URLs ersetzen

**Files:**
- Modify: `pb_inc/admincenter/edit.inc.php` (Zeile 37-39)
- Modify: `pb_inc/admincenter/statement.inc.php` (Zeile 35-37)
- Test: `tests/Unit/AdminErrorLinksTest.php` (NEU)

- [ ] **Step 7.1: Test schreiben**

Create `tests/Unit/AdminErrorLinksTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminErrorLinksTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function fileProvider(): array
    {
        return [
            'edit.inc.php'      => [POWERBOOK_ROOT . '/pb_inc/admincenter/edit.inc.php'],
            'statement.inc.php' => [POWERBOOK_ROOT . '/pb_inc/admincenter/statement.inc.php'],
        ];
    }

    /**
     * @dataProvider fileProvider
     */
    public function testNoHistoryBackInErrorMessages(string $file): void
    {
        $source = file_get_contents($file);
        $this->assertNotFalse($source);

        // ID-unknown-error should not use javascript:history.back()
        $this->assertStringNotContainsString(
            'javascript:history.back()',
            $source,
            sprintf('%s darf in Fehlermeldungen kein history.back() verwenden.', basename($file))
        );
    }

    /**
     * @dataProvider fileProvider
     */
    public function testErrorMessageLinksToEntries(string $file): void
    {
        $source = file_get_contents($file);
        $this->assertStringContainsString(
            '?page=entries',
            $source,
            sprintf('%s muss bei Fehler auf ?page=entries verlinken.', basename($file))
        );
    }
}
```

- [ ] **Step 7.2: Test laufen (FAIL erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/AdminErrorLinksTest.php --no-coverage
```

Expected: Beide FAIL.

- [ ] **Step 7.3: Fix in `pb_inc/admincenter/edit.inc.php`**

Zeile 37-39:

Alt:

```php
if ($edit_id === 0) {
    $message = '<a href="javascript:history.back()">Fehler: <b>ID unbekannt!</b></a>';
    $showForm = false;
}
```

Neu:

```php
if ($edit_id === 0) {
    $message = 'Fehler: <b>ID unbekannt!</b> <a href="?page=entries">Zur Eintragsliste</a>';
    $showForm = false;
}
```

- [ ] **Step 7.4: Fix in `pb_inc/admincenter/statement.inc.php`**

Zeile 35-37:

Alt:

```php
if ($id === 0) {
    $message = '<a href="javascript:history.back()">Es ist ein Fehler aufgetreten: <b>ID unbekannt!</b></a>';
    $showForm = false;
}
```

Neu:

```php
if ($id === 0) {
    $message = 'Es ist ein Fehler aufgetreten: <b>ID unbekannt!</b> <a href="?page=entries">Zur Eintragsliste</a>';
    $showForm = false;
}
```

- [ ] **Step 7.5: Tests laufen (PASS)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/AdminErrorLinksTest.php --no-coverage
```

- [ ] **Step 7.6: Commit**

```bash
git add pb_inc/admincenter/edit.inc.php pb_inc/admincenter/statement.inc.php tests/Unit/AdminErrorLinksTest.php
git commit -m "Fix: BUG-005 - Fehlermeldungen verlinken auf ?page=entries statt history.back()

Bei Direktaufruf von ?page=edit bzw. ?page=statement ohne ID fuehrte
der 'javascript:history.back()'-Link bei leerem History-Stack ins Leere.
Jetzt Link zur Eintragsliste."
```

---

## BUG-004: Pseudo-Seiten aus allowedPages entfernen

### Task 8: BUG-004 — `emails`, `pages`, `empty` aus Whitelist entfernen

**Files:**
- Modify: `pb_inc/admincenter/index.php` (Zeile 24-28 — `$allowedPages`)
- Test: `tests/Integration/AdminAllowedPagesTest.php` (NEU)

- [ ] **Step 8.1: Test schreiben**

Create `tests/Integration/AdminAllowedPagesTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class AdminAllowedPagesTest extends TestCase
{
    public function testPseudoPagesAreNotWhitelisted(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/index.php');
        $this->assertNotFalse($source);

        $forbidden = ['emails', 'pages', 'empty'];
        foreach ($forbidden as $page) {
            $this->assertDoesNotMatchRegularExpression(
                "/'{$page}'/",
                explode('// Get request parameters', $source)[0], // Nur Top-Block (Whitelist-Definition)
                sprintf('"%s" darf nicht in \$allowedPages stehen.', $page)
            );
        }
    }

    public function testRealPagesStillWhitelisted(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/index.php');
        $required = ['home', 'login', 'logout', 'license', 'admins', 'entries',
                     'configuration', 'password', 'release', 'entry', 'edit', 'statement'];
        foreach ($required as $page) {
            $this->assertMatchesRegularExpression(
                "/'{$page}'/",
                $source,
                sprintf('"%s" muss in \$allowedPages stehen.', $page)
            );
        }
    }
}
```

- [ ] **Step 8.2: Test laufen (FAIL erwartet für erste Methode)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/AdminAllowedPagesTest.php --no-coverage
```

- [ ] **Step 8.3: Fix**

In `pb_inc/admincenter/index.php`, Zeile 24-28:

Alt:

```php
$allowedPages = [
    'home', 'login', 'logout', 'license', 'admins', 'emails',
    'entries', 'configuration', 'password', 'release', 'entry',
    'pages', 'edit', 'statement', 'empty',
];
```

Neu:

```php
// BUG-004: 'emails' (legacy), 'pages' (paginierungs-helper),
// 'empty' (platzhalter) sind keine eigenstaendigen Seiten und
// werden daher nicht zur Navigation whitelisted.
$allowedPages = [
    'home', 'login', 'logout', 'license', 'admins',
    'entries', 'configuration', 'password', 'release',
    'entry', 'edit', 'statement',
];
```

- [ ] **Step 8.4: Test läuft (PASS)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/AdminAllowedPagesTest.php --no-coverage
```

- [ ] **Step 8.5: Live-Verifikation**

```bash
BASE=http://localhost:8080/pb_inc/admincenter/index.php
COOKIE=/tmp/pb_cookies.txt
for p in emails pages empty; do
  # Sollten auf home fallen (Whitelist filtert sie raus)
  OUT=$(curl -s -b $COOKIE "$BASE?page=$p")
  HAS_HOME=$(echo "$OUT" | grep -c "W I L L K O M M E N")
  echo "page=$p → W I L L K O M M E N Count: $HAS_HOME (erwartet ≥ 1)"
done
```

Expected: Alle drei zeigen nun den Home-Willkommen-Block statt leerer Content-Area.

- [ ] **Step 8.6: Commit**

```bash
git add pb_inc/admincenter/index.php tests/Integration/AdminAllowedPagesTest.php
git commit -m "Fix: BUG-004 - Pseudo-Seiten emails/pages/empty aus allowedPages entfernt

Diese Dateien sind keine eigenstaendigen AdminCenter-Views sondern
Legacy-/Helfer-/Platzhalter-Dateien. Direktaufruf lieferte leere
Content-Area. Nicht-whitelisted Seiten fallen jetzt auf home zurueck."
```

---

## BUG-013: Direct Access Guard in guestbook.inc.php

### Task 9: BUG-013 — Direktaufruf-Schutz für pb_inc/guestbook.inc.php

**Files:**
- Modify: `pbook.php` (Konstante `PB_ENTRY` vor include definieren)
- Modify: `pb_inc/guestbook.inc.php` (Guard am Start)
- Test: `tests/Integration/GuestbookDirectAccessTest.php` (NEU)

- [ ] **Step 9.1: Test schreiben**

Create `tests/Integration/GuestbookDirectAccessTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookDirectAccessTest extends TestCase
{
    public function testDirectCallReturnsForbiddenOrEmpty(): void
    {
        $ch = curl_init('http://localhost:8080/pb_inc/guestbook.inc.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Direct access must either 403 or produce a body <200 Byte (essentially empty).
        $this->assertTrue(
            $httpCode === 403 || strlen((string) $body) < 200,
            sprintf(
                'Direktaufruf /pb_inc/guestbook.inc.php muss blockiert sein. HTTP=%d Bytes=%d',
                $httpCode,
                strlen((string) $body)
            )
        );
    }
}
```

- [ ] **Step 9.2: Test laufen (FAIL erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookDirectAccessTest.php --no-coverage
```

Expected: FAIL — Body ist ~5300 Byte.

- [ ] **Step 9.3: Fix in `pbook.php`**

Vor der `require_once __DIR__ . '/pb_inc/config.inc.php';` Zeile (Zeile 15) ergänzen:

```php
// Include config early for session_start() before any output
if (!defined('PB_ENTRY')) {
    define('PB_ENTRY', true);
}
require_once __DIR__ . '/pb_inc/config.inc.php';
```

- [ ] **Step 9.4: Fix in `pb_inc/guestbook.inc.php`**

Am Dateianfang, nach `declare(strict_types=1);`, vor den require-Zeilen:

```php
declare(strict_types=1);

// Direktaufruf blockieren (BUG-013).
if (!defined('PB_ENTRY')) {
    http_response_code(403);
    exit('Forbidden');
}

// Include required files
require_once __DIR__ . '/config.inc.php';
...
```

Wichtig: PHPUnit-Tests die via Bootstrap laufen, definieren die Konstante nicht. Prüfe ob existierende Unit-Tests `guestbook.inc.php` inkludieren; falls ja, im Test-Bootstrap `define('PB_ENTRY', true);` ergänzen.

- [ ] **Step 9.5: Test-Bootstrap prüfen**

```bash
grep -rn "guestbook.inc.php" tests/ 2>&1 | head -10
```

Falls Ergebnisse vorhanden: In `tests/bootstrap.php` vor dem require_once-Block ergänzen:

```php
// Allow test-suite to include guestbook.inc.php without triggering direct-access guard.
if (!defined('PB_ENTRY')) {
    define('PB_ENTRY', true);
}
```

- [ ] **Step 9.6: Tests laufen**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookDirectAccessTest.php --no-coverage
docker exec powerbook_web vendor/bin/phpunit --testsuite Unit --no-coverage 2>&1 | tail -5
```

Expected: Neuer Test PASS, keine Regressionen.

- [ ] **Step 9.7: Commit**

```bash
git add pbook.php pb_inc/guestbook.inc.php tests/Integration/GuestbookDirectAccessTest.php tests/bootstrap.php
git commit -m "Fix: BUG-013 - Direktaufruf-Schutz fuer guestbook.inc.php

Ohne diesen Guard konnte /pb_inc/guestbook.inc.php direkt aufgerufen
werden und rendernde Logik ablaufen. Jetzt 403 bei Direktaufruf.
PB_ENTRY Konstante wird in pbook.php und im Test-Bootstrap gesetzt."
```

---

## BUG-008: Server-seitige Längenvalidierung

### Task 10: BUG-008 — Längenvalidierung im Gästebuch-Formular

**Files:**
- Modify: `pb_inc/guestbook.inc.php` (Preview-Validierung, Zeile 179-190)
- Modify: `pb_inc/admincenter/edit.inc.php` (Update-Validierung)
- Test: `tests/Integration/GuestbookLengthValidationTest.php` (NEU)

- [ ] **Step 10.1: Test schreiben**

Create `tests/Integration/GuestbookLengthValidationTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class GuestbookLengthValidationTest extends TestCase
{
    public function testGuestbookRejectsOverlongName(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        $this->assertNotFalse($source);

        // Look for a mb_strlen or strlen check with the name variable and a max limit
        $this->assertMatchesRegularExpression(
            '/(mb_)?strlen\s*\(\s*\$name\s*\)\s*>\s*\d+/',
            $source,
            'guestbook.inc.php muss serverseitig die Namenslaenge pruefen.'
        );
    }

    public function testGuestbookRejectsOverlongText(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/guestbook.inc.php');
        $this->assertMatchesRegularExpression(
            '/(mb_)?strlen\s*\(\s*\$text\s*\)\s*>\s*\d+/',
            $source,
            'guestbook.inc.php muss serverseitig die Textlaenge pruefen.'
        );
    }
}
```

- [ ] **Step 10.2: Test laufen (FAIL erwartet)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookLengthValidationTest.php --no-coverage
```

- [ ] **Step 10.3: Fix in `pb_inc/guestbook.inc.php`**

Im Preview-Validierungs-Block (nach `elseif (strlen($email2) >= 1 && !filter_var(...))` (Zeile 185), vor dem `else {` (Zeile 189)) neue Zweige ergänzen:

Alt:

```php
    } elseif (strlen(trim($name)) === 0) {
        $error = 'Bitte einen <b>Name</b> eingeben!';
        $show_form = 'yes';
    } elseif (strlen(trim($text)) === 0) {
        $error = 'Bitte einen <b>Text</b> eingeben!!';
        $show_form = 'yes';
    } elseif (strlen($email2) >= 1 && !filter_var($email2, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige <b>eMail-Adresse</b>!';
        $show_form = 'yes';
    } else {
```

Neu:

```php
    } elseif (strlen(trim($name)) === 0) {
        $error = 'Bitte einen <b>Name</b> eingeben!';
        $show_form = 'yes';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Der <b>Name</b> darf hoechstens 100 Zeichen lang sein!';
        $show_form = 'yes';
    } elseif (strlen(trim($text)) === 0) {
        $error = 'Bitte einen <b>Text</b> eingeben!!';
        $show_form = 'yes';
    } elseif (mb_strlen($text) > 5000) {
        $error = 'Der <b>Text</b> darf hoechstens 5000 Zeichen lang sein!';
        $show_form = 'yes';
    } elseif (strlen($email2) >= 1 && !filter_var($email2, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige <b>eMail-Adresse</b>!';
        $show_form = 'yes';
    } elseif (mb_strlen($email2) > 250) {
        $error = 'Die <b>eMail-Adresse</b> darf hoechstens 250 Zeichen lang sein!';
        $show_form = 'yes';
    } elseif (mb_strlen($url) > 255) {
        $error = 'Die <b>Homepage-URL</b> darf hoechstens 255 Zeichen lang sein!';
        $show_form = 'yes';
    } else {
```

- [ ] **Step 10.4: Fix in `pb_inc/admincenter/edit.inc.php`**

Nach der E-Mail-Validierung im Update-Zweig (suche `elseif (!empty($edit_email) && !filter_var(...)`) ergänzen:

```php
    } elseif (!empty($edit_email) && !filter_var($edit_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'E-Mail-Adresse ist ungültig!';
        $messageType = 'error';
    } elseif (mb_strlen($edit_name) > 100) {
        $message = 'Der Name darf hoechstens 100 Zeichen lang sein!';
        $messageType = 'error';
    } elseif (mb_strlen($edit_text) > 5000) {
        $message = 'Der Text darf hoechstens 5000 Zeichen lang sein!';
        $messageType = 'error';
    } elseif (mb_strlen($edit_email) > 250) {
        $message = 'Die E-Mail-Adresse darf hoechstens 250 Zeichen lang sein!';
        $messageType = 'error';
    } elseif (mb_strlen($edit_homepage) > 255) {
        $message = 'Die Homepage-URL darf hoechstens 255 Zeichen lang sein!';
        $messageType = 'error';
    } else {
```

- [ ] **Step 10.5: Tests laufen**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/GuestbookLengthValidationTest.php --no-coverage
docker exec powerbook_web vendor/bin/phpunit --testsuite Unit --no-coverage 2>&1 | tail -5
```

- [ ] **Step 10.6: Live-Verifikation**

```bash
sleep 35
COOKIE_GB=/tmp/gb008.txt
curl -s -c $COOKIE_GB "http://localhost:8080/pbook.php" > /tmp/gb008_form.html
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/gb008_form.html | head -1 | sed 's/.*value="//')
LONG_NAME=$(printf 'A%.0s' $(seq 1 200))
curl -s -b $COOKIE_GB -X POST "http://localhost:8080/pbook.php" \
  --data-urlencode "csrf_token=$TOK" \
  --data-urlencode "name=$LONG_NAME" \
  --data-urlencode "text=OK" \
  -d "icon=no&show_gb=no&preview=yes" > /tmp/gb008_res.html
grep -oE "Name.*hoechstens.*100|hoechstens 100|Eintragen" /tmp/gb008_res.html | head -3
```

Expected: "Name ... hoechstens 100" Fehlermeldung sichtbar, kein "Eintragen!"-Button.

- [ ] **Step 10.7: Commit**

```bash
git add pb_inc/guestbook.inc.php pb_inc/admincenter/edit.inc.php tests/Integration/GuestbookLengthValidationTest.php
git commit -m "Fix: BUG-008 - Serverseitige Laengenvalidierung fuer Name/Text/Email/URL

Client-seitiges maxlength=X konnte via Curl/DevTools umgangen werden.
Jetzt serverseitige mb_strlen-Pruefungen in Gaestebuch-Preview und
Admin-Entry-Edit."
```

---

## BUG-009: Password-Recovery User Enumeration

### Task 11: BUG-009 — Generische Response für Password Recovery

**Files:**
- Modify: `pb_inc/admincenter/password.inc.php` (Zeile 41-52)
- Test: `tests/Unit/PasswordRecoveryGenericTest.php` (NEU)

- [ ] **Step 11.1: Test schreiben**

Create `tests/Unit/PasswordRecoveryGenericTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PasswordRecoveryGenericTest extends TestCase
{
    public function testErrorMessageDoesNotDiscloseExistence(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        $this->assertNotFalse($source);

        // Old message must be gone
        $this->assertStringNotContainsString(
            'Admin in Datenbank nicht gefunden',
            $source,
            'Die enumerations-freundliche Meldung "Admin in Datenbank nicht gefunden" darf nicht mehr verwendet werden.'
        );
    }

    public function testResponseUsesGenericWording(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        // Expect a generic wording like "Falls ... existiert, wurde ... gesendet".
        $this->assertMatchesRegularExpression(
            '/Falls\s+ein\s+Konto\s+(mit|zu)/i',
            $source,
            'Response-Text soll generisch sein (z. B. "Falls ein Konto zu diesem Namen existiert, wurde eine E-Mail versandt.").'
        );
    }
}
```

- [ ] **Step 11.2: Test laufen (FAIL)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/PasswordRecoveryGenericTest.php --no-coverage
```

- [ ] **Step 11.3: Fix in `pb_inc/admincenter/password.inc.php`**

Finde im Recovery-Zweig den Block:

```php
            if (!$admin) {
                $message = 'Admin in Datenbank nicht gefunden!';
                $messageType = 'error';
            } else {
                // Generate new temporary password
                ...
                $message = 'Ein neues Passwort wurde an Ihre E-Mail versandt.';
                $messageType = 'success';
            }
```

Ersetze durch generische Antwort — immer gleicher Text, unabhängig von Existenz:

```php
            $genericResponse = 'Falls ein Konto zu diesem Namen oder dieser E-Mail-Adresse existiert, wurde eine E-Mail mit weiteren Anweisungen verschickt.';

            if (!$admin) {
                // Do nothing (no password reset), but show the same message.
                $message = $genericResponse;
                $messageType = 'info';
            } else {
                // Trigger existing reset logic (wird in Task 12 durch Token-Flow ersetzt).
                // ... bestehender Reset- & Mail-Versand-Code bleibt hier ...

                $message = $genericResponse;
                $messageType = 'info';
            }
```

Achtung: Lasse den vorhandenen Reset- und Mail-Versand-Code in-place (wird in Task 12 überarbeitet). Nur die `$message`/`$messageType`-Zuweisungen harmonisieren, und die Fallunterscheidung entfernen.

- [ ] **Step 11.4: Tests laufen**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/PasswordRecoveryGenericTest.php --no-coverage
```

Expected: PASS.

- [ ] **Step 11.5: Live-Verifikation**

```bash
BASE=http://localhost:8080/pb_inc/admincenter/index.php
SESS=/tmp/pr.txt

# Non-existing user
> $SESS
curl -s -c $SESS "$BASE?page=password" > /tmp/prform.html
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/prform.html | head -1 | sed 's/.*value="//')
curl -s -b $SESS -X POST "$BASE?page=password" \
  -d "csrf_token=$TOK&action=recover&name=NoSuchAdmin_UNIQ&email_known=" -o /tmp/res_nonex.html
NONEX_MSG=$(grep -oE "Falls ein Konto|nicht gefunden|versandt" /tmp/res_nonex.html | head -1)

# Existing user
> $SESS
curl -s -c $SESS "$BASE?page=password" > /tmp/prform2.html
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/prform2.html | head -1 | sed 's/.*value="//')
curl -s -b $SESS -X POST "$BASE?page=password" \
  -d "csrf_token=$TOK&action=recover&name=PowerBook&email_known=" -o /tmp/res_exist.html
EXIST_MSG=$(grep -oE "Falls ein Konto|nicht gefunden|versandt" /tmp/res_exist.html | head -1)

echo "NonExisting: $NONEX_MSG"
echo "Existing:    $EXIST_MSG"
```

Expected: Beide Antworten identisch (enthalten "Falls ein Konto ...").

- [ ] **Step 11.6: Commit**

```bash
git add pb_inc/admincenter/password.inc.php tests/Unit/PasswordRecoveryGenericTest.php
git commit -m "Fix: BUG-009 - Password-Recovery gibt einheitliche Response (User Enumeration)

Vorher unterscheidbare Meldungen 'Admin nicht gefunden' vs. 'E-Mail
versandt' ermoeglichten das Enumerieren gueltiger Admin-Namen. Jetzt
immer dieselbe generische Info-Meldung."
```

---

## BUG-010: Token-basierter Password-Reset-Flow

### Task 12: BUG-010 — Reset-Token statt Sofort-Passwort-Wechsel

**Files:**
- Modify: `install_deu.php` (neue Spalten `reset_token`, `reset_token_expires` bei Table-Create)
- Create: `pb_inc/admincenter/password_migrate.php` (Helper: Spalten bei Bedarf hinzufügen)
- Modify: `pb_inc/admincenter/index.php` (lädt Migration-Helper)
- Modify: `pb_inc/admincenter/password.inc.php` (Token-Flow)
- Test: `tests/Integration/PasswordResetTokenFlowTest.php` (NEU)

- [ ] **Step 12.1: Migration-Helper erstellen**

Create `pb_inc/admincenter/password_migrate.php`:

```php
<?php
/**
 * PowerBook - Auto-Migration: fügt reset-token-Spalten zu powerbook_admin hinzu.
 * Wird beim Laden des AdminCenter einmalig ausgeführt (idempotent).
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $pb_admin */

try {
    // SQLite- und MySQL-kompatible Spalten-Existenzprüfung
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $hasColumn = function (string $column) use ($pdo, $pb_admin, $driver): bool {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info({$pb_admin})");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ((string) $row['name'] === $column) {
                    return true;
                }
            }
            return false;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_name = ? AND column_name = ?');
        $stmt->execute([$pb_admin, $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$hasColumn('reset_token')) {
        $pdo->exec("ALTER TABLE {$pb_admin} ADD COLUMN reset_token VARCHAR(64) NULL");
    }
    if (!$hasColumn('reset_token_expires')) {
        $pdo->exec("ALTER TABLE {$pb_admin} ADD COLUMN reset_token_expires INT NULL");
    }
} catch (PDOException $e) {
    // Migration-Fehler ignorieren wir hier (Logs werden via error-handler.inc.php geschrieben).
    if (function_exists('logDbError')) {
        logDbError('password_migrate: ' . $e->getMessage());
    }
}
```

- [ ] **Step 12.2: Migration in index.php einbinden**

In `pb_inc/admincenter/index.php` nach `require_once __DIR__ . '/../validation.inc.php';` ergänzen:

```php
require_once __DIR__ . '/../validation.inc.php';
require_once __DIR__ . '/password_migrate.php';
```

- [ ] **Step 12.3: install_deu.php — neue Spalten in Table-Create aufnehmen**

Suche in `install_deu.php` die `CREATE TABLE {$pb_admin} (...)` Anweisung und ergänze:

```sql
CREATE TABLE {$pb_admin} (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(250) NOT NULL,
    config CHAR(1) DEFAULT 'N',
    `release` CHAR(1) DEFAULT 'N',
    entries CHAR(1) DEFAULT 'N',
    admins CHAR(1) DEFAULT 'N',
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_token_expires INT DEFAULT NULL,
    PRIMARY KEY (id)
)
```

Die bestehenden Spalten bitte unverändert belassen; nur `reset_token` und `reset_token_expires` anfügen.

- [ ] **Step 12.4: Test schreiben**

Create `tests/Integration/PasswordResetTokenFlowTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PasswordResetTokenFlowTest extends TestCase
{
    public function testPasswordFileUsesResetToken(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        $this->assertNotFalse($source);
        $this->assertStringContainsString('reset_token', $source, 'Token-Flow muss reset_token nutzen.');
        $this->assertStringContainsString('reset_token_expires', $source);
    }

    public function testPasswordFileDoesNotImmediatelyUpdatePassword(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/password.inc.php');
        // The recover action should no longer contain a direct UPDATE password = ?
        // without prior token validation
        $this->assertStringNotContainsString(
            'UPDATE {$pb_admin} SET password',
            $source,
            'Sofortiger Passwort-Update in Recovery-Handler muss entfernt werden.'
        );
    }
}
```

- [ ] **Step 12.5: Test laufen (FAIL)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/PasswordResetTokenFlowTest.php --no-coverage
```

- [ ] **Step 12.6: password.inc.php Token-Flow implementieren**

Ersetze den vollständigen Inhalt von `pb_inc/admincenter/password.inc.php` durch:

```php
<?php
/**
 * PowerBook - Password Recovery (Token Flow, BUG-010 Fix)
 * Schritt 1: User fragt Recovery an → Token + Mail-Link.
 * Schritt 2: User klickt Link → neues Passwort setzen.
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $pb_admin */

$message = '';
$messageType = '';
$showForm = true;
$showSetPasswordForm = false;
$action = $_POST['action'] ?? '';
$tokenParam = $_GET['token'] ?? '';

// Schritt 2: Link mit Token aufgerufen → neues Passwort setzen
if (!empty($tokenParam) && $action !== 'recover' && $action !== 'set_password') {
    $stmt = $pdo->prepare("SELECT id, name, reset_token_expires FROM {$pb_admin} WHERE reset_token = ? LIMIT 1");
    $stmt->execute([$tokenParam]);
    $tokenAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenAdmin || (int) $tokenAdmin['reset_token_expires'] < time()) {
        $message = 'Der Reset-Link ist ungültig oder abgelaufen. Bitte erneut anfordern.';
        $messageType = 'error';
        $showForm = true; // Zurueck auf Recovery-Anfrage-Form
    } else {
        $showSetPasswordForm = true;
        $showForm = false;
    }
}

// Schritt 2b: Neues Passwort wird gespeichert
if ($action === 'set_password' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $token = trim($_POST['token'] ?? '');
    $newPw1 = $_POST['new_password1'] ?? '';
    $newPw2 = $_POST['new_password2'] ?? '';

    $stmt = $pdo->prepare("SELECT id, reset_token_expires FROM {$pb_admin} WHERE reset_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $tokenAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenAdmin || (int) $tokenAdmin['reset_token_expires'] < time()) {
        $message = 'Der Reset-Link ist ungültig oder abgelaufen. Bitte erneut anfordern.';
        $messageType = 'error';
    } elseif (strlen($newPw1) < 8) {
        $message = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        $messageType = 'error';
        $showSetPasswordForm = true;
        $showForm = false;
    } elseif ($newPw1 !== $newPw2) {
        $message = 'Die beiden Passworte stimmen nicht ueberein.';
        $messageType = 'error';
        $showSetPasswordForm = true;
        $showForm = false;
    } else {
        $hashed = password_hash($newPw1, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE {$pb_admin} SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt->execute([$hashed, $tokenAdmin['id']]);

        $message = 'Passwort erfolgreich geaendert. Sie koennen sich jetzt einloggen.';
        $messageType = 'success';
        $showForm = false;
        $showSetPasswordForm = false;
    }
    regenerateCsrfToken();
}

// Schritt 1: Recovery anfordern
if ($action === 'recover' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $name = trim($_POST['name'] ?? '');
    $emailKnown = trim($_POST['email_known'] ?? '');

    $genericResponse = 'Falls ein Konto zu diesem Namen oder dieser E-Mail-Adresse existiert, wurde eine E-Mail mit einem Reset-Link verschickt.';

    if ($name === '' && $emailKnown === '') {
        $message = 'Bitte geben Sie Ihren Namen oder Ihre E-Mail-Adresse ein.';
        $messageType = 'error';
    } else {
        // Admin finden (wie bisher), aber Response immer generisch.
        $admin = null;
        try {
            if ($name !== '') {
                $stmt = $pdo->prepare("SELECT id, name, email FROM {$pb_admin} WHERE name = ? LIMIT 1");
                $stmt->execute([$name]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($emailKnown !== '') {
                $stmt = $pdo->prepare("SELECT id, name, email FROM {$pb_admin} WHERE email = ? LIMIT 1");
                $stmt->execute([$emailKnown]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($admin) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + 30 * 60; // 30 Minuten
                $stmt = $pdo->prepare("UPDATE {$pb_admin} SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $admin['id']]);

                $adminUrl = $config_admin_url ?? '';
                $resetLink = rtrim($adminUrl, '/') . '/index.php?page=password&token=' . $token;
                $to = sanitizeEmailHeader($admin['email']);
                if (!empty($to)) {
                    $subject = 'PowerBook: Passwort zuruecksetzen';
                    $headers = "From: PowerBook <noreply@powerbook.local>\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    $body = "Hallo,\n\nklicken Sie auf den folgenden Link, um ein neues Passwort zu setzen:\n\n";
                    $body .= $resetLink . "\n\n";
                    $body .= "Der Link ist 30 Minuten gueltig.\n\nFalls Sie kein Passwort zurueckgesetzt haben, ignorieren Sie diese Mail.\n";
                    @mail($to, $subject, $body, $headers);
                }
            }
        } catch (PDOException $e) {
            if (function_exists('logDbError')) {
                logDbError('Password recovery: ' . $e->getMessage());
            }
        }

        $message = $genericResponse;
        $messageType = 'info';
    }
    regenerateCsrfToken();
}
?>

<tr>
    <td bgcolor="#3F5070" align="center">
        <b class="headline">P A S S W O R T &nbsp; V E R G E S S E N</b>
    </td>
</tr>
<tr>
    <td bgcolor="#001F3F" valign="top">
<?php if ($message !== '') { ?>
        <div style="padding: 10px; margin: 10px 0; background: <?= $messageType === 'success' ? '#003300' : ($messageType === 'error' ? '#330000' : '#003355') ?>;">
            <?= $message ?>
        </div>
<?php } ?>

<?php if ($showSetPasswordForm) { ?>
<p>Neues Passwort festlegen:</p>
<form action="?page=password" method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="set_password">
    <input type="hidden" name="token" value="<?= e($tokenParam) ?>">
    <table border="0">
        <tr><td>Neues Passwort:</td><td><input type="password" name="new_password1" minlength="8"></td></tr>
        <tr><td>Wiederholen:</td><td><input type="password" name="new_password2" minlength="8"></td></tr>
        <tr><td></td><td><input type="submit" value="Passwort setzen"></td></tr>
    </table>
</form>
<?php } elseif ($showForm) { ?>
<p>Geben Sie Ihren Admin-Namen oder Ihre E-Mail-Adresse ein. Sie erhalten einen Link zum Zuruecksetzen.</p>
<form action="?page=password" method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="recover">
    <table border="0">
        <tr><td>Admin-Name:</td><td><input type="text" name="name" value=""></td></tr>
        <tr><td>oder E-Mail:</td><td><input type="text" name="email_known" value=""></td></tr>
        <tr><td></td><td><input type="submit" value="Reset-Link anfordern"></td></tr>
    </table>
</form>
<?php } ?>
    </td>
</tr>
```

Wichtig: Die ursprüngliche Datei (pre-Fix) enthält vermutlich mehr Markup drumherum (wie alle .inc.php-Fragmente). Überprüfe vor dem Überschreiben die Originaldatei; nur die Logik und das Form-Markup ersetzen, das Include-Muster `<?php ... ?>` + das Tabellen-Gerüst `<tr><td>...</td></tr>` beibehalten.

- [ ] **Step 12.7: Tests laufen**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/PasswordResetTokenFlowTest.php --no-coverage
docker exec powerbook_web vendor/bin/phpunit --testsuite Unit --no-coverage 2>&1 | tail -5
```

- [ ] **Step 12.8: Live-Verifikation**

```bash
# Der bisherige Admin PowerBook hat nach BUG-010-Tests bereits ein neu generiertes PW.
# Wir setzen es zurueck per Token-Flow.

BASE=http://localhost:8080/pb_inc/admincenter/index.php
SESS=/tmp/reset.txt
> $SESS

# Schritt 1: Recovery anfordern
curl -s -c $SESS "$BASE?page=password" > /tmp/recovery_form.html
TOK=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/recovery_form.html | head -1 | sed 's/.*value="//')
curl -s -b $SESS -X POST "$BASE?page=password" \
  -d "csrf_token=$TOK&action=recover&name=PowerBook&email_known=" -o /tmp/recovery_res.html
grep -oE "Falls ein Konto" /tmp/recovery_res.html

# Mail aus Mailpit holen und Token extrahieren
sleep 1
LAST_MAIL_ID=$(curl -s "http://localhost:8031/api/v1/messages?limit=1" | python3 -c "import sys,json;d=json.load(sys.stdin);print(d['messages'][0]['ID'] if d.get('messages') else '')")
echo "Letzte Mail-ID: $LAST_MAIL_ID"
TOKEN_URL=$(curl -s "http://localhost:8031/api/v1/message/$LAST_MAIL_ID" | python3 -c "import sys,json,re;d=json.load(sys.stdin);body=d.get('Text','');m=re.search(r'token=([a-f0-9]+)',body);print(m.group(1) if m else '')")
echo "Reset Token: ${TOKEN_URL:0:20}..."

# Schritt 2: Link abrufen (Token im URL)
curl -s -b $SESS "$BASE?page=password&token=$TOKEN_URL" > /tmp/setpw_form.html
TOK2=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/setpw_form.html | head -1 | sed 's/.*value="//')

# Schritt 2b: Neues Passwort setzen
curl -s -b $SESS -X POST "$BASE?page=password" \
  -d "csrf_token=$TOK2&action=set_password&token=$TOKEN_URL&new_password1=NewSecret123&new_password2=NewSecret123" \
  -o /tmp/setpw_res.html
grep -oE "erfolgreich.geaendert|erfolgreich|ungueltig|abgelaufen" /tmp/setpw_res.html | head -1

# Login mit neuem PW verifizieren
SESS2=/tmp/reset_login.txt
curl -s -c $SESS2 "$BASE?page=login" > /tmp/login_form2.html
TOK3=$(grep -oE 'csrf_token" value="[a-f0-9]+' /tmp/login_form2.html | head -1 | sed 's/.*value="//')
curl -s -b $SESS2 -c $SESS2 -X POST "$BASE?page=login" \
  -d "csrf_token=$TOK3&name=PowerBook&password=NewSecret123&login=yes" -o /tmp/login_res2.html
grep -oE "erfolgreich|falsches" /tmp/login_res2.html | head -1
```

Expected:
- Schritt 1: "Falls ein Konto" sichtbar.
- Token-URL Mail extrahiert.
- Schritt 2b: "erfolgreich geaendert".
- Login mit NewSecret123: "erfolgreich".

- [ ] **Step 12.9: Commit**

```bash
git add install_deu.php pb_inc/admincenter/password.inc.php pb_inc/admincenter/password_migrate.php pb_inc/admincenter/index.php tests/Integration/PasswordResetTokenFlowTest.php
git commit -m "Fix: BUG-010 - Token-basierter Passwort-Reset statt Sofort-Ueberschreibung

Vorher setzte jede Recovery-Anfrage sofort ein neues Passwort in der DB
- DoS-Vektor gegen Admin. Jetzt: Mail mit 30-Min-Token-Link, Passwort
wird erst nach Klick und Eingabe des neuen Passworts gewechselt. Neue
Spalten reset_token + reset_token_expires werden via Auto-Migration und
im install_deu.php angelegt."
```

---

## BUG-011: install_deu.php Lock-File

### Task 13: BUG-011 — install_deu.php nach Installation sperren

**Files:**
- Modify: `install_deu.php` (Lock-File-Check und -Schreiben)
- Test: `tests/Integration/InstallLockTest.php` (NEU)

- [ ] **Step 13.1: Test schreiben**

Create `tests/Integration/InstallLockTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class InstallLockTest extends TestCase
{
    public function testInstallDeuCreatesAndChecksLockFile(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/install_deu.php');
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            '.installed',
            $source,
            'install_deu.php muss ein .installed Lock-File erstellen/pruefen.'
        );
        $this->assertStringContainsString(
            'file_exists',
            $source,
            'install_deu.php muss file_exists-Check fuer Lock-File enthalten.'
        );
    }
}
```

- [ ] **Step 13.2: Test laufen (FAIL)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/InstallLockTest.php --no-coverage
```

- [ ] **Step 13.3: Fix in `install_deu.php`**

Am Anfang der Datei, nach `declare(strict_types=1);` und vor `?>` (ca. Zeile 13), ergänzen:

```php
declare(strict_types=1);

// BUG-011: Lock-File-Check. Nach erfolgreicher Installation wird '.installed'
// erstellt. Beim naechsten Aufruf: 403.
$lockFile = __DIR__ . '/.installed';
if (file_exists($lockFile)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Forbidden</title></head><body>';
    echo '<h1>403 - Installation already completed</h1>';
    echo '<p>PowerBook wurde bereits installiert. Loeschen Sie '
       . htmlspecialchars($lockFile, ENT_QUOTES, 'UTF-8')
       . ' manuell, falls Sie neu installieren moechten.</p>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
```

Am Ende des erfolgreichen Installationszweigs (nach dem Standard-Admin-Insert, also nach `echo " <span class='success'>Abgeschlossen.</span><br>";` des Admin-Block, ca. Zeile 188), ergänzen:

```php
        echo " <span class='success'>Abgeschlossen.</span><br>";

        // Lock-File erstellen (BUG-011).
        @file_put_contents(__DIR__ . '/.installed', date('c') . "\n");
```

- [ ] **Step 13.4: Tests laufen**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Integration/InstallLockTest.php --no-coverage
```

- [ ] **Step 13.5: Live-Verifikation**

```bash
# Simuliere: erst KEIN Lock, dann mit Lock
rm -f .installed 2>/dev/null
curl -s -o /dev/null -w "Ohne Lock: HTTP %{http_code}\n" "http://localhost:8080/install_deu.php"

touch .installed
curl -s -o /tmp/locktest.html -w "Mit Lock: HTTP %{http_code}\n" "http://localhost:8080/install_deu.php"
grep -oE "Installation already completed|Forbidden|bereits installiert" /tmp/locktest.html | head -1

# Cleanup
rm -f .installed
```

Expected:
- Ohne Lock: HTTP 200 (Install-Seite).
- Mit Lock: HTTP 403, "Installation already completed" sichtbar.

- [ ] **Step 13.6: .installed zu .gitignore hinzufügen**

In `.gitignore` ergänzen (falls nicht vorhanden):

```
# BUG-011 Lock-File
.installed
```

- [ ] **Step 13.7: Commit**

```bash
git add install_deu.php .gitignore tests/Integration/InstallLockTest.php
git commit -m "Fix: BUG-011 - install_deu.php mit Lock-File gegen Re-Install geschuetzt

Nach erfolgreicher Installation wird .installed geschrieben. Beim
nächsten Aufruf gibt install_deu.php HTTP 403 zurueck."
```

---

## BUG-012: coverage_report.php Parse Error

### Task 14: BUG-012 — Backslash-Escape in single-quoted String fixen

**Files:**
- Modify: `coverage_report.php` (Zeile 11, 23)
- Test: `tests/Unit/CoverageReportSyntaxTest.php` (NEU)

- [ ] **Step 14.1: Test schreiben**

Create `tests/Unit/CoverageReportSyntaxTest.php`:

```php
<?php
declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CoverageReportSyntaxTest extends TestCase
{
    public function testCoverageReportHasValidPhpSyntax(): void
    {
        $output = [];
        $code = 0;
        exec('php -l ' . escapeshellarg(POWERBOOK_ROOT . '/coverage_report.php') . ' 2>&1', $output, $code);
        $this->assertSame(0, $code, 'coverage_report.php muss syntaktisch valide sein: ' . implode("\n", $output));
    }
}
```

- [ ] **Step 14.2: Test laufen (FAIL)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/CoverageReportSyntaxTest.php --no-coverage
```

Expected: FAIL — Parse error.

- [ ] **Step 14.3: Fix in `coverage_report.php`**

Zeilen 11 und 23:

Alt:

```php
    $name = str_replace('D:\restricted\powerscripts.org\PowerBook\', '', (string)$file['name']);
```

Neu (double-quoted, kein Problem mit `\P`):

```php
    $name = str_replace('D:\\restricted\\powerscripts.org\\PowerBook\\', '', (string) $file['name']);
```

Begründung: In single-quoted strings wird `\'` als Escape gelesen, sodass das finale `\'` den String-Terminator maskiert. Doppelter Backslash in double-quoted oder single-quoted (`'\\'`) löst das Problem.

- [ ] **Step 14.4: Test läuft (PASS)**

```bash
docker exec powerbook_web vendor/bin/phpunit tests/Unit/CoverageReportSyntaxTest.php --no-coverage
```

- [ ] **Step 14.5: Live-Verifikation**

```bash
curl -s -o /tmp/cov.html -w "HTTP: %{http_code}\n" "http://localhost:8080/coverage_report.php"
head -5 /tmp/cov.html
```

Expected: HTTP 200 (oder 500 wegen fehlendem `coverage.xml` in der Ziel-Umgebung, was aber ein Laufzeit-Problem wäre, nicht Parse). Wichtig: **Kein Parse-Error** im Log.

```bash
tail -3 logs/error.log | grep -c "Parse error"
```

Expected: 0.

- [ ] **Step 14.6: Commit**

```bash
git add coverage_report.php tests/Unit/CoverageReportSyntaxTest.php
git commit -m "Fix: BUG-012 - coverage_report.php Parse Error (single-quoted Backslash)

Der abschliessende Backslash im Windows-Pfad '...PowerBook\' wurde
als \\' interpretiert (Escape des terminierenden Quote). Jetzt doppel-
backslash verwendet."
```

---

## Abschluss

### Task 15: Finale Gesamt-Verifikation

**Files:** nur Tests + Commit

- [ ] **Step 15.1: Alle PHPUnit-Tests**

```bash
docker exec powerbook_web vendor/bin/phpunit --no-coverage 2>&1 | tail -15
```

Expected: Alle Tests grün. Neue Count = Baseline + mindestens 14 neue Tests (aus Task 1-14).

- [ ] **Step 15.2: PHPStan**

```bash
docker exec powerbook_web vendor/bin/phpstan analyse --memory-limit=512M 2>&1 | tail -10 || true
```

Expected: Keine neuen Errors. (Falls vorher schon Errors existierten: gleiche Anzahl, keine zusätzlichen.)

- [ ] **Step 15.3: PHP-CS-Fixer Dry-Run**

```bash
docker exec powerbook_web vendor/bin/php-cs-fixer fix --dry-run --diff 2>&1 | tail -20 || true
```

Expected: keine Code-Style-Verstöße in den geänderten Dateien (oder nur die Vorbefunde).

- [ ] **Step 15.4: Bug-Report aktualisieren — Status auf "Behoben" ändern**

Öffne `docs/2026-04-23-Userbereichs-bugs.md` und ergänze am Anfang jedes Bug-Eintrags (unter der Überschrift):

```markdown
> **STATUS 2026-04-23:** ✅ Behoben in Task NN des Plans `docs/superpowers/plans/2026-04-23-userbereich-bugs-fix.md`. Siehe Commit-History für Details.
```

Die Statusreihe in der Testabdeckung (`docs/2026-04-23-Userbereichs-test-coverage.md` Abschnitt 4.2) entsprechend als "FIXED" markieren.

- [ ] **Step 15.5: Finaler Commit**

```bash
git add docs/2026-04-23-Userbereichs-bugs.md docs/2026-04-23-Userbereichs-test-coverage.md
git commit -m "Docs: Audit-Dokumentation aktualisiert - alle 14 Bugs als FIXED markiert"
```

- [ ] **Step 15.6: Abschlussbericht in README ankündigen (optional)**

(Nur wenn im Repo ein CHANGELOG existiert.) Ggf. Eintrag ergänzen — sonst überspringen.

---

## Reihenfolge-Logik (für Subagent-Driven-Development)

| Reihenfolge | Task | Grund |
|-------------|------|-------|
| 1 | Task 0 (Baseline) | Testlauf-Snapshot |
| 2 | Task 1 (BUG-002) | Entblockiert weitere Live-Tests des Guestbooks |
| 3 | Task 2 (BUG-006) | Entblockiert Admin-CRUD-Live-Tests |
| 4 | Task 3 (BUG-014) | Security-Grundlage |
| 5 | Task 4 (BUG-001) | Datenintegrität — vor anderen Preview-Fixes |
| 6 | Task 5 (BUG-003) | Baut auf Task 4 auf (Roh-Variable-Konzept) |
| 7 | Task 6 (BUG-007) | Kleiner Render-Fix |
| 8 | Task 7 (BUG-005) | Kleiner UI-Fix |
| 9 | Task 8 (BUG-004) | Routing-Bereinigung |
| 10 | Task 9 (BUG-013) | Braucht PB_ENTRY; keine Abhängigkeit zu CRUD-Pfaden |
| 11 | Task 10 (BUG-008) | Unabhängige Validierungs-Ergänzung |
| 12 | Task 11 (BUG-009) | Vorstufe für Task 12 |
| 13 | Task 12 (BUG-010) | Baut auf Task 11 auf |
| 14 | Task 13 (BUG-011) | Unabhängig |
| 15 | Task 14 (BUG-012) | Unabhängig |
| 16 | Task 15 (Abschluss) | Zusammenfassung |

---

## Testkommandos-Referenz

```bash
# Unit-Suite
docker exec powerbook_web vendor/bin/phpunit --testsuite Unit --no-coverage

# Integration-Suite
docker exec powerbook_web vendor/bin/phpunit --testsuite Integration --no-coverage

# Einzeltest
docker exec powerbook_web vendor/bin/phpunit tests/Unit/FILE.php --no-coverage

# PHPStan
docker exec powerbook_web vendor/bin/phpstan analyse --memory-limit=512M

# Code-Style
docker exec powerbook_web vendor/bin/php-cs-fixer fix --dry-run --diff
```
