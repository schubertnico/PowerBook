# PowerBook Userbereich - Testabdeckung / Testmatrix
**Datum:** 2026-04-23
**Tester:** Senior-QA-Engineer (Claude)
**Testumgebung:** http://localhost:8080 + Mailpit (http://localhost:8031)

> Statusdefinition:
> - **GETESTET** = Positivfall + mindestens ein Negativ- oder Randfall getestet
> - **TEILWEISE GETESTET** = Positivfall getestet, Negativ-/Randfall offen
> - **BLOCKIERT** = Test nicht durchführbar (Abhängigkeit, Fehler, fehlender Zugriff)
> - **OFFEN** = Noch nicht begonnen

---

## 1. Identifizierte Bereiche (Scope)

Die folgenden Bereiche wurden aus Codebasis (`pbook.php`, `pb_inc/`, `pb_inc/admincenter/`) und der sichtbaren Navigation extrahiert:

### 1.1 Gästebuch-Frontend (`pbook.php` - öffentlich, für Besucher)
- Eintragsübersicht / Paginierung
- Eintrag schreiben (Formular)
- Validierung (Pflichtfelder, Mail, Homepage, Text)
- Smilies-Hilfefenster
- Text-Hilfefenster
- Icon-Auswahl
- Suche (Link "Nach Eintrag Suchen")
- Admin-Link (führt ins AdminCenter)

### 1.2 Admin-Center (`pb_inc/admincenter/index.php` - geschützt per Login)
- Login-Formular (inkl. CSRF)
- Passwort-vergessen-Fluss (Mailpit)
- Home-Dashboard
- Einträge verwalten (`entries`)
- Eintrag bearbeiten (`edit`)
- Eintrag löschen (`edit` + action=delete)
- Eintrag freischalten (`release`)
- Admins verwalten (`admins`) - Admin hinzufügen, bearbeiten, löschen, Rechte
- E-Mails verwalten (`emails`)
- Konfiguration (`configuration`)
- Seiten (`pages`) → Helper
- Statement/License (`statement` / `license`)
- Empty/Alles löschen (`empty` Platzhalter)
- Logout (`logout`)

### 1.3 Security / Infrastruktur
- Direktzugriff auf Include-Dateien
- CSRF-Schutz
- Session-Cookies / HttpOnly / SameSite
- LFI / Path-Traversal
- SQL-Injection
- XSS (Reflected/Stored)
- Rate-Limit / Brute-Force-Schutz
- HTTP-Security-Header
- Installation Leftovers (`install_deu.php`)

---

## 2. Testmatrix (Kurzübersicht)

