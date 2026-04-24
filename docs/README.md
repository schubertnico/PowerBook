# Dokumentation

Dieses Verzeichnis enthält die ausführliche Projekt-Dokumentation. Eine Schnellübersicht findest du in der [`README.md`](../README.md) im Projekt-Root.

## Sicherheits-Audit (April 2026)

Vollständiger Audit des PowerBook-Userbereichs (Frontend + AdminCenter), durchgeführt am 2026-04-23, alle Funde behoben am 2026-04-24.

| Datei | Inhalt |
|-------|--------|
| [`2026-04-23-Userbereichs-bugs.md`](2026-04-23-Userbereichs-bugs.md) | 14 dokumentierte Bugs mit Reproduktion, Ursache, Fix-Commit |
| [`2026-04-23-Userbereichs-improvements.md`](2026-04-23-Userbereichs-improvements.md) | 41 Workflow- und UX-Verbesserungs-Vorschläge |
| [`2026-04-23-Userbereichs-test-coverage.md`](2026-04-23-Userbereichs-test-coverage.md) | 78 Testfälle mit Status (91 % vollständig durchgeführt) |

## Implementierungspläne

| Datei | Inhalt |
|-------|--------|
| [`superpowers/plans/2026-04-23-userbereich-bugs-fix.md`](superpowers/plans/2026-04-23-userbereich-bugs-fix.md) | Schritt-für-Schritt-Plan für die Bug-Behebung (15 Tasks, TDD-orientiert) |

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
