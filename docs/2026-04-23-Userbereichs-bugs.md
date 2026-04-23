# PowerBook Userbereich - Bug-Report
**Datum:** 2026-04-23
**Tester:** Senior-QA-Engineer (Claude)
**Testumgebung:** http://localhost:8080 (Admin-Center unter /pb_inc/admincenter/index.php)

> Diese Datei enthält **nur Bugs**. Workflow-/UX-Hinweise siehe `2026-04-23-Userbereichs-improvements.md`.
>
> **Update 2026-04-23 — Fix-Durchlauf:** Alle 14 Bugs wurden gemäß dem Plan
> `docs/superpowers/plans/2026-04-23-userbereich-bugs-fix.md` behoben.
> Jeder Eintrag ist mit **✅ BEHOBEN** markiert und verlinkt auf den Commit/Test.
> 511 PHPUnit-Tests grün (29 neue Tests); siehe `git log` seit `854956b`.

---

## Bug-Liste

### BUG-001: Homepage-URL wird als kompletter HTML-Tag in Datenbank gespeichert

> ✅ **BEHOBEN** (Commit 6008163) — siehe PHPUnit-Tests.
- **Bereich:** Gästebuch-Eintrag (Frontend) / Admin-Einträge
- **URL / Route:** `/pbook.php`, `/pb_inc/admincenter/index.php?page=entries`
- **Reproduktionsschritte:**
  1. Im Gästebuch-Frontend einen Eintrag mit Homepage `sdfsd` (vor langer Zeit angelegt) wurde erstellt
  2. Admin-Bereich `?page=entries` aufrufen
  3. Die Homepage-Link-Zelle des Eintrags inspizieren
- **Erwartet:** `<small><a href="http://sdfsd" target="_blank">Homepage</a></small>`
- **Tatsächlich:** `<small><a href="http://&lt;small&gt;&lt;a href=&quot;http://sdfsd&quot; target=&quot;_blank&quot; rel=&quot;noopener noreferrer&quot;&gt;Homepage&lt;/a&gt;&lt;/small&gt;" target="_blank">Homepage</a></small>` — der Homepage-Wert enthält bereits das gerenderte HTML, das wird erneut als href-Präfix verwendet
- **Fehlerart:** Datenintegrität + doppelte Speicherung. Der Datenbank-Wert `entry.homepage` enthält kompletten `<small><a>…</a></small>` HTML. Die Template-Logik in `pb_inc/admincenter/entry.inc.php:70-79` prefixt `http://`, anschliessend escapt `e()` die Tags — der Link-href wird zu `http://&lt;small&gt;…`.
- **Ursache im Code:**
  - `pb_inc/entry.inc.php:73-80` setzt die Variable `$url` auf den kompletten gerenderten HTML-Link (`<small><a href="…">Homepage</a></small>`).
  - `pb_inc/guestbook.inc.php:214` inkludiert `entry.inc.php` **innerhalb der Preview-Logik** — damit wird die zuvor gesetzte Variable `$url` (Anfang Zeile 32 aus `$_POST['url']`) überschrieben.
  - `pb_inc/guestbook.inc.php:223` nutzt dann `e($url)` als Wert für das Hidden-Field `url2`. Dort steht jetzt das gerenderte HTML.
  - Nach Klick auf "Eintragen!" wird `$url2 = $_POST['url2']` (Zeile 41) in die DB geschrieben (Zeile 319 `:homepage => $url2`).
  - Ergebnis: `powerbook_entries.homepage` enthält den kompletten HTML-String anstelle der Rohurl.
- **Schweregrad:** Mittel (Datenkorruption, gebrochener Link) — XSS-Risiko gering durch `e()`, aber Persistenz inkonsistent.
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** HTTP 200 OK, reguläres Rendering
- **Persistenz:** Ja, Wert ist in der MySQL-Tabelle `powerbook_entries.homepage` persistiert.
- **Raw HTML Auszug:**
  ```html
  <small><a href="http://&lt;small&gt;&lt;a href=&quot;http://sdfsd&quot; target=&quot;_blank&quot; rel=&quot;noopener noreferrer&quot;&gt;Homepage&lt;/a&gt;&lt;/small&gt;" target="_blank" rel="noopener noreferrer">Homepage</a></small>
  ```
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-002: PHP Fatal Error bei CSRF-Fehler im Gästebuch-Formular (kritisch)