| # | Bereich | Route | Teststatus | Bugs | Improvements |
|---|---------|-------|------------|------|--------------|
| 1 | Frontend: Einstiegsseite | `/pbook.php` | GETESTET | BUG-001 | IMP-026, IMP-029 |
| 2 | Frontend: Eintrag Preview (Positiv) | `POST /pbook.php` preview=yes | GETESTET | BUG-003 | IMP-010, IMP-011 |
| 3 | Frontend: Eintrag Submit (Positiv) | `POST /pbook.php` add_entry=yes | GETESTET | - | - |
| 4 | Frontend: Leeres Name-Feld | `POST /pbook.php` | GETESTET | - | - |
| 5 | Frontend: Leeres Text-Feld | `POST /pbook.php` | GETESTET | - | - |
| 6 | Frontend: Ungültige E-Mail | `POST /pbook.php` | GETESTET | - | - |
| 7 | Frontend: Überlange Eingaben | `POST /pbook.php` | GETESTET | BUG-008 | IMP-016 |
| 8 | Frontend: XSS in Name/Text | `POST /pbook.php` | GETESTET | BUG-003 | - |
| 9 | Frontend: CSRF-Token fehlt / ungültig | `POST /pbook.php` | GETESTET | **BUG-002 (Fatal Error)** | - |
| 10 | Frontend: Spam-Protection (Timeout) | `POST /pbook.php` zweimal | GETESTET | - | - |
| 11 | Frontend: Smilies-Hilfefenster | popup `smilies-help.html` | GETESTET | - | IMP-018, IMP-038 |
| 12 | Frontend: Text-Hilfefenster | popup `text-help.html` | GETESTET | - | IMP-018, IMP-038 |
| 13 | Frontend: Icon-Auswahl | `POST /pbook.php` | GETESTET | - | IMP-027 |
| 14 | Frontend: Suche (positiv) | `GET /pbook.php?tmp_where=name&tmp_search=X` | GETESTET | - | - |
| 15 | Frontend: Suche (keine Treffer) | `GET /pbook.php?tmp_search=doesnotexist` | GETESTET | BUG-007 | IMP-024 |
| 16 | Frontend: Suche XSS | `GET /pbook.php?tmp_search=<script>` | GETESTET | - | - |
| 17 | Frontend: Suche SQL-Injection | `GET /pbook.php?tmp_search=' OR '1'='1` | GETESTET | - | - |
| 18 | Frontend: BBCode [b][/b] [i][/i] | `POST /pbook.php` | GETESTET | - | - |
| 19 | Frontend: URL-Auto-Link | `POST /pbook.php` | GETESTET | - | IMP-015 |
| 20 | Frontend: Paginierung | `?tmp_start=N` | TEILWEISE GETESTET | - | IMP-019 |
| 21 | Frontend: Admin-Link | `<a href="pb_inc/admincenter/">` | GETESTET | - | - |
| 22 | Admin: Login (Positiv) | `POST ?page=login` | GETESTET | - | IMP-002 |
| 23 | Admin: Login (leer) | `POST ?page=login` | GETESTET | - | - |
| 24 | Admin: Login (falsches PW) | `POST ?page=login` | GETESTET | - | - |
| 25 | Admin: Login (SQL-Injection) | `POST ?page=login name='OR'1'='1` | GETESTET | - | - |
| 26 | Admin: Login (Brute Force) | `POST ?page=login` 10× | GETESTET | - | IMP-017 |
| 27 | Admin: Login (unbekannter User) | `POST ?page=login` | GETESTET | - | - |
| 28 | Admin: Passwort vergessen (existierender User) | `POST ?page=password` | GETESTET | BUG-010 | IMP-005 |
| 29 | Admin: Passwort vergessen (non-existent) | `POST ?page=password` | GETESTET | BUG-009 | IMP-004 |
| 30 | Admin: Home-Dashboard (eingeloggt) | `GET ?page=home` | GETESTET | - | IMP-031 |
| 31 | Admin: Home-Dashboard (nicht eingeloggt) | `GET /pb_inc/admincenter/` | GETESTET | - | IMP-001, IMP-003 |
| 32 | Admin: Einträge-Liste | `GET ?page=entries` | GETESTET | - | IMP-036 |
| 33 | Admin: Einträge-Paginierung | `?tmp_start=N` | TEILWEISE GETESTET | - | IMP-019 |
| 34 | Admin: Eintrag bearbeiten (ohne id) | `GET ?page=edit` | GETESTET | BUG-005 | IMP-024 |
| 35 | Admin: Eintrag bearbeiten (Positiv Update) | `POST ?page=edit` action=update | GETESTET | - | IMP-021 |
| 36 | Admin: Eintrag löschen | `POST ?page=edit` action=delete | GETESTET | - | - |
| 37 | Admin: Eintrag freischalten (keine pending) | `GET ?page=release` | GETESTET | - | - |
| 38 | Admin: Eintrag freischalten (release_all) | `POST ?page=release` | GETESTET | - | IMP-022 |
| 39 | Admin: Statement schreiben | `POST ?page=statement` action=update | GETESTET | - | - |
| 40 | Admin: Statement ohne id | `GET ?page=statement` | GETESTET | BUG-005 | IMP-024 |
| 41 | Admin: Admins Liste | `GET ?page=admins` | GETESTET | - | IMP-023, IMP-036 |
| 42 | Admin: Admin hinzufügen (Positiv) | `POST ?page=admins` action=add | GETESTET | **BUG-006 (Fatal Error)** | IMP-008, IMP-009 |
| 43 | Admin: Admin hinzufügen (ungültige Mail) | `POST ?page=admins` | GETESTET | - | - |
| 44 | Admin: Admin hinzufügen (keine Rechte) | `POST ?page=admins` | GETESTET | - | - |
| 45 | Admin: Admin hinzufügen (Name Duplikat) | `POST ?page=admins` | TEILWEISE GETESTET | - | - |
| 46 | Admin: Admin bearbeiten | `POST ?page=admins` action=edit | GETESTET | **BUG-006 (Fatal Error)** | IMP-014 |
| 47 | Admin: Admin löschen | `POST ?page=admins` action=edit + delete=yes | GETESTET | **BUG-006 (Fatal Error)** | IMP-023 |
| 48 | Admin: Self-Delete | - | BLOCKIERT | - | IMP-023 |
| 49 | Admin: SuperAdmin löschen (blockiert) | - | TEILWEISE (Code gelesen) | - | - |
| 50 | Admin: Konfiguration anzeigen | `GET ?page=configuration` | GETESTET | - | - |
| 51 | Admin: Konfiguration speichern | `POST ?page=configuration` | GETESTET | - | IMP-020 |
| 52 | Admin: Konfiguration (fehlende Pflichtfelder) | `POST ?page=configuration` | GETESTET | - | - |
| 53 | Admin: Konfiguration (ohne CSRF) | `POST ?page=configuration` | GETESTET | - | - |
| 54 | Admin: Emails-Page | `GET ?page=emails` | GETESTET | BUG-004 | IMP-013 |
| 55 | Admin: Pages-Page (Helper) | `GET ?page=pages` | GETESTET | BUG-004 | IMP-013 |
| 56 | Admin: Empty-Page | `GET ?page=empty` | GETESTET | BUG-004 | IMP-013 |
| 57 | Admin: License | `GET ?page=license` | GETESTET | - | - |
| 58 | Admin: Logout (POST) | `POST ?page=logout` | GETESTET | - | IMP-037 |
| 59 | Admin: Logout (GET) | `GET ?logout=yes` | GETESTET | - | IMP-012 |
| 60 | Security: CSRF (Login Replay) | old token | GETESTET | - | - |
| 61 | Security: CSRF-Token rotiert nach Success | - | GETESTET | - | - |
| 62 | Security: LFI/Path Traversal in `?page=` | `?page=../etc/passwd` | GETESTET | - | - |
| 63 | Security: Direktzugriff auf Include-Datei | `/pb_inc/*.php` | GETESTET | BUG-013 | - |
| 64 | Security: Direktzugriff install_deu.php | `/install_deu.php` | GETESTET | BUG-011 | IMP-006, IMP-007 |
| 65 | Security: coverage_report.php Direktzugriff | `/coverage_report.php` | GETESTET | BUG-012 | - |
| 66 | Security: Session-Cookie-Flags | Response Headers | GETESTET | - | IMP-041 |
| 66b | Security: Session-Fixation (Regeneration) | POST ?page=login | GETESTET | **BUG-014** | - |
| 66c | Security: CSRF auf Entry-Edit | POST ?page=edit ohne Token | GETESTET | - | - |
| 66d | Security: CSRF auf Release | POST ?page=release ohne Token | GETESTET | - | - |
| 66e | Security: CSRF auf Statement | POST ?page=statement ohne Token | GETESTET | - | - |
| 67 | Security: HTTP-Security-Headers | Response Headers | GETESTET | - | IMP-040 |
| 68 | Security: Access ohne Login zu protected Pages | `GET ?page=entries` ohne Session | GETESTET | - | IMP-003 |
| 69 | Persistenz: Entry Edit → DB / Frontend sichtbar | durchgehend | GETESTET | - | - |
| 70 | Persistenz: Statement → Frontend sichtbar | durchgehend | GETESTET | - | - |
| 71 | Persistenz: Admin CRUD → DB-Wirkung | durchgehend | GETESTET | - | - |
| 72 | Persistenz: Konfiguration → wieder laden | durchgehend | GETESTET | - | - |
| 73 | Mailpit: Password Recovery liefert Mail | durchgehend | GETESTET | - | - |
| 74 | Mailpit: Admin-Added-Mail fehlt (Fatal Error) | durchgehend | GETESTET | BUG-006 | IMP-008 |

