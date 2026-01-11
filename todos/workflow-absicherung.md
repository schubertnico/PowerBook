# PowerBook - Workflow-Absicherung Todos

## Status-Legende
- [ ] Offen
- [x] Erledigt
- [!] Kritisch

---

## Phase 1: Kritische Fixes

### [!] 1.1 Email-Validierung korrigieren
**Datei:** `pb_inc/guestbook.inc.php` Zeile 185
**Problem:** Verwendet `str_contains('@')` statt `filter_var()`
**Aktion:** Ändern zu:
```php
elseif (strlen($email2) >= 1 && !filter_var($email2, FILTER_VALIDATE_EMAIL)) {
```
- [ ] Code ändern
- [ ] Test hinzufügen
- [ ] Manuell testen

### [!] 1.2 CSRF-Handling vereinheitlichen
**Dateien:** `pb_inc/guestbook.inc.php` Zeilen 195-197, 271-273
**Problem:** Verwendet `die()` statt Fehlermeldung
**Aktion:** Konsistentes Error-Handling wie in index.php
- [ ] Preview-Handler ändern
- [ ] Add-Entry-Handler ändern
- [ ] Test hinzufügen

### [!] 1.3 DB-Error-Handling in Admin-Formularen
**Dateien:**
- `pb_inc/admincenter/configuration.inc.php`
- `pb_inc/admincenter/password.inc.php`
- `pb_inc/admincenter/edit.inc.php`
- `pb_inc/admincenter/admins.inc.php`
- `pb_inc/admincenter/release.inc.php`
- `pb_inc/admincenter/statement.inc.php`

**Aktion:** try-catch um DB-Operationen
- [ ] error-handler.inc.php erstellen
- [ ] In alle Admin-Dateien integrieren
- [ ] Tests hinzufügen

---

## Phase 2: Robustheit verbessern

### 2.1 Race Condition im Spam-Check beheben
**Datei:** `pb_inc/guestbook.inc.php` Zeilen 279-317
**Problem:** Kein Transaction-Lock
**Aktion:** `beginTransaction()` + `FOR UPDATE` + `commit()`
- [ ] Code ändern
- [ ] Concurrent-Test erstellen

### 2.2 Email-Fehler loggen
**Dateien:** Alle mit `@mail()`
**Aktion:** sendEmail() Wrapper-Funktion erstellen
- [ ] sendEmail() in functions.inc.php
- [ ] Alle @mail() Aufrufe ersetzen
- [ ] Logging implementieren

### 2.3 Passwort-Validierung hinzufügen
**Datei:** `pb_inc/validation.inc.php`
**Aktion:** validatePassword() mit Mindestlänge
- [ ] Funktion erstellen
- [ ] In admins.inc.php integrieren
- [ ] Tests hinzufügen

### 2.4 Logger erstellen
**Neue Datei:** `pb_inc/logger.inc.php`
**Funktionen:**
- logError()
- logFormSubmission()
- [ ] Datei erstellen
- [ ] In kritische Stellen integrieren

---

## Phase 3: Test-Coverage erweitern

### 3.1 Edge-Case Unit-Tests
- [ ] tests/Unit/EdgeCasesTest.php erstellen
- [ ] Email-Validierung Edge-Cases
- [ ] Passwort-Validierung Edge-Cases
- [ ] CSRF-Token Edge-Cases

### 3.2 Integration-Tests für Formulare
- [ ] tests/Integration/GuestbookFormTest.php
- [ ] tests/Integration/AdminLoginTest.php
- [ ] tests/Integration/ConfigurationTest.php
- [ ] tests/Integration/AdminManagementTest.php

### 3.3 Test-Coverage-Ziel
- [ ] Coverage auf 80%+ erhöhen
- [ ] Coverage-Report generieren

---

## Phase 4: CI/CD Pipeline

### 4.1 GitHub Actions
- [ ] .github/workflows/ci.yml erstellen
- [ ] MySQL Service konfigurieren
- [ ] PHPStan Step
- [ ] PHPUnit Step
- [ ] Coverage Upload