> ✅ **BEHOBEN** (Commit 62d8e8b) — siehe PHPUnit-Tests.
- **Bereich:** Gästebuch-Frontend, Preview & Eintrag-Speichern
- **URL / Route:** `POST /pbook.php`
- **Reproduktionsschritte:**
  1. In einer Browser-/Curl-Session frisch `/pbook.php` aufrufen, oder Session verwerfen.
  2. Einen POST auf `/pbook.php` mit `preview=yes&name=X&text=Y` **ohne gültigen `csrf_token`** senden.
  3. Antwort beobachten (Body wird abgeschnitten).
- **Erwartet:** Formular wird wieder angezeigt mit Fehlermeldung "CSRF-Token ungültig. Bitte die Seite neu laden."
- **Tatsächlich:** PHP Fatal Error, Antwort wird abgebrochen. Der Output endet nach dem Entry-List-Block (ca. 2301 Byte) ohne Fehlermeldung, Formular fehlt, `</body></html>` fehlt.
- **Fehlerart:** PHP-Fatal (undefinierte Funktion). Führt zu Response-Truncation und fehlender Fehleranzeige.
- **Schweregrad:** **Hoch / Kritisch**. Jeder Eintrags-Versuch mit ungültigem/fehlendem CSRF-Token (z. B. abgelaufene Session oder Session-Wechsel) bricht die Seite ab.
- **Konsole / Stacktrace:** aus `logs/error.log`:
  ```
  PHP Fatal error:  Uncaught Error: Call to undefined function logCsrfFailure() in /var/www/html/pb_inc/guestbook.inc.php:178
  Stack trace:
  #0 /var/www/html/pbook.php(55): include()
  #1 {main}
    thrown in /var/www/html/pb_inc/guestbook.inc.php on line 178
  ```
- **Netzwerkhinweise:** HTTP-Statuscode ist weiterhin 200, aber Content-Length passt nicht zum unvollständigen Body.
- **Persistenz:** Fehler wird jeder ungültigen Submission neu ausgelöst (persistent reproduzierbar).
- **Ursache im Code:**
  - `pb_inc/guestbook.inc.php` inkludiert nur `config.inc.php` und `functions.inc.php` (siehe Zeilen 15-16).
  - `logCsrfFailure()` ist in `pb_inc/error-handler.inc.php:113` definiert, wird aber hier nicht inkludiert.
  - `pb_inc/guestbook.inc.php:178` und `:278` rufen `logCsrfFailure()` trotzdem auf.
  - `admincenter/index.php:20` inkludiert `error-handler.inc.php` korrekt — Admin-Center ist nicht betroffen.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-003: Doppelte HTML-Escape der Eingabewerte in der Preview-Anzeige

> ✅ **BEHOBEN** (Commit 6008163) — siehe PHPUnit-Tests.
- **Bereich:** Gästebuch-Preview (POST mit `preview=yes`)
- **URL / Route:** `POST /pbook.php`
- **Reproduktionsschritte:**
  1. Normalen Gästebuch-Eintrag mit Name `<script>alert(1)</script>` und gültigem CSRF-Token & gültigen Feldern absenden.
  2. Preview-Antwort inspizieren (Raw HTML).
