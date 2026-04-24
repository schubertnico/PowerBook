# PowerBook — PHP-Gästebuch-System

[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://www.php.net/)
[![Tests](https://img.shields.io/badge/tests-515%20passing-brightgreen.svg)](#tests)
[![Coverage](https://img.shields.io/badge/coverage-87%25-brightgreen.svg)](#tests)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

> Klassisches PHP-Gästebuch — ursprünglich 2002 von **Axel "Expandable" Habermaier** entwickelt, modernisiert für **PHP 8.4** und sicherheitsgehärtet (Stand: 2026-04-24).

**Projekt-Webseite:** https://www.powerscripts.org
**Projektbereich:** https://www.powerscripts.org/projects-5.html
**Repository:** https://github.com/schubertnico/PowerBook

---

## ⚡ Schnellstart (Docker, 60 Sekunden)

```bash
# 1. Repository klonen
git clone https://github.com/schubertnico/PowerBook.git
cd PowerBook/.docker

# 2. Container starten
docker compose up -d --build

# 3. Installation im Browser ausführen
# → http://localhost:8080/install_deu.php
# Auf "Installation starten" klicken.
# Das angezeigte Initial-Passwort SOFORT notieren — es wird nicht erneut angezeigt!

# 4. Loslegen
# Frontend (Gästebuch):  http://localhost:8080/pbook.php
# Admin-Center:          http://localhost:8080/pb_inc/admincenter/
# Mailpit (Test-Mails):  http://localhost:8031
```

> **Hinweis zum Initial-Passwort:** Seit IMP-006 (April 2026) wird bei der Installation ein zufälliges 16-stelliges Hex-Passwort generiert (kein hardcoded `powerbook` mehr). Das Passwort erscheint **einmalig** auf der Erfolgsseite — bitte sofort notieren oder direkt im AdminCenter ändern.

---

## Inhaltsverzeichnis

- [Features](#features)
- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
  - [Docker (empfohlen)](#docker-empfohlen)
  - [Klassisches Hosting (LAMP)](#klassisches-hosting-lamp)
- [AdminCenter](#admincenter)
- [Sicherheit](#sicherheit)
- [Tests](#tests)
- [Entwicklung](#entwicklung)
- [Troubleshooting](#troubleshooting)
- [Lizenz](#lizenz)
- [Kontakt & Support](#kontakt--support)

---

## Features

- **Gästebuch-Frontend** für Besucher: Eintrag schreiben, lesen, Suchen, Paginierung
- **AdminCenter** mit Rollen-/Rechtemanagement (Konfiguration, Einträge, Freischaltung, Admin-Verwaltung)
- **BBCode**-Textformatierung (`[b]`, `[i]`, `[u]`, `[small]`) + Auto-Linking von URLs
- **Smilies/Emoticons** (umschaltbar pro Eintrag)
- **Icons** für Einträge (Frage, Ausruf, Smileys etc.)
- **Anti-Spam:** IP-basierte Zeitsperre (konfigurierbar)
- **Token-basierter Passwort-Reset** mit 30-Minuten-TTL (kein Sofort-Wechsel)
- **CSRF-Schutz** auf allen Formularen, mit Token-Rotation nach Erfolg
- **Session-Fixation-Schutz** durch `session_regenerate_id` nach Login
- **E-Mail-Benachrichtigungen** über SMTP (z. B. an Mailpit im Dev-Setup)
- **Mehrsprachig** vorbereitet (Deutsch ausgeliefert)
- **Responsive PHP 8.4** mit `declare(strict_types=1)` durchgängig

---

## Voraussetzungen

| Komponente | Mindestversion |
|------------|----------------|
| PHP | **8.4** |
| Datenbank | MySQL 8.0 / MariaDB 10.6 |
| Webserver | Apache 2.4 (mit `mod_php`) oder NGINX + PHP-FPM |
| Composer | 2.x (nur für Tests/Tools, nicht für den Live-Betrieb erforderlich) |

Für die Docker-Variante reicht **Docker Desktop** (Windows/macOS) oder **Docker Engine + Compose v2** (Linux).

---

## Installation

### Docker (empfohlen)

Das Repository enthält ein vollständiges Docker-Compose-Setup unter `.docker/`:

| Service | Image | Port (Host) |
|---------|-------|-------------|
| `web` | Apache 2.4 + PHP 8.4 | **8080** |
| `db` | MySQL 8.0 | 3314 |
| `mail` | Axllent Mailpit | SMTP **1031**, Web-UI **8031** |

```bash
git clone https://github.com/schubertnico/PowerBook.git
cd PowerBook/.docker
docker compose up -d --build
```

Anschließend `http://localhost:8080/install_deu.php` aufrufen und auf **Installation starten** klicken. Die Installation:

1. legt die MySQL-Tabellen `pb_admins`, `pb_config`, `pb_entries` an (mit Spalten `reset_token` + `reset_token_expires` für den sicheren Recovery-Flow),
2. erzeugt einen Standard-Admin **PowerBook** mit zufälligem 16-Hex-Passwort,
3. zeigt dieses Passwort **einmalig** in einer Hervorhebungs-Box,
4. erstellt das Lock-File `.installed`, sodass `install_deu.php` nicht versehentlich erneut ausgeführt werden kann (HTTP 403).

> **Wichtig:** Notieren Sie das Initial-Passwort sofort. Es kann jederzeit im AdminCenter geändert werden.

### Klassisches Hosting (LAMP)

1. Dateien per FTP/SCP auf den Webserver übertragen.
2. `pb_inc/mysql.inc.php` anpassen:
   ```php
   $config_sql_server   = 'localhost';
   $config_sql_user     = 'powerbook';
   $config_sql_password = 'IhrSicheresPasswort';
   $config_sql_database = 'powerbook';
   ```
3. `https://ihre-domain.tld/install_deu.php` im Browser öffnen.
4. Initial-Passwort notieren.
5. **Empfehlung:** `install_deu.php` zusätzlich per `.htaccess` schützen oder löschen, sobald `.installed` existiert.

---

## AdminCenter

URL: `http://<host>/pb_inc/admincenter/`

| Bereich | Zweck |
|---------|-------|
| **Home** | Dashboard mit System-Info und Schnellnavigation |
| **Einträge verwalten** | Liste, Bearbeiten, Löschen, Statement-Schreiben |
| **Einträge freischalten** | Sichtbarkeit (R/U) — abhängig von Konfiguration |
| **Admins verwalten** | Hinzufügen, Bearbeiten, Löschen, Rechte (Konfig/Admins/Einträge/Release) |
| **Konfiguration** | Spam-Check-Intervall, Anzeige-Einstellungen, Smilies/BBCode/ICQ, E-Mail-Benachrichtigung, Eintrags-Design (Template) |
| **Lizenz** | MIT-Lizenztext |
| **Logout** | Beendet die Session |

**Passwort vergessen?** `?page=password` aufrufen, Name oder E-Mail eingeben → Reset-Link kommt per Mail (30 Minuten gültig). Erst durch Klick + Setzen des neuen Passworts wird das alte ungültig.

---

## Sicherheit

PowerBook wurde im April 2026 einem vollständigen Sicherheits-Audit unterzogen. Alle 14 dokumentierten Bugs sind behoben, drei priorisierte Workflow-Verbesserungen sind umgesetzt:

| Kategorie | Maßnahme |
|-----------|----------|
| **SQL-Injection** | PDO Prepared Statements für alle Queries |
| **XSS** | Konsequentes Output-Escaping (`htmlspecialchars`), keine doppelten Escapes mehr im Preview-Pfad |
| **CSRF** | Token in jedem Formular, Rotation nach erfolgreichen Aktionen |
| **Session-Fixation** | `session_regenerate_id(true)` nach jedem Login |
| **Passwort-Speicherung** | `password_hash(PASSWORD_DEFAULT)`, automatische Migration alter Hashes |
| **Passwort-Reset** | Token-Flow mit 30-Min-TTL (kein Sofort-Reset, kein Account-DoS) |
| **User-Enumeration** | Generische Recovery-Response („Falls ein Konto existiert …") |
| **LFI / Path-Traversal** | Whitelist `$allowedPages` für AdminCenter-Routing |
| **Direktaufruf-Schutz** | `PB_ENTRY`-Konstanten-Guard für `pb_inc/guestbook.inc.php` |
| **Installation-Schutz** | `.installed` Lock-File verhindert Re-Install (HTTP 403) |
| **Standard-Passwort** | Zufällig generiert (`bin2hex(random_bytes(8))`) — kein hardcoded Wert |
| **Admin-Add-Fallback** | Bei SMTP-Fehler wird das Initial-Passwort einmalig im UI angezeigt → kein Self-Lockout |
| **Längen-Validierung** | Server-seitige Längenprüfung für Name (100), Text (5000), E-Mail (250), URL (255) |
| **Cookie-Flags** | Session-Cookie mit `HttpOnly` und `SameSite=Strict` |
| **E-Mail-Header** | Sanitisierung aller Recipient-Header (Anti Header-Injection) |

**Audit-Dokumentation:** siehe `docs/2026-04-23-Userbereichs-bugs.md`, `docs/2026-04-23-Userbereichs-improvements.md`, `docs/2026-04-23-Userbereichs-test-coverage.md`.

---

## Tests

```bash
# Composer-Abhängigkeiten installieren
composer install

# Volle Test-Suite (Unit + Integration)
vendor/bin/phpunit

# Nur Unit-Tests
vendor/bin/phpunit --testsuite Unit

# Nur Integration-Tests (benötigen laufenden Apache-Container)
vendor/bin/phpunit --testsuite Integration

# Im Docker-Container
docker exec powerbook_web vendor/bin/phpunit
```

**Aktueller Stand:** 515 Tests / 950 Assertions / **Coverage 87 %**.

| Suite | Tests | Bemerkung |
|-------|-------|-----------|
| Unit | ~480 | Isoliert, SQLite-In-Memory |
| Integration | ~35 | Audit-/Bug-Regressionstests, einige benötigen laufenden Apache |

**Statische Analyse & Code-Qualität:**

```bash
composer run phpstan        # PHPStan Level max
vendor/bin/phpmd pb_inc text phpmd.xml
vendor/bin/php-cs-fixer fix --dry-run --diff
```

---

## Entwicklung

```bash
# Composer-Skripte
composer install
composer run phpstan
composer run rector-dry
composer run rector

# Docker-Lebenszyklus
cd .docker
docker compose up -d
docker compose logs -f web
docker compose down
docker compose ps
```

**Projektstruktur (Auszug):**

```
PowerBook/
├── pbook.php                           Frontend-Einstieg
├── install_deu.php                     Installations-Assistent
├── pb_inc/
│   ├── guestbook.inc.php               Frontend-Logik
│   ├── form.inc.php                    Eintrags-Formular
│   ├── entry.inc.php                   Eintrags-Rendering
│   ├── csrf.inc.php                    CSRF-Helper
│   ├── error-handler.inc.php           Logging-Helper
│   ├── functions.inc.php               Allgemeine Helfer
│   ├── send-email.php / thank-email.php SMTP-Versand
│   └── admincenter/
│       ├── index.php                   Routing + Auth
│       ├── home.inc.php                Dashboard
│       ├── entries.inc.php             Eintragsliste
│       ├── edit.inc.php                Eintrag bearbeiten
│       ├── release.inc.php             Freischalten
│       ├── statement.inc.php           Admin-Statement
│       ├── admins.inc.php              Admin-Verwaltung
│       ├── admin_email_helpers.inc.php Mail-Helfer (BUG-006)
│       ├── configuration.inc.php       Konfigurations-Editor
│       ├── login.inc.php / logout.inc.php
│       ├── password.inc.php            Recovery-Token-Flow
│       └── password_migrate.php        Auto-Migration für reset_token-Spalten
├── tests/                              PHPUnit-Suites
├── docs/                               Audit- und Plan-Dokumente
└── .docker/                            Compose-Setup
```

---

## Troubleshooting

| Symptom | Ursache / Lösung |
|---------|------------------|
| `install_deu.php` zeigt **HTTP 403** | Lock-File `.installed` existiert. Bei gewünschter Re-Installation manuell löschen. |
| Login schlägt fehl | Initial-Passwort vergessen? `?page=password` für Reset-Link nutzen. |
| Reset-Link kommt nicht an | Mailpit-UI prüfen: http://localhost:8031 (Docker) bzw. SMTP-Konfiguration in `pb_inc/mysql.inc.php`. |
| Admin-Add zeigt "E-Mail konnte NICHT versendet werden" + Passwort | SMTP nicht erreichbar — das angezeigte Passwort sofort sichern, dann SMTP konfigurieren. |
| `?page=guestbook.inc` direkt → 403 | Erwünscht: Direktaufruf von Includes ist seit BUG-013 blockiert (`PB_ENTRY`-Guard). |
| PHP Warnings in `logs/error.log` | Nur Warnungen, keine Fatals. Wenn Fatals: aktualisieren auf neueste Version. |
| `Cannot redeclare function …` beim Test | Zwischen Branches gewechselt? Container neu starten: `docker compose restart web`. |

---

## Lizenz

**MIT License**

```
Copyright (c) 2002 Axel "Expandable" Habermaier (Original PowerBook 1.21)
Copyright (c) 2025-2026 Nico Schubert (PHP 8.4 Migration & Security Updates)
```

Voller Lizenztext: [`LICENSE`](LICENSE).

---

## Kontakt & Support

**SchubertMedia**
Inhaber: Nico Schubert
Stauffenbergallee 57
99085 Erfurt — Deutschland

| Kanal | |
|-------|--|
| Telefon | **+49 (0) 3612 3002247** (Mo.–Fr. 9–12 + 13–18 Uhr) |
| Telefax | +49 (0) 3612 3004636 |
| E-Mail | [info@schubertmedia.de](mailto:info@schubertmedia.de) |
| Webseite | https://www.powerscripts.org |
| Projektbereich | https://www.powerscripts.org/projects-5.html |
| Bug-Tracker | https://github.com/schubertnico/PowerBook/issues |

---

**Original:** PowerBook 1.21 © 2002 by Axel "Expandable" Habermaier
**PHP 8.4 Update:** © 2025–2026 by Nico Schubert / SchubertMedia
