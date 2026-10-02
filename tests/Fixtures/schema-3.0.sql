-- PowerBook 3.0.0 (Mai 2026): Tabellen wie von install_deu.php 3.0 angelegt
-- (InnoDB, utf8mb4 ohne ausdrückliche Kollation). Abweichung: statement ohne
-- DEFAULT '' (MySQL 8 im Strict Mode lehnt es ab, ERROR 1101).
-- Ohne reset_token-Spalten: Sie entstanden erst bei der ersten Anmeldung im
-- AdminCenter (password_migrate.php). pb_config ohne Primärschlüssel.
-- Standardkonfiguration von 3.0 (Design mit „(#TIME#)h“, Danke-Mail mit
-- „Gruessen“), Admin „PowerBook“ mit Passwort „Moewenblick2026“ und Jannik
-- ohne Konfigurationsrecht (Passwort „Helferlein2026“).

DROP TABLE IF EXISTS pb_login_attempts;
DROP TABLE IF EXISTS pb_entries;
DROP TABLE IF EXISTS pb_config;
DROP TABLE IF EXISTS pb_admins;

CREATE TABLE pb_admins (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(250) NOT NULL,
  password VARCHAR(255) NOT NULL,
  config ENUM('Y','N') DEFAULT 'N' NOT NULL,
  `release` ENUM('Y','N') DEFAULT 'Y' NOT NULL,
  entries ENUM('Y','N') DEFAULT 'Y' NOT NULL,
  admins ENUM('Y','N') DEFAULT 'N' NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pb_config (
  `release` ENUM('R','U') DEFAULT 'R' NOT NULL,
  send_email ENUM('Y','N') DEFAULT 'N' NOT NULL,
  email VARCHAR(250) NOT NULL,
  date VARCHAR(20) NOT NULL,
  time VARCHAR(20) NOT NULL,
  spam_check INT(10) NOT NULL DEFAULT 30,
  color VARCHAR(10) NOT NULL DEFAULT '#FF0000',
  show_entries INT(5) NOT NULL DEFAULT 10,
  guestbook_name VARCHAR(250) NOT NULL,
  admin_url VARCHAR(250) NOT NULL,
  text_format ENUM('Y','N') DEFAULT 'Y' NOT NULL,
  icons ENUM('Y','N') DEFAULT 'Y' NOT NULL,
  smilies ENUM('Y','N') DEFAULT 'Y' NOT NULL,
  pages ENUM('L','D') DEFAULT 'L' NOT NULL,
  use_thanks ENUM('Y','N') DEFAULT 'N' NOT NULL,
  language ENUM('ger1','ger2','eng') DEFAULT 'eng' NOT NULL,
  design TEXT NOT NULL,
  thanks_title VARCHAR(250) NOT NULL,
  thanks TEXT NOT NULL,
  statements ENUM('Y','N') DEFAULT 'Y' NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pb_entries (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(250) DEFAULT '' NOT NULL,
  text TEXT NOT NULL,
  date INT(20) NOT NULL,
  homepage VARCHAR(200) DEFAULT '' NOT NULL,
  ip VARCHAR(45) NOT NULL,
  status ENUM('R','U') DEFAULT 'R' NOT NULL,
  icon VARCHAR(100) DEFAULT '' NOT NULL,
  smilies ENUM('Y','N') DEFAULT 'Y' NOT NULL,
  statement TEXT NOT NULL,
  statement_by VARCHAR(250) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  INDEX idx_status (status),
  INDEX idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO pb_config (`release`, send_email, email, date, time, spam_check, color, show_entries, guestbook_name, admin_url,
  text_format, icons, smilies, pages, use_thanks, language, design, thanks_title, thanks, statements)
VALUES ('R', 'N', '', 'l, j. F Y', 'H:i', 30, '#FF0000', 10, 'pbook.php', '',
  'Y', 'Y', 'Y', 'D', 'N', 'eng',
  '<article class="card pb-entry-card shadow-sm"><header class="card-header d-flex flex-wrap justify-content-between align-items-center"><span>(#ICON#)<b>(#DATE#)</b>, <small class="text-body-secondary">(#TIME#)h</small></span><span>(#EMAIL_NAME#)</span></header><div class="card-body">(#TEXT#)</div><footer class="card-footer d-flex flex-wrap justify-content-end gap-3 align-items-center text-end"><span>(#URL#)</span></footer></article>',
  'Danke für Ihren Eintrag!', 'Hallo (#NAME#)!

Vielen Dank für Ihren Eintrag in meinem Gästebuch!

Mit freundlichen Gruessen
Der Admin', 'Y');

INSERT INTO pb_admins (id, name, email, password, config, `release`, entries, admins)
VALUES (1, 'PowerBook', 'admin@example.com', '$2y$12$hc65uUuEtw.VnAg/mViC5.YfZFJsliLkp1MKkBUSAcyjRISfDBsIS', 'Y', 'Y', 'Y', 'Y');
INSERT INTO pb_admins (id, name, email, password, config, `release`, entries, admins)
VALUES (2, 'Jannik', 'jannik@example.org', '$2y$12$ahp2zPUK0WkV6QBQBzRjzOQ.HOiBNRr50.xYkzwMK7IjHvcuyk2aG', 'N', 'Y', 'Y', 'N');

INSERT INTO pb_entries (name, email, text, date, homepage, ip, status, icon, smilies, statement, statement_by)
VALUES ('Anke', 'anke@example.org', 'Herzlich willkommen im Gästebuch!', 1778400000, '', '192.0.2.20', 'R', '', 'Y', 'Danke!', 'PowerBook');
INSERT INTO pb_entries (name, email, text, date, homepage, ip, status, icon, smilies, statement, statement_by)
VALUES ('Werbung', 'spam@example.net', 'Billige Uhren', 1778400100, 'https://spam.example.net', '198.51.100.7', 'U', '', 'N', '', '');