- **Erwartet:** Name wird einmal HTML-escapt (z.B. `&lt;script&gt;alert(1)&lt;/script&gt;`) im Preview-Block dargestellt.
- **Tatsächlich:** Name wird doppelt escapt (`&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;`). Der Preview zeigt dem Benutzer `&lt;script&gt;alert(1)&lt;/script&gt;` als sichtbaren Text (Entities als Text), statt der ursprünglichen Benutzereingabe.
- **Fehlerart:** Logikfehler — doppeltes HTML-Escaping im Preview-Pfad.
- **Schweregrad:** Niedrig bis Mittel (kein Security-Risiko, aber Preview zeigt falsche Darstellung. Nach dem tatsächlichen Speichern via `url2`/`name2`-Pfad wird der Wert ebenfalls doppelt escapt in DB geschrieben, siehe Persistenz).
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** keine Auffälligkeit
- **Persistenz:** Ja — die Hidden-Fields `name2`, `url2`, `icq2`, `text2` enthalten bereits `e()`-escapte Werte, werden dann bei Submission nochmal durch `e()` gejagt und so doppelt-escapt in DB gespeichert.
- **Ursache im Code:**
  - `pb_inc/guestbook.inc.php:194-198` escapen `$name = e($name)`, `$url = e($url)` usw. VOR der Preview-Anzeige.
  - `pb_inc/guestbook.inc.php:221-227` setzen dann Hidden-Fields mit `e($name)`, `e($url)` — also doppelte Anwendung von `e()`.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-004: Admin-Panel-Pseudoseiten rendern leere Content-Area

> ✅ **BEHOBEN** (Commit cd9b742) — siehe PHPUnit-Tests.
- **Bereich:** Admin-Center Routing
- **URL / Route:** `/pb_inc/admincenter/index.php?page=emails`, `?page=pages`, `?page=empty`
- **Reproduktionsschritte:**
  1. Als Admin einloggen.
  2. Eine der URLs `?page=emails`, `?page=pages` oder `?page=empty` direkt aufrufen.
- **Erwartet:** Entweder Weiterleitung oder klare Meldung (404/"Seite nicht gefunden" oder Zugriff verboten) — diese Seiten sind keine echten Admin-Views.
- **Tatsächlich:** Seite rendert ohne Content-Bereich. Nur Kopf-/Fußzeile wird angezeigt. Kein Hinweis dass die Seite kein Ziel hat.
  - `emails.inc.php` ist laut Kommentar "kept for backward compatibility" / "deprecated".
  - `pages.inc.php` ist ein **Paginierungs-Helper** — nicht eigenständig nutzbar, liefert bei direktem Aufruf nichts (weil `$tmp_pages < 1`).
  - `empty.inc.php` ist ein Platzhalter (`&nbsp;`).
- **Fehlerart:** Routing-Whitelist enthält Nicht-Seiten.
- **Schweregrad:** Niedrig (kosmetisch, verwirrend für Admin). Keine Security-Gefahr.
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** HTTP 200, Body ~2447 Byte
- **Persistenz:** Routen sind dauerhaft über `$allowedPages` in `pb_inc/admincenter/index.php:24` aufrufbar.
- **Ursache im Code:**
  - `$allowedPages` (Zeile 24-28) enthält `pages` und `emails` obwohl diese keine Admin-Views sind.
  - `$allowedPages` enthält `empty` als Platzhalter, das ebenfalls keinen Content liefert.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-006: PHP Fatal Error beim Hinzufügen/Bearbeiten/Löschen von Admins (kritisch)

> ✅ **BEHOBEN** (Commit 6008163) — siehe PHPUnit-Tests.
- **Bereich:** Admin-Center — Admin-Verwaltung (add/edit/delete)
- **URL / Route:** `POST /pb_inc/admincenter/index.php?page=admins` mit `action=add`, `action=edit`, `action=delete`
- **Reproduktionsschritte:**
  1. Als Admin einloggen.
  2. Unter `?page=admins` einen neuen Admin mit gültigen Daten hinzufügen (Name, Mail, mind. eine Berechtigung).
  3. Antwort ansehen; danach `?page=admins` erneut laden.
- **Erwartet:** Erfolgsmeldung "Admin erfolgreich hinzugefügt. Er wird eine E-Mail mit seinen Daten erhalten." und Admin in der Liste.
- **Tatsächlich:**
  - Antwort wird vorzeitig abgeschnitten (Body ca. 2145 Byte statt ~7000). Keine Erfolgs-/Fehlermeldung erscheint, keine Admin-Liste.
  - Der Admin wird in der Datenbank angelegt (Nebenwirkung erfolgt), aber UI zeigt keine Bestätigung.
  - E-Mail wird nicht versandt (Fatal vor Send).
