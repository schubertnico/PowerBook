# PowerBook - PHP Gästebuch-System

Ein klassisches PHP-Gästebuch-System, ursprünglich entwickelt von Axel "Expandable" Habermaier im Jahr 2002, modernisiert für PHP 8.4.

**Repository:** https://github.com/schubertnico/PowerBook.git

## Projektbeschreibung

PowerBook ist ein einfaches, aber funktionales Gästebuch-System mit folgenden Features:

- Gästebuch-Einträge mit Name, E-Mail, Homepage und Text
- Admin-Center zur Verwaltung von Einträgen und Benutzern
- Spam-Schutz durch IP-basierte Zeitsperre
- BBCode-ähnliche Textformatierung
- Smilies/Emoticons
- E-Mail-Benachrichtigungen bei neuen Einträgen
- Mehrseitige Darstellung (Pagination)

## Voraussetzungen

- PHP 8.4 oder höher
- MySQL 8.0 oder höher
- Apache mit mod_rewrite (oder Docker)

## Setup

### Mit Docker (empfohlen)

1. Repository klonen:
   ```bash
   git clone https://github.com/schubertnico/PowerBook.git
   cd PowerBook
   ```

2. Docker-Container starten:
   ```bash
   cd .docker
   docker compose build
   docker compose up -d
   ```

3. Installation aufrufen:
   - Öffnen Sie http://localhost:8080/install_deu.php
   - Klicken Sie auf "Installation starten"
   - **Wichtig:** Löschen Sie `install_deu.php` nach der Installation!

4. Gästebuch nutzen:
   - Gästebuch: http://localhost:8080/pbook.php
   - Admin-Center: http://localhost:8080/pb_inc/admincenter/

### Ohne Docker

1. Dateien auf den Webserver kopieren
2. `pb_inc/mysql.inc.php` anpassen:
   ```php
   $config_sql_server   = 'localhost';
   $config_sql_user     = 'ihr_benutzer';
   $config_sql_password = 'ihr_passwort';
   $config_sql_database = 'ihre_datenbank';
   ```
3. Installation über `install_deu.php` ausführen

## Start

### Docker-Befehle

```bash
# Container starten
cd .docker
docker compose up -d

# Container stoppen
docker compose down

# Logs anzeigen
docker compose logs -f

# Status prüfen
docker compose ps
```

### Ports

| Service | Port | Beschreibung |
|---------|------|--------------|
| Web (PHP/Apache) | 8080 | Gästebuch-Anwendung |
| MySQL | 3314 | Datenbank |
| Mailpit SMTP | 1031 | Test-Mailserver (SMTP) |
| Mailpit Web | 8031 | Test-Mailserver (Web-UI) |

## Nutzung

### Standard-Login

Nach der Installation:
- **Benutzername:** PowerBook
- **Passwort:** powerbook

**Wichtig:** Ändern Sie das Passwort nach dem ersten Login!

### Test-E-Mails prüfen

Alle vom System gesendeten E-Mails werden von Mailpit abgefangen:
- Öffnen Sie http://localhost:8031
- Hier sehen Sie alle gesendeten Test-E-Mails

### Development Tools

```bash
# Composer-Abhängigkeiten installieren
composer install

# PHPStan (Statische Analyse) ausführen
composer run phpstan

# Rector (Code-Migration) - Dry Run
composer run rector-dry

# Rector ausführen
composer run rector
```

## Lizenz

MIT License

Copyright (c) 2002 Axel "Expandable" Habermaier (Original PowerBook 1.21)
Copyright (c) 2025 Nico Schubert (PHP 8.4 Migration & Security Updates)

Siehe [LICENSE](LICENSE) für Details.

## Änderungen / Migration auf PHP 8.4

### PHP 8.4 Anpassungen

