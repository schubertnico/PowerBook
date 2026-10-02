-- PowerBook – Datenbankschema
-- Version 3.1 (PHP 8.4, MySQL 8.0 / MariaDB 10.6) – MIT License
--
-- Einzige Quelle des Schemas: install.php legt die Tabellen aus dieser Datei an,
-- update.php ergänzt bei bestehenden Installationen, was hier neu ist.
-- Kein Standard-Administrator: install.php legt ihn mit selbst gewähltem Passwort an.
--
-- Regeln: jede Tabelle mit Primärschlüssel (sql_require_primary_key), keine
-- Vorgabewerte an TEXT-Spalten (MySQL 8 im Strict Mode lehnt sie ab), Kollation
-- ausdrücklich (MariaDB kennt utf8mb4_0900_ai_ci nicht).

DROP TABLE IF EXISTS pb_login_attempts;
DROP TABLE IF EXISTS pb_entries;
DROP TABLE IF EXISTS pb_config;
DROP TABLE IF EXISTS pb_admins;

CREATE TABLE pb_admins (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(250) NOT NULL,
  password VARCHAR(255) NOT NULL,
  config ENUM('Y','N') NOT NULL DEFAULT 'N',
  `release` ENUM('Y','N') NOT NULL DEFAULT 'Y',
  entries ENUM('Y','N') NOT NULL DEFAULT 'Y',
  admins ENUM('Y','N') NOT NULL DEFAULT 'N',
  reset_token VARCHAR(64) DEFAULT NULL,
  reset_token_expires BIGINT DEFAULT NULL,
  pw_changed BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pb_config (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  title VARCHAR(150) NOT NULL DEFAULT 'Gästebuch',
  `release` ENUM('R','U') NOT NULL DEFAULT 'U',
  send_email ENUM('Y','N') NOT NULL DEFAULT 'Y',
  email VARCHAR(250) NOT NULL DEFAULT '',
  mail_from VARCHAR(250) NOT NULL DEFAULT '',
  date VARCHAR(20) NOT NULL DEFAULT 'd.m.Y',
  time VARCHAR(20) NOT NULL DEFAULT 'H:i',
  spam_check INT NOT NULL DEFAULT 30,
  color VARCHAR(10) NOT NULL DEFAULT '#FF0000',
  show_entries INT NOT NULL DEFAULT 10,
  guestbook_name VARCHAR(250) NOT NULL DEFAULT 'pbook.php',
  admin_url VARCHAR(250) NOT NULL DEFAULT '',
  text_format ENUM('Y','N') NOT NULL DEFAULT 'Y',
  icons ENUM('Y','N') NOT NULL DEFAULT 'Y',
  smilies ENUM('Y','N') NOT NULL DEFAULT 'Y',
  pages ENUM('L','D') NOT NULL DEFAULT 'D',
  use_thanks ENUM('Y','N') NOT NULL DEFAULT 'N',
  language ENUM('ger1','ger2','eng') NOT NULL DEFAULT 'ger1',
  design TEXT NOT NULL,
  thanks_title VARCHAR(250) NOT NULL DEFAULT '',
  thanks TEXT NOT NULL,
  statements ENUM('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pb_entries (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(250) NOT NULL DEFAULT '',
  text TEXT NOT NULL,
  date BIGINT NOT NULL DEFAULT 0,
  homepage VARCHAR(255) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  status ENUM('R','U') NOT NULL DEFAULT 'R',
  icon VARCHAR(100) NOT NULL DEFAULT '',
  smilies ENUM('Y','N') NOT NULL DEFAULT 'Y',
  statement TEXT NOT NULL,
  statement_by VARCHAR(250) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fehlgeschlagene Anmeldungen im AdminCenter (Drossel). Einträge älter als
-- 15 Minuten löscht die Anmeldung selbst.
CREATE TABLE pb_login_attempts (
  id INT NOT NULL AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  name VARCHAR(100) NOT NULL DEFAULT '',
  time BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_ip_time (ip, time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standardkonfiguration. install.php überschreibt Titel, Adressen und Schalter.
INSERT INTO pb_config (id, design, thanks_title, thanks) VALUES (1,
  '<article class="card pb-entry-card shadow-sm"><header class="card-header d-flex flex-wrap justify-content-between align-items-center"><span>(#ICON#)<b>(#DATE#)</b>, <small class="text-body-secondary">(#TIME#) Uhr</small></span><span>(#EMAIL_NAME#)</span></header><div class="card-body">(#TEXT#)</div><footer class="card-footer d-flex flex-wrap justify-content-end gap-3 align-items-center text-end"><span>(#URL#)</span></footer></article>',
  'Danke für Ihren Eintrag!',
  'Hallo (#NAME#),\n\nvielen Dank für Ihren Eintrag in unserem Gästebuch!\n\nMit freundlichen Grüßen');