- **Fehlerart:** PHP Fatal Error (undefinierte Funktion).
- **Schweregrad:** **Hoch / Kritisch**. Jeder CRUD-Vorgang für Admins ist betroffen. Daten-Inkonsistenz (DB-Insert ok, UI sagt nichts, keine E-Mail → temporäres Passwort wird nicht an neuen Admin kommuniziert → Self-Lockout möglich).
- **Konsole / Stacktrace:** aus `logs/error.log`:
  ```
  PHP Fatal error:  Uncaught Error: Call to undefined function sendAdminEmail() in /var/www/html/pb_inc/admincenter/admins.inc.php:85
  Stack trace:
  #0 /var/www/html/pb_inc/admincenter/index.php(280): include()
  #1 {main}
    thrown in /var/www/html/pb_inc/admincenter/admins.inc.php on line 85
  ```
  Betroffen sind alle drei Aufrufe: Zeilen 85 (add), 149 (delete), 229 (edit).
- **Netzwerkhinweise:** HTTP 200, Body abgeschnitten
- **Persistenz:** Ja — DB-INSERT/UPDATE/DELETE läuft durch, nur E-Mail-Versand & Response-Nachbereitung crashen.
- **Ursache im Code:**
  - `pb_inc/admincenter/admins.inc.php:292` definiert `function sendAdminEmail(...)` **innerhalb** eines `if (!function_exists('sendAdminEmail')) { ... }`-Blocks.
  - PHP hebt (hoistet) Funktionen **nur** auf, wenn sie im Root-Scope deklariert sind. Bedingte Deklarationen (`if (...) { function foo() {} }`) werden **erst** zur Laufzeit registriert, sobald der `if`-Zweig erreicht wird.
  - Die Aufrufe auf Zeilen 85/149/229 erfolgen **bevor** die `if`-Zweigdefinition auf Zeile 292 erreicht wird → Funktion ist noch nicht registriert → Fatal Error.
- **Bestätigung:** Die DB-Spalte `powerbook_admin` enthält nach dem Test einen neuen Admin "TestAdmin" — Insert ok, aber E-Mail & Userfeedback fehlen.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-007: "Keine passenden Einträge"-Meldung wird als HTML-Text mit Entities statt als Link angezeigt

> ✅ **BEHOBEN** (Commit 9e9bb9f) — siehe PHPUnit-Tests.
- **Bereich:** Gästebuch-Suche im Frontend
- **URL / Route:** `/pbook.php?tmp_where=name&tmp_search=<etwas_was_nicht_existiert>`
- **Reproduktionsschritte:**
  1. `/pbook.php?tmp_where=name&tmp_search=doesnotexist123` aufrufen.
  2. Anstelle eines klickbaren "Keine passenden Einträge"-Links erscheint der HTML-Code als escape-Text.
- **Erwartet:** Klickbarer Link "Keine passenden Einträge" (zurück zur Vorseite).
- **Tatsächlich:** User sieht im Body: `<a href="javascript:history.back()">Keine passenden Einträge.</a>` — wörtlich als Text, HTML-Entities sichtbar.
- **Fehlerart:** Doppelte HTML-Escape — `$message` enthält HTML-Markup, wird aber mit `e($message)` ausgegeben.
- **Schweregrad:** Niedrig (UX)
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** HTTP 200
- **Persistenz:** N/A (rein Render-Logik)
- **Ursache im Code:**
  - `pb_inc/guestbook.inc.php:123` setzt `$message = '<a href="javascript:history.back()">Keine passenden Einträge.</a>';` (HTML).
  - `pb_inc/guestbook.inc.php:147` gibt aus: `echo '<div align="center"><b>' . e($message) . '</b></div>';` — `e()` escapt das HTML-Markup.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-008: Keine serverseitige Längenbegrenzung für Name, Text und weitere Felder

> ✅ **BEHOBEN** (Commit 04d398f) — siehe PHPUnit-Tests.
- **Bereich:** Gästebuch-Formular, Admin Entry Edit
- **URL / Route:** `POST /pbook.php`, `POST /pb_inc/admincenter/index.php?page=edit`
- **Reproduktionsschritte:**
  1. Mittels `curl` (oder modifiziertem Form via DevTools) eine POST-Anfrage mit `name=AAAAAA…×500`, `text=XXXXX…×10000` senden.
  2. Preview-/Eintrag-Pfad vollendet ohne Validierungsfehler.
