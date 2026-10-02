-- PowerBook 1.21 (2002): Tabellen wie von german/install_deu.php angelegt,
-- auf einem MySQL-Server jener Zeit (MyISAM, latin1). Für MySQL 8 ist nur
-- `release` in Backticks gesetzt (seit MySQL 5.0 reserviert).
-- pb_config hat keinen Primärschlüssel; zum Einspielen auf Servern mit
-- sql_require_primary_key vorher SET SESSION sql_require_primary_key = 0.
-- Daten: Standardkonfiguration und Standard-Admin von 1.21 (Passwort
-- „powerbook“ Base64-kodiert), ein Helfer ohne Konfigurationsrecht
-- (Passwort „helfer2003“) und zwei Einträge.

DROP TABLE IF EXISTS pb_login_attempts;
DROP TABLE IF EXISTS pb_entries;
DROP TABLE IF EXISTS pb_config;
DROP TABLE IF EXISTS pb_admins;

CREATE TABLE pb_admins (
  id int(11) NOT NULL auto_increment,
  name varchar(100) NOT NULL,
  email varchar(250) NOT NULL,
  password varchar(100) NOT NULL,
  config enum('PERMITTED','FORBIDDEN') DEFAULT 'FORBIDDEN' NOT NULL,
  `release` enum('PERMITTED','FORBIDDEN') DEFAULT 'PERMITTED' NOT NULL,
  entries enum('PERMITTED','FORBIDDEN') DEFAULT 'PERMITTED' NOT NULL,
  admins enum('PERMITTED','FORBIDDEN') DEFAULT 'FORBIDDEN' NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE pb_config (
  `release` enum('R','U') DEFAULT 'R' NOT NULL,
  send_email enum('Y','N') DEFAULT 'N' NOT NULL,
  email varchar(250) NOT NULL,
  date varchar(20) NOT NULL,
  time varchar(20) NOT NULL,
  spam_check varchar(10) NOT NULL,
  color varchar(10) NOT NULL,
  show_entries varchar(5) NOT NULL,
  guestbook_name varchar(250) NOT NULL,
  admin_url varchar(250) NOT NULL,
  text_format enum('Y','N') DEFAULT 'Y' NOT NULL,
  icons enum('Y','N') DEFAULT 'Y' NOT NULL,
  smilies enum('Y','N') DEFAULT 'Y' NOT NULL,
  icq enum('Y','N') DEFAULT 'Y' NOT NULL,
  pages enum('L','D') DEFAULT 'L' NOT NULL,
  use_thanks enum('Y','N') DEFAULT 'N' NOT NULL,
  language enum('ger1','ger2','eng') DEFAULT 'eng' NOT NULL,
  design text NOT NULL,
  thanks_title varchar(250) NOT NULL,
  thanks text NOT NULL,
  statements enum('Y','N') DEFAULT 'Y' NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE pb_entries (
  id int(11) NOT NULL auto_increment,
  name varchar(100) NOT NULL,
  email varchar(250) NOT NULL,
  text text NOT NULL,
  date varchar(20) NOT NULL,
  homepage varchar(200) NOT NULL,
  icq varchar(20) NOT NULL,
  ip varchar(21) NOT NULL,
  status enum('R','U') DEFAULT 'R' NOT NULL,
  icon varchar(100) NOT NULL,
  smilies enum('Y','N') DEFAULT 'Y' NOT NULL,
  statement text NOT NULL,
  statement_by varchar(250) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO pb_config VALUES ('R', 'N', '', 'l, j F Y', 'H:i', '30', '#FF0000', '10', '', '', 'Y', 'Y', 'Y', 'Y', 'D', 'Y', 'eng', '<table width="560" border="0">
<tr bgcolor="#001329"><td align="left">
(#ICON#)<b>(#DATE#)</b>, <small>(#TIME#)h</small>
</td><td align="right" width="121">
(#EMAIL_NAME#)
</td></tr><tr><td valign="top" bgcolor="#001930">
(#TEXT#)
</td><td width="121" align="right" valign="top" bgcolor="#001329">
(#URL#)<br>
(#ICQ#)
</td></tr></table><br>', 'Thank you for your entry!', 'Hello (#NAME#)!

Thank you for your entry in my guestbook!

Greetings
The Admin', 'Y');

INSERT INTO pb_admins VALUES (1, 'PowerBook', 'powerbook@powerscripts.org', 'cG93ZXJib29r', 'PERMITTED', 'PERMITTED', 'PERMITTED', 'PERMITTED');
INSERT INTO pb_admins VALUES (2, 'Helfer', 'helfer@example.org', 'aGVsZmVyMjAwMw==', 'FORBIDDEN', 'PERMITTED', 'PERMITTED', 'FORBIDDEN');

INSERT INTO pb_entries VALUES (1, 'Alter Gast', 'alt@example.org', 'Eintrag aus dem Jahr 2003', '1057000000', 'http://www.example.org', '12345678', '192.168.0.10', 'R', '', 'Y', '', '');
INSERT INTO pb_entries VALUES (2, 'Möwe', '', 'Grüße von der Küste', '1057100000', '', '', '10.0.0.1', 'U', '', 'Y', 'Schön, danke!', 'PowerBook');