### 4.2 Pre-Commit Hooks
- [ ] hooks/pre-commit erstellen
- [ ] composer setup-hooks Script
- [ ] Dokumentieren

### 4.3 Branch-Schutz
- [ ] Status checks aktivieren
- [ ] Code review erforderlich
- [ ] Direkte Pushes zu master blockieren

---

## Phase 5: Monitoring

### 5.1 Logging-System
- [ ] logs/error.log - Fehler
- [ ] logs/forms.log - Formular-Submissions
- [ ] logs/security.log - CSRF-Fehler, verdächtige Aktivitäten

### 5.2 Log-Rotation
- [ ] Alte Logs archivieren/löschen
- [ ] Größenbeschränkung

---

## Checkliste für jeden Formular-Handler

Vor Abschluss muss jeder Handler diese Punkte erfüllen:

```
[ ] CSRF-Token validiert
[ ] Eingaben getrimmt
[ ] Email mit filter_var() validiert
[ ] DB-Operationen in try-catch
[ ] Fehler geloggt
[ ] Verständliche Fehlermeldung für User
[ ] Keine sensiblen Daten in Fehlermeldungen
[ ] Unit-Test vorhanden
[ ] PHPStan Level 5 besteht
```

---

## Dateien-Übersicht

### Zu ändern
| Datei | Änderungen |
|-------|------------|
| guestbook.inc.php | Email-Validierung, CSRF-Handling, Spam-Check |
| admincenter/configuration.inc.php | DB-Error-Handling |
| admincenter/password.inc.php | DB-Error-Handling |
| admincenter/edit.inc.php | DB-Error-Handling |
| admincenter/admins.inc.php | DB-Error-Handling, Passwort-Validierung |
| admincenter/release.inc.php | DB-Error-Handling |
| admincenter/statement.inc.php | DB-Error-Handling |
| validation.inc.php | Passwort-Validierung |

### Neu zu erstellen
| Datei | Zweck |
|-------|-------|
| pb_inc/error-handler.inc.php | safeDbOperation() |
| pb_inc/logger.inc.php | Logging-Funktionen |
| tests/Unit/EdgeCasesTest.php | Edge-Case Tests |
| tests/Integration/*.php | Integration-Tests |
| .github/workflows/ci.yml | CI/CD Pipeline |
| hooks/pre-commit | Git Hook |

---

## Fortschritt

| Phase | Status | Fortschritt |
|-------|--------|-------------|
| Phase 1 | Erledigt | 100% |
| Phase 2 | Erledigt | 100% (Error-Handler in allen Admin-Dateien) |
| Phase 3 | Teilweise | 73% (Unit-Tests vorhanden) |
| Phase 4 | Offen | 0% |
| Phase 5 | Teilweise | 30% (Logging implementiert) |

### Erledigte Aufgaben (Phase 1)

- [x] Email-Validierung in guestbook.inc.php (filter_var statt str_contains)
- [x] CSRF-Handling vereinheitlicht (keine die() mehr, Fehlermeldung)
- [x] Race Condition behoben (Transaction mit FOR UPDATE)
- [x] error-handler.inc.php erstellt
- [x] DB-Error-Handling in configuration.inc.php
- [x] DB-Error-Handling in password.inc.php
- [x] DB-Error-Handling in edit.inc.php
- [x] PHPStan: No errors
- [x] PHPUnit: 110 tests passed

### Erledigte Aufgaben (Phase 2)

- [x] DB-Error-Handling in admins.inc.php (add, load, delete, update, list)
- [x] DB-Error-Handling in release.inc.php (release all, release one, load entries)
- [x] DB-Error-Handling in statement.inc.php (update, load entry)
- [x] validatePassword() mit Mindestlaenge (8 Zeichen)
- [x] validatePasswordConfirmation() fuer Passwort-Bestaetigung
- [x] sendEmail() Wrapper mit Logging
- [x] Alle @mail() Aufrufe durch sendEmail() ersetzt
- [x] PHPStan: No errors
- [x] PHPUnit: 121 tests passed

**Letzte Aktualisierung:** 2026-01-11