- **Erwartet:** Serverseitige Längenvalidierung lehnt übermäßig lange Werte ab (Name > 100 Zeichen, Text > z. B. 5000 Zeichen).
- **Tatsächlich:** Client-seitige `maxlength="100"`/`maxlength="250"` wird nicht serverseitig erzwungen. Strings mit 500+ bzw. 10000+ Zeichen werden akzeptiert.
- **Fehlerart:** Missing input validation (Client-side only).
- **Schweregrad:** Mittel. Gefahr: Datenbank-DoS (Spam-Einträge mit sehr großen Texten), Layout-Bruch, Performance.
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** HTTP 200 auf alle Requests
- **Persistenz:** Jeder Submission kann die DB mit überlangen Werten fluten (nur durch MySQL-Datentyp-Limits begrenzt).
- **Ursache im Code:**
  - `pb_inc/guestbook.inc.php:180-188` validiert nur `strlen(trim($name)) === 0`, `strlen(trim($text)) === 0`, aber keine Maximallängen.
  - `pb_inc/admincenter/edit.inc.php` validiert ebenfalls keine Maximallängen.
  - `pb_inc/validation.inc.php` enthält zwar Helfer (prüfen), aber diese werden nicht genutzt.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-009: Password-Recovery bestätigt Existenz eines Admin-Namens (User Enumeration)

> ✅ **BEHOBEN** (Commit 7bf2cff) — siehe PHPUnit-Tests.
- **Bereich:** Admin-Center — Passwort vergessen
- **URL / Route:** `POST /pb_inc/admincenter/index.php?page=password`
- **Reproduktionsschritte:**
  1. `?page=password` aufrufen.
  2. Recovery-Formular mit beliebigem Namen (z. B. `NonExistentAdmin`) absenden.
  3. Antwort liest: "Admin in Datenbank nicht gefunden!".
  4. Recovery mit existierendem Namen `PowerBook` liefert Erfolgsmeldung.
- **Erwartet:** Gleiche, generische Antwort für beide Fälle (z. B. "Falls ein Konto zu diesem Namen existiert, wurde eine E-Mail versandt.") — verhindert User Enumeration.
- **Tatsächlich:** Unterschiedliche Response-Texte decken auf, ob ein Admin-Name existiert. Außerdem ohne Rate-Limit einfach zu enumerieren.
- **Fehlerart:** Security / User Enumeration
- **Schweregrad:** Mittel. Ermöglicht Admin-Namen zu finden, die dann als Brute-Force-Vektor dienen.
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** HTTP 200
- **Persistenz:** N/A
- **Ursache im Code:**
  - `pb_inc/admincenter/password.inc.php` (gesehen in früherem Auszug): `if (!$admin) { $message = 'Admin in Datenbank nicht gefunden!'; }` — zeigt Nicht-Existenz direkt an.
  - Zusätzlich kein Rate-Limit, kein CAPTCHA.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-010: Password-Recovery setzt Passwort sofort zurück — keine E-Mail-Bestätigung

> ✅ **BEHOBEN** (Commit 7bf2cff) — siehe PHPUnit-Tests.
- **Bereich:** Admin-Center — Passwort vergessen
- **URL / Route:** `POST /pb_inc/admincenter/index.php?page=password`
- **Reproduktionsschritte:**
  1. Als beliebiger Angreifer `?page=password` mit Admin-Name senden.
  2. Das alte Passwort ist SOFORT ungültig und durch ein neues, per E-Mail versendetes, ersetzt.
