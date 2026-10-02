# Dokumentation

Dieses Verzeichnis enthält die ausführliche Projekt-Dokumentation. Eine Schnellübersicht steht in der [`README.md`](../README.md) im Projektordner.

## Sicherheits-Audit (April 2026)

Vollständiger Audit des PowerBook-Userbereichs (Frontend + AdminCenter), durchgeführt am 2026-04-23, alle Funde behoben am 2026-04-24.

| Datei | Inhalt |
|-------|--------|
| [`2026-04-23-Userbereichs-bugs.md`](2026-04-23-Userbereichs-bugs.md) | 14 dokumentierte Bugs mit Reproduktion, Ursache, Fix-Commit |
| [`2026-04-23-Userbereichs-improvements.md`](2026-04-23-Userbereichs-improvements.md) | 41 Workflow- und UX-Verbesserungs-Vorschläge |
| [`2026-04-23-Userbereichs-test-coverage.md`](2026-04-23-Userbereichs-test-coverage.md) | 78 Testfälle mit Status (91 % vollständig durchgeführt) |

## Installation und Aktualisierung (ab 3.1)

| Datei | Inhalt |
|-------|--------|
| [`../powerbook.sql`](../powerbook.sql) | Einzige Quelle des Datenbankschemas. `install.php` legt die Tabellen daraus an, `update.php` vergleicht damit, die Tests laden sie. |
| [`../install.php`](../install.php), `../pb_inc/install.inc.php` | Installer in vier Schritten: Datenbank, Gästebuch, Administrator, Fertig. Schreibt `pb_inc/mysql.inc.php` und die Sperrdatei `install.lock`. |
| [`../update.php`](../update.php), `../pb_inc/update.inc.php` | Aktualisierung von PowerBook 1.x, 2.0 und 3.0 auf 3.1, nur nach Anmeldung als Administrator mit dem Recht „Konfiguration ändern“. |
| `../pb_inc/setup.inc.php` | Gemeinsame Helfer (Seitenrahmen, Verbindung, Schema, `mysql.inc.php` schreiben) und die Seiten „noch nicht eingerichtet“ bzw. „Datenbank nicht erreichbar“. |
| [`../tests/Fixtures/`](../tests/Fixtures/) | Tabellen von PowerBook 1.21, 2.0 und 3.0 für die Tests von `update.php`. |

Tests gegen einen echten MySQL- oder MariaDB-Server laufen, sobald `PB_TEST_DB_HOST` gesetzt ist
(dazu `PB_TEST_DB_PORT`, `PB_TEST_DB_USER`, `PB_TEST_DB_PASSWORD`, `PB_TEST_DB_PREFIX`); sonst werden
sie übersprungen. Die CI startet dafür MySQL 8.0 und MariaDB 10.6.

## Übersicht: Behobene Bugs (Stand 2026-04-24)

| ID | Schweregrad | Kurzbeschreibung | Fix-Commit |
|----|-------------|------------------|------------|
| BUG-001 | Mittel | Homepage-URL als komplettes HTML in DB gespeichert | `6008163` |
| BUG-002 | **Kritisch** | PHP Fatal Error bei CSRF-Fehler im Gästebuch | `62d8e8b` |
| BUG-003 | Niedrig–Mittel | Doppel-Escape im Preview-Pfad | `6008163` |
| BUG-004 | Niedrig | Pseudo-Seiten in `$allowedPages`-Whitelist | `cd9b742` |
| BUG-005 | Niedrig | `history.back()`-Links bei direktem Aufruf | `98454af` |
| BUG-006 | **Kritisch** | Fatal Error bei Admin-CRUD (Funktion in conditional) | `ecdb0f6` + `6008163` |
| BUG-007 | Niedrig | "Keine passenden Einträge" als HTML-Text statt Link | `9e9bb9f` |
| BUG-008 | Mittel | Keine serverseitige Längenvalidierung | `04d398f` |
| BUG-009 | Mittel | User Enumeration im Password-Recovery | `7bf2cff` |
| BUG-010 | Mittel–Hoch | Sofort-Reset des Passworts (DoS-Vektor) | `7bf2cff` |
| BUG-011 | **Hoch** | install_deu.php frei zugänglich | `2910e8a` |
| BUG-012 | Niedrig | Parse-Error in coverage_report.php | `d35c609` |
| BUG-013 | Niedrig–Mittel | Direktaufruf von guestbook.inc.php möglich | `65989e3` |
| BUG-014 | Mittel–Hoch | Session-Fixation (keine ID-Regeneration) | `1ff79d6` |

## Übersicht: Umgesetzte Improvements (Stand 2026-04-24)

| ID | Priorität | Kurzbeschreibung | Fix-Commit |
|----|-----------|------------------|------------|
| IMP-005 | Hoch | Token-basierter Passwort-Reset | `7bf2cff` (mit BUG-010) |
| IMP-006 | Hoch | Zufälliges Initial-Admin-Passwort | `1bdac4d` |
| IMP-007 | Hoch | Install-Lock | `2910e8a` (mit BUG-011) |
| IMP-008 | Hoch | Admin-Add UI-Fallback bei SMTP-Fehler | `03c9ab3` |

Die übrigen 37 Improvements sind als Backlog dokumentiert und nicht umgesetzt.

## Test-Status (Stand 2026-04-24)

```
PHPUnit 11.5.46 — PHP 8.4.16
Tests: 515, Assertions: 950, Skipped: 2 (Container-Network)
Coverage: ~87 %
```

Alle Tests laufen reproduzierbar gegen das Docker-Setup unter `.docker/`.

## Lese-Reihenfolge für Neueinsteiger

1. [`../README.md`](../README.md) — Projekt-Überblick + Schnellstart
2. Diese Datei (`docs/README.md`) — Doku-Index
3. [`2026-04-23-Userbereichs-bugs.md`](2026-04-23-Userbereichs-bugs.md) — was war kaputt, was wurde gefixt
4. [`2026-04-23-Userbereichs-test-coverage.md`](2026-04-23-Userbereichs-test-coverage.md) — was wird getestet
5. [`2026-04-23-Userbereichs-improvements.md`](2026-04-23-Userbereichs-improvements.md) — Backlog für künftige Iterationen
