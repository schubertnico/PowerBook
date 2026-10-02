-- PowerBook 2.0 (April 2026): Tabellen wie von install_deu.php 2.0 angelegt
-- (InnoDB, utf8mb4 ohne ausdrückliche Kollation). Abweichung: statement ohne
-- DEFAULT '' – MySQL 8 im Strict Mode lehnt das ab (ERROR 1101), MariaDB und
-- MySQL ohne Strict Mode legen die Spalte ohne bzw. mit leerer Vorgabe an.
-- reset_token und reset_token_expires (INT) hat password_migrate.php bei der
-- ersten Anmeldung angehängt. pb_config ohne Primärschlüssel.
-- Admin „PowerBook“, Passwort „Altbestand2020“.

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
  reset_token VARCHAR(64) DEFAULT NULL,
  reset_token_expires INT DEFAULT NULL,
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
  icq ENUM('Y','N') DEFAULT 'N' NOT NULL,
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
  icq VARCHAR(20) DEFAULT '' NOT NULL,
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
  text_format, icons, smilies, icq, pages, use_thanks, language, design, thanks_title, thanks, statements)
VALUES ('R', 'N', 'gastgeber@example.org', 'l, j. F Y', 'H:i', 30, '#FF0000', 10, 'pbook.php', '',
  'Y', 'Y', 'Y', 'N', 'D', 'N', 'eng', '<table width="560" border="0">
<tr bgcolor="#001329"><td align="left">
(#ICON#)<b>(#DATE#)</b>, <small>(#TIME#)h</small>
</td><td align="right" width="121">
(#EMAIL_NAME#)
</td></tr><tr><td valign="top" bgcolor="#001930">
(#TEXT#)
</td><td width="121" align="right" valign="top" bgcolor="#001329">
(#URL#)<br>
(#ICQ#)
</td></tr></table><br>', 'Danke für Ihren Eintrag!', 'Hallo (#NAME#)!

Vielen Dank für Ihren Eintrag in meinem Gästebuch!

Mit freundlichen Grüßen
Der Admin', 'Y');

INSERT INTO pb_admins (id, name, email, password, config, `release`, entries, admins)
VALUES (1, 'PowerBook', 'gastgeber@example.org', '$2y$12$FlqIZWkcDYu9EWNv3XO0oOv4R53ic.7KEV4ONWClqpBwArZ/EeJXC', 'Y', 'Y', 'Y', 'Y');

INSERT INTO pb_entries (name, email, text, date, homepage, icq, ip, status, icon, smilies, statement, statement_by)
VALUES ('Gast aus 2.0', 'gast@example.org', 'Schöner Urlaub auf Föhr!', 1745000000, 'https://www.example.org', '', '192.0.2.10', 'R', '', 'Y', '', '');
