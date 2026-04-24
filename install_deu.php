<?php
/**
 * PowerBook - PHP Guestbook System
 * Installation Script (German)
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// BUG-011: Lock-File-Check. Nach erfolgreicher Installation wird '.installed'
// erstellt. Beim naechsten Aufruf: HTTP 403 und kurze Info-Seite.
$lockFile = __DIR__ . '/.installed';
if (file_exists($lockFile)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">'
       . '<title>403 - Installation already completed</title></head><body>'
       . '<h1>403 - Installation already completed</h1>'
       . '<p>PowerBook wurde bereits installiert. Loeschen Sie '
       . htmlspecialchars($lockFile, ENT_QUOTES, 'UTF-8')
       . ' manuell, falls Sie neu installieren moechten.</p>'
       . '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerBook - Installation</title>
    <style type="text/css">
        body {
            scrollbar-arrow-color: #001329;
            scrollbar-base-color: #3F5070;
        }
        td {
            font-size: 13px;
            font-family: Arial, sans-serif;
        }
        b {
            font-size: 12px;
        }
        b.script {
            font-size: 12px;
            font-weight: normal;
            color: #B0B0B0;
        }
        div.headline {
            font-size: 40px;
            font-family: Arial;
            font-weight: bold;
        }
        input, textarea, select {
            color: #FFFFFF;
            border-width: 1px;
            border-color: #6078A0;
            border-style: solid;
            background: #001329;
        }
        small {
            font-size: 11px;
        }
        hr {
            color: #001329;
        }
        .success { color: #00FF00; }
        .error { color: #FF0000; }
    </style>
</head>
<body bgcolor="#002040" topmargin="10" bottommargin="10" leftmargin="10" rightmargin="10" text="#ffffff" link="#B5C3D9" vlink="#B5C3D9" alink="#B5C3D9" marginwidth="0" marginheight="0">

<div align="center">
<table border="0" cellpadding="5" cellspacing="1" width="90%" bgcolor="#6078A0">
<tr bgcolor="#001329"><td align="center">
    <div class="headline">PowerBook</div>
    <span style="font-size: 11px;">Installation (PHP 8.4 Version)</span>
</td></tr>
<tr bgcolor="#001930"><td>

<?php
$install = $_GET['install'] ?? '';

if ($install === 'yes') {
    require_once __DIR__ . '/pb_inc/mysql.inc.php';
    require_once __DIR__ . '/pb_inc/database.inc.php';

    try {
        $pdo = getDatabase();

        // Drop existing tables
        echo 'Lösche alte Tabellen (falls vorhanden)...<br>';
        $pdo->exec("DROP TABLE IF EXISTS {$pb_entries}");
        $pdo->exec("DROP TABLE IF EXISTS {$pb_config}");
        $pdo->exec("DROP TABLE IF EXISTS {$pb_admin}");
        echo "<span class='success'>Abgeschlossen.</span><br><br>";

        // Create admin table
        echo "Erstelle Tabelle <b>{$pb_admin}</b>...";
        $pdo->exec("CREATE TABLE {$pb_admin} (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(250) NOT NULL,
            password VARCHAR(255) NOT NULL,
            config ENUM('Y','N') DEFAULT 'N' NOT NULL,
            `release` ENUM('Y','N') DEFAULT 'Y' NOT NULL,
            entries ENUM('Y','N') DEFAULT 'Y' NOT NULL,
            admins ENUM('Y','N') DEFAULT 'N' NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo " <span class='success'>Abgeschlossen.</span><br>";

        // Create config table
        echo "Erstelle Tabelle <b>{$pb_config}</b>...";
        $pdo->exec("CREATE TABLE {$pb_config} (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo " <span class='success'>Abgeschlossen.</span><br>";

        // Create entries table
        echo "Erstelle Tabelle <b>{$pb_entries}</b>...";
        $pdo->exec("CREATE TABLE {$pb_entries} (
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
            statement TEXT NOT NULL DEFAULT '',
            statement_by VARCHAR(250) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            INDEX idx_status (status),
            INDEX idx_ip (ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo " <span class='success'>Abgeschlossen.</span><br>";

        // Insert standard config
        echo 'Füge Standard-Konfiguration ein...';
        $defaultDesign = '<table width="560" border="0">
<tr bgcolor="#001329"><td align="left">
(#ICON#)<b>(#DATE#)</b>, <small>(#TIME#)h</small>
</td><td align="right" width="121">
(#EMAIL_NAME#)
</td></tr><tr><td valign="top" bgcolor="#001930">
(#TEXT#)
</td><td width="121" align="right" valign="top" bgcolor="#001329">
(#URL#)<br>
(#ICQ#)
</td></tr></table><br>';

        $defaultThanks = 'Hallo (#NAME#)!

Vielen Dank für Ihren Eintrag in meinem Gästebuch!

Mit freundlichen Grüßen
Der Admin';

        $stmt = $pdo->prepare("INSERT INTO {$pb_config} VALUES (
            'R', 'N', '', 'l, j. F Y', 'H:i', 30, '#FF0000', 10, 'pbook.php', '',
            'Y', 'Y', 'Y', 'N', 'D', 'N', 'eng', :design, 'Danke für Ihren Eintrag!', :thanks, 'Y'
        )");
        $stmt->execute([':design' => $defaultDesign, ':thanks' => $defaultThanks]);
        echo " <span class='success'>Abgeschlossen.</span><br>";

        // IMP-006: Zufaelliges Initial-Passwort statt hardcoded 'powerbook'.
        echo 'Erstelle Standard-Admin...';
        $initialAdminPassword = bin2hex(random_bytes(8)); // 16 hex chars
        $adminPassword = password_hash($initialAdminPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO {$pb_admin} (name, email, password, config, `release`, entries, admins) VALUES (:name, :email, :password, 'Y', 'Y', 'Y', 'Y')");
        $stmt->execute([
            ':name' => 'PowerBook',
            ':email' => 'admin@example.com',
            ':password' => $adminPassword,
        ]);
        echo " <span class='success'>Abgeschlossen.</span><br>";

        // BUG-011: Lock-File setzen, damit install_deu.php nicht erneut aufgerufen werden kann.
        @file_put_contents(__DIR__ . '/.installed', date('c') . "\n");

        echo '<br><br>';
        echo "<div style='background: #003300; padding: 15px; border: 2px solid #00FF00;'>";
        echo "<b class='success'>Installation erfolgreich!</b><br><br>";
        echo 'Bitte <b>löschen Sie diese Datei</b> (install_deu.php) aus Sicherheitsgründen.<br><br>';
        echo 'Bearbeiten Sie die Konfiguration und Ihre Admin-Daten im <a href="pb_inc/admincenter/">AdminCenter</a>.<br><br>';
        echo 'Login-Daten:<br>';
        echo '- <b>Name:</b> PowerBook<br>';
        echo '- <b>Passwort:</b> <code style="background:#000;padding:3px 6px;font-family:monospace;">' . htmlspecialchars($initialAdminPassword, ENT_QUOTES, 'UTF-8') . '</code><br><br>';
        echo '<b>Wichtig:</b> Notieren Sie dieses Passwort JETZT &mdash; es wird nicht erneut angezeigt! &Auml;ndern Sie es nach dem ersten Login.';
        echo '</div>';

    } catch (PDOException $e) {
        echo "<br><span class='error'><b>Fehler bei der Installation:</b><br>";
        echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '</span><br><br>';
        echo 'Bitte überprüfen Sie die Datenbankeinstellungen in <b>pb_inc/mysql.inc.php</b>.';
    }

} else {
    ?>

<h2>Willkommen zur Installation von PowerBook!</h2>

<p>Diese Datei erstellt automatisch alle benötigten MySQL-Tabellen.</p>

<h3>Vor der Installation:</h3>
<ol>
    <li>Stellen Sie sicher, dass die Datei <b>pb_inc/mysql.inc.php</b> korrekt konfiguriert ist</li>
    <li>Die MySQL-Datenbank muss existieren und der Benutzer muss Schreibrechte haben</li>
    <li>Bei Verwendung von Docker: Starten Sie zuerst die Container mit <code>docker compose up -d</code></li>
</ol>

<h3>Standard-Datenbank-Einstellungen (Docker):</h3>
<ul>
    <li><b>Host:</b> db</li>
    <li><b>Datenbank:</b> powerbook</li>
    <li><b>Benutzer:</b> powerbook</li>
    <li><b>Passwort:</b> powerbook_secret</li>
</ul>

<p><b>Hinweis:</b> Diese Installation löscht möglicherweise vorhandene PowerBook-Tabellen!</p>

<br>
<div align="center">
    <a href="install_deu.php?install=yes" style="background: #003366; padding: 10px 20px; border: 2px solid #6078A0; text-decoration: none; font-weight: bold;">
        Installation starten
    </a>
</div>
<br>

<?php
}
?>

</td></tr>
<tr bgcolor="#001329"><td align="center">
    <small>
        <a href="https://github.com/schubertnico/PowerBook.git" target="_blank">PowerBook</a>
        &copy; 2002 by <a href="mailto:expandable@powerscripts.org">Axel Habermaier</a>
        | PHP 8.4 Update: 2025
    </small>
</td></tr>
</table>
</div>

</body>
</html>
