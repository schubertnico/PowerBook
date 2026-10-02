# PowerBook — PHP-Gästebuch-System

[![Version](https://img.shields.io/badge/version-3.1.0-blue.svg)](https://github.com/schubertnico/PowerBook/releases/tag/v3.1.0)
[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

> Klassisches PHP-Gästebuch — ursprünglich 2002 von **Axel "Expandable" Habermaier** entwickelt, auf **PHP 8.4** modernisiert, sicherheitsgehärtet und mit **Bootstrap 5** gestaltet. Version **3.1.0** bringt einen Installer mit Datenbankformular und `update.php` für Bestandsinstallationen ab PowerBook 1.x.

**Projekt-Webseite:** https://www.powerscripts.org
**Projektbereich:** https://www.powerscripts.org/projects-5.html
**Repository:** https://github.com/schubertnico/PowerBook

---

## Inhaltsverzeichnis

- [Features](#features)
- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Aktualisierung von 1.x, 2.0 oder 3.0](#aktualisierung-von-1x-20-oder-30)
- [Entwicklung mit Docker](#entwicklung-mit-docker)
- [AdminCenter](#admincenter)
- [Sicherheit](#sicherheit)
- [Tests](#tests)
- [Projektstruktur](#projektstruktur)
- [Fehlersuche](#fehlersuche)
- [Lizenz](#lizenz)
- [Changelog](#changelog)
- [Kontakt & Support](#kontakt--support)

---

## Features

- **Gästebuch** für Besucher: Eintrag schreiben (mit Vorschau), lesen, suchen, blättern
- **AdminCenter** mit Rechten je Konto: Einträge bearbeiten und löschen, Einträge freischalten, Admins verwalten, Konfiguration ändern
- **Freischaltung** neuer Einträge (gegen Werbung) und **Benachrichtigung** per E-Mail
- **BBCode** (`[b]`, `[i]`, `[u]`, `[small]`), automatische Links, **Smileys** und **Icons**
- **Spam-Sperre** je IP-Adresse mit einstellbarer Wartezeit
- **Passwort vergessen** per Mail-Link, sichere Passwort-Hashes
- **Installer** in vier Schritten und **update.php** für ältere Versionen
- Durchgängig **PHP 8.4** mit `declare(strict_types=1)`, **Bootstrap 5.3**

---

## Voraussetzungen

| Komponente | Mindestversion |
|------------|----------------|
| PHP | **8.4** mit den Erweiterungen `pdo_mysql` und `mbstring` |
| Datenbank | **MySQL 8.0** oder **MariaDB 10.6** |
| Webserver | Apache 2.4 mit aktiver `.htaccess` (empfohlen) oder NGINX + PHP-FPM |

Der Installer prüft PHP-Version, Erweiterungen und Schreibrechte selbst und zeigt, was fehlt.

---

## Installation

1. **Paket entpacken und hochladen.** Laden Sie den Inhalt des Release-ZIPs per FTP in einen Ordner Ihres Webspace, zum Beispiel `/gaestebuch/`. Das Paket enthält keine Zugangsdaten – die Datei `pb_inc/mysql.inc.php` legt erst der Installer an.
2. **Schreibrechte setzen.** Der Ordner `pb_inc/` und der PowerBook-Ordner selbst müssen für PHP beschreibbar sein (meist `chmod 755`, bei manchen Hostern `775`). `logs/` sollte ebenfalls beschreibbar sein.
3. **Installer aufrufen:** `https://ihre-domain.tld/gaestebuch/install.php`
   - **Willkommen:** prüft die Voraussetzungen.
   - **Schritt 1 – Datenbank:** Datenbankserver, Port, Datenbankname, Benutzername und Passwort vom Hoster. Der Installer testet die Verbindung sofort und erklärt Fehler verständlich. Gibt es in der Datenbank schon PowerBook-Tabellen, löscht er sie nur nach ausdrücklicher Bestätigung.
   - **Schritt 2 – Gästebuch:** Name des Gästebuchs, Adresse (daraus entsteht der Link zum AdminCenter in Mails), E-Mail-Adresse für Benachrichtigungen. Benachrichtigung und Freischaltung sind vorab eingeschaltet.
   - **Schritt 3 – Administrator:** Name, E-Mail-Adresse und Passwort (mindestens 8 Zeichen) des ersten Kontos. Es hat alle Rechte und lässt sich nicht löschen (Superadmin).
   - **Fertig:** Tabellen, Konfiguration, `pb_inc/mysql.inc.php` und die Sperrdatei `install.lock` sind angelegt. Der Knopf **„install.php jetzt löschen“** entfernt den Installer vom Server.
4. Anmelden im AdminCenter unter `https://ihre-domain.tld/gaestebuch/pb_inc/admincenter/`.

**Ohne Schreibrecht für `pb_inc/`:** Speichern Sie die Vorlage `pb_inc/mysql.inc.php.example` als `pb_inc/mysql.inc.php`, tragen Sie die Zugangsdaten ein und laden Sie die Datei hoch. Der Installer übernimmt die Werte dann aus der Datei und fragt nur noch nach Gästebuch und Administrator.

**Neu installieren:** `install.lock` und `pb_inc/mysql.inc.php` löschen, `install.php` erneut hochladen und aufrufen.

> Unter NGINX greift die `.htaccess` nicht. Sperren Sie dort `pb_inc/*.inc.php`, `logs/`, `*.sql`, `*.lock`, `*.example`, `install_deu.php` und – nach der Installation – `install.php` in der Serverkonfiguration.

---

## Aktualisierung von 1.x, 2.0 oder 3.0

1. **Sicherung anlegen**, zum Beispiel in phpMyAdmin über „Exportieren“.
2. **Alle Dateien von 3.1 hochladen** und die vorhandenen überschreiben. Die bestehende `pb_inc/mysql.inc.php` bleibt erhalten, weil das Paket keine enthält.
3. **`update.php` aufrufen**, zum Beispiel `https://ihre-domain.tld/gaestebuch/update.php`. Die Seite zeigt vorher, was sie erledigt, und startet erst nach Anmeldung mit einem Konto, das die Konfiguration ändern darf (in PowerBook 1.x: Ihr bisheriges Admin-Konto).
4. Danach **„update.php jetzt löschen“** klicken.

`update.php` ergänzt nur und lässt sich beliebig oft aufrufen; Einträge, Admins und Einstellungen bleiben erhalten. Je nach Ausgangsversion erledigt es:

| Aufgabe | 1.x | 2.0 | 3.0 |
|---------|:---:|:---:|:---:|
| Primärschlüssel für `pb_config` (verlangen manche MySQL-Server) | ✓ | ✓ | ✓ |
| Tabellen von MyISAM auf InnoDB umstellen | ✓ | | |
| Kollation `utf8mb4_unicode_ci` (Sicherungen lassen sich auch in MariaDB einspielen) | | ✓ | ✓ |
| Rechte `PERMITTED`/`FORBIDDEN` → `Y`/`N` | ✓ | | |
| Neue Spalten: Titel, Absenderadresse, Passwort-Reset, Passwortwechsel | ✓ | ✓ | ✓ |
| Tabelle `pb_login_attempts` (Schutz vor dem Durchprobieren von Passwörtern) | ✓ | ✓ | ✓ |
| Zahlen- und Zeitspalten (Datum nach dem 19.01.2038, IPv6-Adressen, lange Homepage-Adressen) | ✓ | ✓ | ✓ |
| Vorgabewerte für alte Spalten (z. B. `icq`), damit neue Einträge klappen | ✓ | ✓ | ✓ |
| Adresse des AdminCenters eintragen, falls leer | ✓ | ✓ | ✓ |
| Unverändertes Standarddesign und Danke-Mail durch die neuen ersetzen („(#TIME#) Uhr“) | ✓ | ✓ | ✓ |
| Sperrdatei `install.lock` anlegen, alten Installer `install_deu.php` löschen | ✓ | ✓ | ✓ |

Tabellen mit altem Zeichensatz (latin1) stellt `update.php` bewusst nicht um und weist nur darauf hin. Passwörter im alten Format von 1.x werden bei der nächsten Anmeldung im AdminCenter sicher gespeichert.

Die Datei `install_deu.php` ist in 3.1 nur noch ein Platzhalter, der auf `install.php` weiterleitet; die `.htaccess` sperrt sie zusätzlich.

---

## Entwicklung mit Docker

Das Repository enthält unter `.docker/` einen Entwicklungsstack:

| Service | Image | Port (Host) |
|---------|-------|-------------|
| `web` | Apache 2.4 + PHP 8.4 | **8081** |
| `db` | MySQL 8.0 | 3314 |
| `mail` | Mailpit | SMTP **1035**, Web-Oberfläche **8035** |

```bash
git clone https://github.com/schubertnico/PowerBook.git
cd PowerBook/.docker
docker compose up -d --build
```

Danach `http://localhost:8081/install.php` aufrufen und im Schritt „Datenbank“ eintragen: Datenbankserver `db`, Port `3306`, Datenbank `powerbook`, Benutzer `powerbook`, Passwort `powerbook_secret`. Testmails landen in Mailpit unter `http://localhost:8035`.

---

## AdminCenter

URL: `https://ihre-domain.tld/gaestebuch/pb_inc/admincenter/`

| Menüpunkt | Zweck |
|-----------|-------|
| **Start** | Übersicht mit Zahl der freigeschalteten und wartenden Einträge |
| **Einträge** | Einträge bearbeiten, beantworten und löschen |
| **Freischalten** | Wartende Einträge freischalten oder löschen |
| **Admins** | Konten anlegen, bearbeiten, löschen und Rechte vergeben |
| **Konfiguration** | Titel, Absenderadresse, Benachrichtigung, Freischaltung, Spam-Sperre, Anzeige, Design der Einträge |
| **Mein Konto** | Eigenen Namen, E-Mail-Adresse und Passwort ändern |
| **Lizenz** | MIT-Lizenztext |

Jedes Konto sieht nur die Menüpunkte, für die es Rechte hat. **Passwort vergessen?** Auf der Anmeldeseite den Link anklicken, Name oder E-Mail-Adresse eingeben – der Link zum Festlegen eines neuen Passworts kommt per Mail.

---

## Sicherheit

| Bereich | Maßnahme |
|---------|----------|
| SQL-Injection | PDO mit Prepared Statements |
| XSS | Konsequentes Escaping aller Ausgaben |
| CSRF | Token in jedem Formular, auch im Installer und in `update.php` |
| Anmeldung | Passwort-Hashes, neue Sitzungs-ID nach der Anmeldung, Drossel bei Fehlversuchen |
| Installer | Nur POST-Formulare mit Token, gesperrt durch `install.lock` (PHP und `.htaccess`), löscht vorhandene Tabellen nur nach Bestätigung, kein Standardpasswort |
| Zugangsdaten | `pb_inc/mysql.inc.php` ist nicht Teil des Pakets und per `.htaccess` gesperrt |
| Fehlermeldungen | Datenbankfehler ohne Benutzername, Server und IP-Adresse; Details nur in `logs/error.log` |
| Dateien | `.htaccess` sperrt Includes, Logs, Schema, Sperrdateien und Werkzeug-Dateien; deutsche Fehlerseiten für 403 und 404 |
| Cookies | `HttpOnly`, `SameSite=Lax`, `Secure` unter HTTPS |

Den `Server`-Kopf mit Versionsnummer schaltet nur die Serverkonfiguration ab (`ServerTokens Prod`), nicht die `.htaccess`.

---

## Tests

```bash
composer install

# Alle Tests (Unit + Integration)
vendor/bin/phpunit

# Installer, update.php und Schema gegen einen echten Datenbankserver
PB_TEST_DB_HOST=127.0.0.1 PB_TEST_DB_PORT=3306 PB_TEST_DB_USER=root PB_TEST_DB_PASSWORD=geheim vendor/bin/phpunit
```

Die meisten Tests laufen mit SQLite im Speicher. Tests für Installer, `update.php` (mit den Tabellen von PowerBook 1.21, 2.0 und 3.0 unter `tests/Fixtures/`) und Schema brauchen einen MySQL- oder MariaDB-Server und werden ohne `PB_TEST_DB_HOST` übersprungen. Der Benutzer muss Datenbanken und Benutzer anlegen dürfen; die Testdatenbanken beginnen mit `pbtest_` (änderbar über `PB_TEST_DB_PREFIX`). Die CI prüft mit MySQL 8.0 und MariaDB 10.6.

Statische Analyse und Codestil:

```bash
composer phpstan
composer psalm
composer phpmd
composer cs-check
```

---

## Projektstruktur

```
PowerBook/
├── pbook.php                       Gästebuch
├── install.php                     Installer (Einstieg)
├── update.php                      Aktualisierung (Einstieg)
├── install_deu.php                 Platzhalter für den alten Installer
├── powerbook.sql                   Datenbankschema (einzige Quelle)
├── pb_inc/
│   ├── mysql.inc.php.example       Vorlage für die Zugangsdaten
│   ├── install.inc.php             Installer: Schritte und Seiten
│   ├── update.inc.php              Aktualisierung: Plan und Ausführung
│   ├── setup.inc.php               Gemeinsame Helfer, Seiten „noch nicht eingerichtet“
│   ├── config.inc.php              Konfiguration aus der Datenbank
│   ├── database.inc.php            Datenbankverbindung (PDO)
│   ├── mysql-connect.inc.php       Verbindung für Gästebuch und AdminCenter
│   ├── guestbook.inc.php           Gästebuch-Logik
│   ├── mail.inc.php                Mailversand
│   └── admincenter/                AdminCenter
├── tests/                          PHPUnit (Unit, Integration, Fixtures)
├── docs/                           Dokumentation
└── .docker/                        Entwicklungsstack
```

---

## Fehlersuche

| Symptom | Ursache / Lösung |
|---------|------------------|
| „PowerBook ist noch nicht eingerichtet“ | Es gibt noch keine `pb_inc/mysql.inc.php`. `install.php` aufrufen. |
| „PowerBook ist bereits installiert“ | `install.lock` sperrt den Installer. Bestehende Installation: `update.php`. Neu installieren: `install.lock` und `pb_inc/mysql.inc.php` löschen. |
| „PowerBook ist bereits eingerichtet“ | `pb_inc/mysql.inc.php` gehört zu einer Datenbank mit Administrator. `update.php` aufrufen. |
| „Die Datenbank ist gerade nicht erreichbar“ | Datenbankserver aus oder Zugangsdaten in `pb_inc/mysql.inc.php` falsch. Die genaue Meldung steht in `logs/error.log`. |
| Hinweis im AdminCenter „Die Datenbank ist noch auf einem älteren Stand“ | `update.php` hochladen und aufrufen. |
| Mails kommen nicht an | Absenderadresse unter „Konfiguration“ auf eine Adresse Ihrer Domain setzen; im Docker-Stack Mailpit prüfen (`http://localhost:8035`). |

---

## Lizenz

**MIT License**

```
Copyright (c) 2002 Axel "Expandable" Habermaier (Original PowerBook 1.21)
Copyright (c) 2025-2026 Nico Schubert (PHP 8.4 Migration & Security Updates)
```

Voller Lizenztext: [`LICENSE`](LICENSE).

---

## Changelog

### v3.1.0 — 2026-10-02

#### Installation und Aktualisierung
- **Neuer Installer `install.php`** in vier Schritten (Datenbank, Gästebuch, Administrator, Fertig) mit Verbindungstest, verständlichen Fehlermeldungen, Prüfung vorhandener Tabellen und selbst gewähltem Administrator-Passwort. Er schreibt `pb_inc/mysql.inc.php` und die Sperrdatei `install.lock` und bietet an, sich danach selbst zu löschen.
- **Neues `update.php`** für PowerBook 1.x, 2.0 und 3.0: zeigt den Plan, startet nach Anmeldung, ergänzt Tabellen, Spalten, Indizes und Einstellungen und legt `install.lock` an.
- **`powerbook.sql`** als einzige Quelle des Schemas: läuft unter MySQL 8 im Strict Mode und mit `sql_require_primary_key`, ausdrückliche Kollation, Datum als `BIGINT`, deutsche Vorgaben.
- `pb_inc/mysql.inc.php` gehört nicht mehr zum Paket – ein Update per FTP überschreibt keine Zugangsdaten mehr. Vorlage: `pb_inc/mysql.inc.php.example`.
- Der alte Installer `install_deu.php` ist nur noch ein Platzhalter und per `.htaccess` gesperrt.
- Ohne Einrichtung bzw. ohne erreichbare Datenbank zeigen Gästebuch und AdminCenter eine kleine Seite statt einer Fehlermeldung mit Zugangsdaten.

#### Weitere Änderungen
- Gästebuch, AdminCenter und Mails überarbeitet: Titel des Gästebuchs und Absenderadresse einstellbar, Mails mit korrekt kodiertem Kopf, echte Umlaute, Rechte und Menü des AdminCenters eindeutig, Bereich „Mein Konto“.
- Release-Archiv ohne Tests, Dokumentation und Entwicklungswerkzeuge (`.gitattributes`).
- Acht Video-Anleitungen von der Installation bis zum Update: https://www.powerscripts.org/projects-5.html

### v3.0.0 — 2026-05-10

Bootstrap-5-Oberfläche für Gästebuch und AdminCenter, ICQ entfernt, keine Versionsnummern und Personennamen mehr in der Ausgabe.

### Frühere Versionen

- **PowerBook PHP 8.4 Update (2025–2026):** Migration auf PHP 8.4, Sicherheits-Audit, CSRF, Prepared Statements, `password_hash`, neue Test-Suite.
- **PowerBook 1.21 (2002):** Original-Release von Axel „Expandable“ Habermaier.

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