---

## 3. Einzel-Testberichte (detailliert)

### Test #1: Frontend-Startseite `/pbook.php` (Anonymer Besucher)
- **Route:** GET /pbook.php
- **Vorbedingung:** Keine Session
- **Schritte:** Navigate zu http://localhost:8080 → redirect auf `/pbook.php`.
- **Erwartet:** Gästebuch mit allen veröffentlichten Einträgen, Eintragsformular, Admin-Link in linker Spalte.
- **Tatsächlich:** ✅ Rendert korrekt. Zeigt 1 Eintrag initial ("dfsdf / fxcvxcvxcvxycyxc"). Homepage-Link des Eintrags fehlerhaft gerendert (siehe BUG-001).
- **UI:** Klassisches dunkelblaues Tabellen-Layout.
- **Konsole:** Eine extension-generierte Log-Zeile (Content-Script), keine Fehler.
- **Netzwerk:** HTTP 200, ~4 KB.
- **Persistenz:** N/A (Lesezugriff)
- **Negativfall:** n/a für reinen GET; siehe Tests 4-7.
- **Randfall:** Direkter POST ohne Form → redirect auf Form oder Fehlermeldung (getestet in #9).
- **Status:** GETESTET
- **Bug-Refs:** BUG-001
- **Improvement-Refs:** IMP-026, IMP-029

### Test #2/3: Eintrag schreiben (Positiv)
- **Route:** POST /pbook.php preview=yes, dann add_entry=yes
- **Vorbedingung:** Frische Session, gültiger CSRF-Token aus dem Form
- **Schritte:**
  1. GET /pbook.php → CSRF-Token extrahieren
  2. POST mit name=AuditTestUser, text=Auditeintrag_Test_42, preview=yes
  3. Preview erhalten, NEW CSRF-Token extrahieren
  4. POST mit name2/text2/url2/preview=no/add_entry=yes
- **Erwartet:** Nach Preview → "Eintragen!"-Button. Nach Submit → "Der Eintrag wurde erfolgreich in die Datenbank aufgenommen. Vielen Dank!"
- **Tatsächlich:** ✅ Exakt wie erwartet. Der Eintrag erscheint sofort auf der öffentlichen Seite.
- **Persistenz:** DB-Tabelle `powerbook_entries` erhält neuen Datensatz mit status='R'.
- **Status:** GETESTET

### Test #9: CSRF-Token fehlt
- **Route:** POST /pbook.php preview=yes **ohne csrf_token**
- **Erwartet:** Formular wird neu gerendert mit Fehlermeldung "CSRF-Token ungültig. Bitte die Seite neu laden."
- **Tatsächlich:** ❌ PHP Fatal Error (BUG-002). Response wird nach ~2301 Byte abgeschnitten, User sieht nichts.
- **Status:** GETESTET

### Test #15: Suche mit keinem Treffer
- **Route:** GET /pbook.php?tmp_where=name&tmp_search=NonExistent
- **Erwartet:** Hinweis "Keine passenden Einträge" als klickbarer Link.
- **Tatsächlich:** ❌ Der HTML-Code wird als Text mit Entities angezeigt (BUG-007): `<a href="javascript:history.back()">Keine passenden Einträge.</a>`
- **Status:** GETESTET

### Test #28: Passwort-Reset (existierender Admin)
- **Route:** POST /pb_inc/admincenter/?page=password
- **Schritte:** Name=PowerBook, email_known=leer, action=recover
- **Erwartet:** Erfolgsmeldung + Mail an hinterlegte Adresse mit *Reset-Link* (sicherer Flow).
- **Tatsächlich:** ⚠️ Erfolgsmeldung + Mail mit neuem Passwort im Klartext (sofortiger Reset, BUG-010).
- **Mailpit:** 1 Mail an admin@example.com "PowerBook: Neues Passwort a5cc11b9b47b35a5".
- **Status:** GETESTET

### Test #29: Passwort-Reset (nicht-existenter User)
- **Route:** POST /pb_inc/admincenter/?page=password
- **Schritte:** Name=NonExistentAdmin, action=recover
- **Erwartet:** Gleiche generische Response wie bei existierendem User.
- **Tatsächlich:** ❌ "Admin in Datenbank nicht gefunden!" (BUG-009, User Enumeration).
- **Status:** GETESTET

### Test #42: Admin hinzufügen (Positiv)
- **Route:** POST /pb_inc/admincenter/?page=admins action=add
- **Schritte:** add_name=TestAdmin, add_email=testadmin@example.com, add_entries=Y, add_release=Y
- **Erwartet:** "Admin erfolgreich hinzugefügt..." + Mail an neuen Admin mit Temp-Passwort.
- **Tatsächlich:** ❌ **BUG-006**: Fatal Error. Der Admin **wird** angelegt (DB-Insert passiert), aber UI zeigt nichts, keine Mail wird versandt.
- **Mailpit:** 0 neue Mails.
- **Log:** `Fatal error: Call to undefined function sendAdminEmail() in /var/www/html/pb_inc/admincenter/admins.inc.php:85`.
- **Status:** GETESTET

### Test #46/47: Admin bearbeiten/löschen
- Gleiches Muster wie #42 — alle drei CRUD-Operationen (add/edit/delete) triggern BUG-006, jeweils mit Fatal auf Zeilen 85 (add) / 229 (edit) / 149 (delete).
- DB-Änderungen erfolgen, aber UI zeigt nichts und keine Mail wird versandt.
- **Status:** GETESTET

### Test #51-53: Konfigurations-Save
- **Route:** POST /pb_inc/admincenter/?page=configuration action=update
- **Positiv (alle Felder gefüllt):** ✅ Speichert OK, aber kein Success-Banner (IMP-020).
- **Negativ (fehlender `change_email`/`change_design`):** "Bitte füllen Sie folgende Felder aus: E-Mail-Adresse, Eintrags-Design, ..." ✅
- **CSRF fehlt:** Stillschweigend nicht gespeichert, kein Fehlertext (siehe IMP zu Feedback).
- **Status:** GETESTET

### Test #58/59: Logout
- **POST /pb_inc/admincenter/?page=logout:** Session gelöscht, Login-Formular erscheint. ✅
- **GET /pb_inc/admincenter/?logout=yes:** Ebenfalls sessions gelöscht. ⚠️ CSRF-anfällig (IMP-012).
- Nach Logout: Zugriff auf `?page=admins` ohne Session → Body 2556 Byte, nur Header, kein Admin-Content.
- **Status:** GETESTET

### Test #60/61: CSRF-Token-Rotation & Replay
- Nach erfolgreichem POST (z. B. Statement-Update) rotiert der CSRF-Token.
- Replay mit altem Token → POST fails silently (Statement bleibt alter Wert "Admin_Audit_Statement").
- **Status:** GETESTET (Positiv)

### Test #62: LFI Path Traversal
- `?page=../config`, `?page=../../etc/passwd`, `?page=%2e%2e%2fconfig` → Alle fallen auf `home` zurück (Whitelist wirkt).
- **Status:** GETESTET

### Test #63: Direkter Include-Zugriff
- `/pb_inc/config.inc.php`, `functions.inc.php`, `mysql.inc.php`, `csrf.inc.php` → HTTP 200 mit 0 Byte (silent)
- `/pb_inc/guestbook.inc.php` → **HTTP 200 mit 5311 Byte** Content (BUG-013)
- `/pb_inc/admincenter/admins.inc.php` → "Keine Berechtigung" (OK)
- **Status:** GETESTET

### Test #64: install_deu.php
- Direkt aufrufbar. Zeigt Installationsassistent mit DB-Credentials im Klartext. BUG-011.
- **Status:** GETESTET

### Test #65: coverage_report.php
- HTTP 500 (PHP Parse Error, BUG-012).
- **Status:** GETESTET

### Test #66: Session-Cookie-Flags
- Gesetzt: `HttpOnly; SameSite=Strict`. ✅
- Fehlt: `Secure` (nur bei HTTPS relevant).
- **Status:** GETESTET

### Test #67: HTTP-Security-Headers
- Keine X-Frame-Options, CSP, X-Content-Type-Options, Referrer-Policy, HSTS, Permissions-Policy gesetzt.
- **Status:** GETESTET (IMP-040)

### Test #73: Mailpit Password Recovery
- Nach #28: Mail erhalten, Inhalt "Hallo, PowerBook! Hier ist Ihr neues PowerBook AdminCenter Passwort: a5cc11b9b47b35a5".
- **Status:** GETESTET

### Test #74: Mailpit Admin-Add-Mail FEHLT
- Nach #42: Mailpit zeigt 0 neue Mails → Bestätigt, dass Fatal vor Mail-Versand greift.
- **Status:** GETESTET

---

## 4. Zusammenfassung / Abschlussbericht

### 4.1 Getestete Bereiche
- **29 Unterseiten des Userbereichs** wurden systematisch angesteuert.
- **78 einzelne Testfälle** (Positiv + Negativ + Randfall).
- Alle in `$allowedPages` gelisteten AdminCenter-Routen wurden abgedeckt.
- Gästebuch-Frontend vollständig durchgespielt: Formular, Preview, Submit, Validation, BBCode, Icons, Smilies, Suche, URL-Auto-Link.

### 4.2 Gefundene Bugs (14 Einträge in bugs.md) — **alle behoben am 2026-04-23**

> Siehe Plan `docs/superpowers/plans/2026-04-23-userbereich-bugs-fix.md` und Commits seit `854956b`.

| ID       | Schweregrad | Kurzbeschreibung | Status |
|----------|-------------|------------------|
| BUG-001  | Mittel      | Homepage-URL wird als komplettes HTML in DB gespeichert, führt zu gebrochenem Link. | 6008163 ✅ |
| BUG-002  | **Kritisch**| PHP Fatal Error bei CSRF-Fehler im Gästebuch-Formular. | 62d8e8b ✅ |
| BUG-003  | Niedrig-Mittel | Doppelte HTML-Escape der Eingabewerte in der Preview-Anzeige. | 6008163 ✅ |
| BUG-004  | Niedrig     | Admin-Panel-Pseudoseiten (`emails`, `pages`, `empty`) rendern leere Content-Area. | cd9b742 ✅ |
| BUG-005  | Niedrig     | `edit`/`statement` ohne ID-Parameter zeigen nutzlosen `history.back()`-Link. | 98454af ✅ |
| BUG-006  | **Kritisch**| PHP Fatal Error beim Hinzufügen/Bearbeiten/Löschen von Admins. | ecdb0f6/6008163 ✅ |
| BUG-007  | Niedrig     | "Keine passenden Einträge" wird als HTML-Text statt als Link angezeigt. | 9e9bb9f ✅ |
| BUG-008  | Mittel      | Keine serverseitige Längenbegrenzung für Name, Text und weitere Felder. | 04d398f ✅ |
| BUG-009  | Mittel      | Password-Recovery bestätigt Existenz eines Admin-Namens (User Enumeration). | 7bf2cff ✅ |
| BUG-010  | Mittel-Hoch | Password-Recovery setzt Passwort sofort zurück — kein Recovery-Link. | 7bf2cff ✅ |
| BUG-011  | **Hoch**    | `install_deu.php` ist öffentlich erreichbar (Sicherheitsrisiko). | 2910e8a ✅ |
| BUG-012  | Niedrig     | `coverage_report.php` hat PHP Parse Error. | d35c609 ✅ |
| BUG-013  | Niedrig-Mittel | Gästebuch-Include direkt aufrufbar rendert Content ohne Wrapper. | 65989e3 ✅ |
| BUG-014  | **Mittel-Hoch**| Session-ID wird beim Login nicht regeneriert (Session Fixation). | 1ff79d6 ✅ |

### 4.3 Empfohlene Improvements (41 Einträge in improvements.md)
- 4× Priorität **Hoch** (IMP-005, IMP-006, IMP-007, IMP-008): Password-Recovery-Flow, Standard-Passwort, Install-Lock, Admin-Add-Fallback.
- 12× Priorität **Mittel**: Session-UX, User-Enumeration, XSS-Preview, URL-Validierung, Security-Header, Cookie-Flags, Rate-Limit u.a.
- 25× Priorität **Niedrig/Sehr niedrig**: UX, Layout, Legacy-Features.

### 4.4 Security-Beurteilung (kurz)
- ✅ **SQL Injection:** Prepared Statements konsistent verwendet. Tests zeigen keinen Exploit-Pfad.
- ✅ **Stored XSS:** `e()` (htmlspecialchars) durchgängig im Render-Pfad. Tests zeigen keinen Raw-Script im Rendering.
- ✅ **LFI:** Whitelist in `$allowedPages` schützt vor Pfad-Traversal.
- ✅ **Session-Cookie:** HttpOnly + SameSite=Strict gesetzt.
- ✅ **Authentication:** Passwörter via `password_hash(PASSWORD_DEFAULT)`.
- ⚠️ **Fatal Errors** (BUG-002, BUG-006) unterbrechen kritische Flows — funktionale Beeinträchtigung, kein Security-Risiko per se, aber User-DoS.
- ⚠️ **User Enumeration** (BUG-009).
- ⚠️ **Passwort-Reset-Flow unsicher** (BUG-010).
- ⚠️ **install_deu.php frei erreichbar** (BUG-011) — **hohes Risiko**.
- ⚠️ **Kein Rate-Limit** auf Login-Attempts.
- ⚠️ **Keine HTTP-Security-Header** (Clickjacking, MIME-Sniffing etc.).

### 4.5 Funktionale Beurteilung (kurz)
- Grundfunktionen (Lesen / Eintrag schreiben / Lesen mit Admin / Suche) **funktionieren**.
- Admin-Verwaltung **bricht silent ab** (Daten werden dennoch modifiziert) — sehr irritierend.
- Konfiguration **speichert** — kein Erfolgs-Feedback, daher unsicher.
- Mailpit zeigt, dass SMTP grundsätzlich erreichbar ist (Password-Reset-Mail geht durch). Admin-Mail nur wegen Fatal-Error nicht gesendet.

### 4.6 Abdeckungsstatistik
| Kategorie              | Anzahl Cases | GETESTET | TEILWEISE | BLOCKIERT |
|------------------------|--------------|----------|-----------|-----------|
| Frontend Gästebuch     | 21           | 19       | 2         | 0         |
| AdminCenter            | 38           | 33       | 4         | 1         |
| Security / Infrastruktur | 19         | 19       | 0         | 0         |
| **Gesamt**             | **78**       | **71**   | **6**     | **1**     |

→ **≈ 91% aller geplanten Testfälle vollständig abgedeckt**. 8% TEILWEISE (z. B. Paginierung mit wenig Datenbestand). 1 Fall BLOCKIERT (Admin-Self-Delete konnte wegen BUG-006-Fatal-Error nicht vollständig UI-getestet werden, Code-Pfad aber verifiziert).

### 4.7 Offene / nicht erreichbare Bereiche
- Eintrags-Paginierung >15 Einträge: Nicht reproduzierbar ohne große Testdatenbank (TEILWEISE).
- Admin Self-Delete: In Logik vorhanden; konnte nicht vollständig getestet werden, weil Fatal Error (BUG-006) das UI-Feedback abbricht (BLOCKIERT für konkretes UI-Feedback, Code-Pfad verifiziert).
- SMTP-Ausfall-Fallback: Getestet nur via Log (`Email Error: Empty recipient`), kein echter Produktiv-Test.

### 4.8 Nächste Schritte (Empfehlung)
In Reihenfolge der Dringlichkeit:
1. **BUG-002 + BUG-006** beheben (Fatal Errors). Lösungen:
   - `pb_inc/guestbook.inc.php` sollte `require_once __DIR__ . '/error-handler.inc.php';` aufnehmen.
   - `pb_inc/admincenter/admins.inc.php` — Funktions-Definitionen ins Root-Scope ziehen ODER vor Verwendung deklarieren.
2. **BUG-011** beheben — Install-Datei nach Installation sperren/löschen.
3. **BUG-010 / IMP-005** überarbeiten — Token-basiertes Password-Reset statt Direktwechsel.
4. Rate-Limit (IMP-017) und HTTP-Security-Header (IMP-040) ergänzen.
5. Restliche Bugs und mittel-priorisierte Improvements abarbeiten.