- `declare(strict_types=1)` in allen PHP-Dateien
- Ersetzung aller `mysql_*` Funktionen durch PDO mit Prepared Statements
- Ersetzung von `ereg()`, `eregi()`, `ereg_replace()`, `eregi_replace()` durch `preg_match()` und `preg_replace()`
- Entfernung der `register_globals` Emulation (`extract($HTTP_*_VARS)`)
- Verwendung von `$_GET`, `$_POST`, `$_COOKIE`, `$_SESSION` statt magischer Variablen
- Null-Coalescing Operator (`??`) für sichere Defaults
- Type Hints und return types wo möglich

### Sicherheitsfixes

- **SQL Injection:** Alle Queries verwenden jetzt PDO Prepared Statements
- **XSS (Cross-Site Scripting):** Konsequentes Output-Escaping mit `htmlspecialchars()`
- **CSRF:** Token-basierter Schutz für alle Formulare
- **Passwort-Sicherheit:**
  - Base64-Kodierung durch `password_hash()` / `password_verify()` ersetzt
  - Automatische Migration alter Passwörter beim Login
- **Local File Inclusion (LFI):** Whitelist für Admin-Seiten
- **Session-Sicherheit:** Keine Passwörter mehr in Cookies, PHP-Sessions stattdessen
- **E-Mail Header Injection:** Sanitisierung aller E-Mail-Header

### Strukturelle Änderungen

- Neue Datei: `pb_inc/csrf.inc.php` - CSRF-Token-Funktionen
- Neue Datei: `pb_inc/database.inc.php` - PDO-Datenbankverbindung
- Aktualisierte Datei: `pb_inc/functions.inc.php` - Hilfsfunktionen mit Type Hints
- Docker-Konfiguration in `.docker/`
- Composer, PHPStan und Rector Konfiguration

### Geänderte Dateien

- `pbook.php` - MIT-Header, strict_types, HTML5
- `install_deu.php` - PDO, password_hash, HTML5
- `pb_inc/mysql.inc.php` - Docker-kompatible Defaults
- `pb_inc/mysql-connect.inc.php` - PDO statt mysql_*
- `pb_inc/config.inc.php` - PDO, keine extract()
- `pb_inc/guestbook.inc.php` - PDO, Prepared Statements, XSS-Fix, CSRF
- `pb_inc/entry.inc.php` - preg_replace statt ereg, XSS-Fix
- `pb_inc/form.inc.php` - CSRF-Token, XSS-Escaping
- `pb_inc/pages.inc.php` - preg_replace, sichere URL-Generierung
- `pb_inc/send-email.php` - Header Injection Fix
- `pb_inc/thank-email.php` - Header Injection Fix, preg_replace
- `pb_inc/admincenter/index.php` - PDO, LFI-Fix, Session-Auth, CSRF
- `pb_inc/admincenter/config.inc.php` - Vereinfacht, verwendet Haupt-Config
- `pb_inc/admincenter/login.inc.php` - CSRF, sichere Passwort-Prüfung
- `pb_inc/admincenter/home.inc.php` - Aktualisierte Info-Seite

## So prüfen Sie, ob es läuft

1. Docker-Status prüfen:
   ```bash
   cd .docker
   docker compose ps
   ```
   Alle Container sollten "Up" sein.

2. Logs prüfen:
   ```bash
   docker compose logs web
   ```

3. PHP Error Log prüfen:
   ```bash
   cat ../logs/php-error.log
   ```
   (oder `type ..\logs\php-error.log` unter Windows)

4. Web-Zugriff testen:
   - http://localhost:8080/pbook.php - Gästebuch
   - http://localhost:8080/pb_inc/admincenter/ - Admin-Center
   - http://localhost:8031 - Mailpit (Test-E-Mails)

## Support

Bei Fragen oder Problemen:
- GitHub Issues: https://github.com/schubertnico/PowerBook.git

---

**Original:** PowerBook 1.21 © 2002 by Axel Habermaier
**PHP 8.4 Update:** © 2025 by Nico Schubert