- **Erwartet:** Der User erhält einen zeitlich begrenzten Recovery-Link ("Klicken Sie diesen Link innerhalb von 24 h, um Ihr Passwort zurückzusetzen"). Ohne Klick: altes Passwort bleibt gültig.
- **Tatsächlich:** Das Passwort wird direkt in der DB überschrieben. Alte Passwörter sofort ungültig. Dies ermöglicht **Admin-Denial-of-Service** durch kontinuierliches Auslösen (Nutzer wird immer wieder aus dem System geworfen).
- **Fehlerart:** Unsicherer Passwort-Recovery-Flow
- **Schweregrad:** Mittel bis Hoch (DoS gegen Admin-Accounts möglich)
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** HTTP 200; Mailpit erhält Mail mit neuem Passwort
- **Persistenz:** Ja — DB-Update des `password`-Feldes.
- **Ursache im Code:**
  - `pb_inc/admincenter/password.inc.php:51-52`: `UPDATE ${pb_admin} SET password = ? WHERE id = ?` — passwort direkt geändert.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-011: install_deu.php ist öffentlich erreichbar (Sicherheitsrisiko)

> ✅ **BEHOBEN** (Commit 2910e8a) — siehe PHPUnit-Tests.
- **Bereich:** Root / Installation
- **URL / Route:** `GET /install_deu.php`
- **Reproduktionsschritte:**
  1. Nicht eingeloggt, ohne Admin-Session `http://localhost:8080/install_deu.php` aufrufen.
  2. Seite liefert HTTP 200, zeigt Installations-Assistent inklusive Datenbank-Credentials in Klartext.
  3. Klick auf "Installation starten" würde Tabellen neu erstellen & Standard-Admin "PowerBook/powerbook" anlegen.
- **Erwartet:** Datei ist entweder gelöscht, per .htaccess geschützt, oder prüft ein Lock-File nach erfolgter Installation und verweigert den Zugriff.
- **Tatsächlich:** Jeder Besucher kann die Datei öffnen. Der Installations-Text empfiehlt zwar "Bitte löschen Sie diese Datei", das Löschen wird aber nicht technisch erzwungen.
- **Fehlerart:** Security / Exposure
- **Schweregrad:** **Hoch** (DoS durch Tabellen-Reset / Account-Takeover, Information Disclosure).
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** HTTP 200, 3071 Byte. DB-Credentials im HTML: `<b>Passwort:</b> powerbook_secret`
- **Persistenz:** Ja — Datei bleibt im Dateisystem bis manuelles Löschen.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-012: `coverage_report.php` hat PHP Parse Error

> ✅ **BEHOBEN** (Commit d35c609) — siehe PHPUnit-Tests.
- **Bereich:** Root
- **URL / Route:** `GET /coverage_report.php`
- **Reproduktionsschritte:**
  1. `http://localhost:8080/coverage_report.php` aufrufen.
- **Erwartet:** Eine Coverage-Ausgabe oder 404.
- **Tatsächlich:** HTTP 500, Body 0 Bytes, PHP Parse Error im Log.
- **Fehlerart:** PHP Parse Error
- **Schweregrad:** Niedrig (Datei scheint Dev-Utility zu sein; aber sie ist öffentlich deploybar).
- **Konsole / Stacktrace:** aus `logs/error.log`:
  ```
  PHP Parse error:  syntax error, unexpected single-quoted string ", (string)$file[", expecting ")" in /var/www/html/coverage_report.php on line 11
  ```
- **Netzwerkhinweise:** HTTP 500
- **Persistenz:** Syntax-Fehler, dauerhaft.
- **Ursache im Code:**
  - `coverage_report.php:14`: `$name = str_replace('D:\restricted\powerscripts.org\PowerBook\', '', (string)$file['name']);`
  - Single-Quoted-String mit Backslash `\P` → Parser interpretiert `\'` als Escape, String endet nicht korrekt.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-013: Gästebuch-Include direkt aufrufbar (`/pb_inc/guestbook.inc.php`) rendert Content ohne Wrapper

> ✅ **BEHOBEN** (Commit 65989e3) — siehe PHPUnit-Tests.
- **Bereich:** Gästebuch-Logik
- **URL / Route:** `GET /pb_inc/guestbook.inc.php`
- **Reproduktionsschritte:**
  1. `http://localhost:8080/pb_inc/guestbook.inc.php` aufrufen.
- **Erwartet:** Datei sollte nicht direkt erreichbar sein — ein Guard wie `if (!defined('PB_ENTRY')) { exit; }` sollte Direktaufruf blocken.
- **Tatsächlich:** Die Datei wird ausgeführt und rendert ~5311 Byte Gästebuch-HTML (ohne die pbook.php-Wrapper-Struktur). Das zeigt die Einträge, ohne ordentlich eingebettet zu sein.
- **Fehlerart:** Design-/Sicherheitsbest Practice
- **Schweregrad:** Niedrig bis Mittel — kein Credential-Leak, aber unnötige Exposure der Include-Datei.
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** HTTP 200, 5311 Byte
- **Persistenz:** Struktur-Fehler
- **Ursache im Code:** Kein Direct-Access-Guard am Anfang von `pb_inc/guestbook.inc.php`.
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-014: Session-ID wird beim Login nicht regeneriert (Session Fixation)

> ✅ **BEHOBEN** (Commit 1ff79d6) — siehe PHPUnit-Tests.
- **Bereich:** Admin Login / Session-Management
- **URL / Route:** `POST /pb_inc/admincenter/index.php?page=login`
- **Reproduktionsschritte:**
  1. Fresh-Session erzeugen: `GET /pb_inc/admincenter/?page=login` → Server setzt `PHPSESSID=<OLD_SID>`.
  2. Mit `OLD_SID` einen Login-POST ausführen (gültige Credentials).
  3. Nach erfolgreichem Login ist die `PHPSESSID` **identisch** mit `OLD_SID`.
- **Erwartet:** Nach erfolgreichem Login muss `session_regenerate_id(true)` aufgerufen werden, damit die Session-ID gewechselt wird.
- **Tatsächlich:** Session-ID bleibt identisch über Login-Grenze hinweg.
- **Fehlerart:** Security / Session Fixation
- **Schweregrad:** **Mittel bis Hoch**. Angreifer kann per Link/URL-Manipulation einem Admin eine bekannte Session-ID unterschieben; sobald der Admin sich einloggt, hat der Angreifer gültige authentifizierte Session.
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** `Set-Cookie`-Header im Login-Response enthält keinen neuen PHPSESSID-Wert.
- **Persistenz:** N/A
- **Ursache im Code:**
  - `pb_inc/admincenter/index.php` Login-Handler (Zeilen ~70-100): ruft nach erfolgreichem Login `regenerateCsrfToken()` auf, aber **nicht** `session_regenerate_id(true)`.
- **Test-Output:**
  ```
  Session ID before login: 41cb6851d04ee5b...
  Session ID after login:  41cb6851d04ee5b...
  ⚠️ Session NOT regenerated (fixation vulnerability)
  ```
- **Status:** Offen
- **Nicht beheben.**

---

### BUG-005: `edit` und `statement` Seiten ohne ID-Parameter zeigen "javascript:history.back()"-Link

> ✅ **BEHOBEN** (Commit 98454af) — siehe PHPUnit-Tests.
- **Bereich:** Admin-Center: Eintrag bearbeiten, Statement schreiben
- **URL / Route:** `/pb_inc/admincenter/index.php?page=edit` (ohne edit_id); `?page=statement` (ohne id)
- **Reproduktionsschritte:**
  1. Als Admin einloggen.
  2. `?page=edit` direkt aufrufen.
- **Erwartet:** Entweder Liste der Einträge zur Auswahl, oder saubere Fehlermeldung "Bitte Eintrag auswählen" mit Link zurück zu `?page=entries`.
- **Tatsächlich:** Es wird ein Link mit `href="javascript:history.back()"` angezeigt: "Fehler: ID unbekannt!" bzw. "Es ist ein Fehler aufgetreten: ID unbekannt!". Bei direktem Aufruf (keine History) führt der Link nirgendwo hin.
- **Fehlerart:** UX/Navigation, schlechte Fehlerbehandlung
- **Schweregrad:** Niedrig
- **Konsole / Stacktrace:** keine Fehler
- **Netzwerkhinweise:** HTTP 200
- **Persistenz:** N/A
- **Ursache im Code:**
  - `pb_inc/admincenter/edit.inc.php:37-39`: `$message = '<a href="javascript:history.back()">Fehler: <b>ID unbekannt!</b></a>';`
  - `pb_inc/admincenter/statement.inc.php:35-37`: dito.
- **Status:** Offen
- **Nicht beheben.**

---

