<?php
/**
 * PowerBook - PHP Guestbook System
 * Installation Script (German)
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// BUG-011: Lock-File-Check. Nach erfolgreicher Installation wird '.installed'
// erstellt. Beim nächsten Aufruf: HTTP 403 und kurze Info-Seite.
$lockFile = __DIR__ . '/.installed';
if (file_exists($lockFile)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>403 - Installation already completed</title>'
       . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">'
       . '</head><body class="bg-body-tertiary">'
       . '<main class="container py-5"><div class="card border-danger shadow-sm">'
       . '<div class="card-header bg-danger text-white"><h1 class="h4 mb-0">403 &middot; Installation bereits abgeschlossen</h1></div>'
       . '<div class="card-body">'
       . '<p>PowerBook wurde bereits installiert. Löschen Sie die Datei <code>'
       . htmlspecialchars($lockFile, ENT_QUOTES, 'UTF-8')
       . '</code> manuell, falls Sie neu installieren möchten.</p>'
       . '</div></div></main></body></html>';
    exit;
}

require_once __DIR__ . '/pb_inc/layout.inc.php';

pb_layout_header('PowerBook - Installation', [
    'showNav' => false,
    'siteName' => 'PowerBook Installation',
]);
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <section class="card shadow-sm mb-4">
            <header class="card-header bg-primary text-white">
                <h1 class="h4 mb-0">PowerBook &middot; Installation</h1>
            </header>
            <div class="card-body">

<?php
$install = $_GET['install'] ?? '';

if ($install === 'yes') {
    require_once __DIR__ . '/pb_inc/mysql.inc.php';
    require_once __DIR__ . '/pb_inc/database.inc.php';

    try {
        $pdo = getDatabase();

        echo '<div class="mb-3">';

        // Drop existing tables
        echo 'Loesche alte Tabellen (falls vorhanden)...<br>';
        $pdo->exec("DROP TABLE IF EXISTS {$pb_entries}");
        $pdo->exec("DROP TABLE IF EXISTS {$pb_config}");
        $pdo->exec("DROP TABLE IF EXISTS {$pb_admin}");
        echo '<span class="text-success">Abgeschlossen.</span><br><br>';

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
        echo ' <span class="text-success">Abgeschlossen.</span><br>';

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
            pages ENUM('L','D') DEFAULT 'L' NOT NULL,
            use_thanks ENUM('Y','N') DEFAULT 'N' NOT NULL,
            language ENUM('ger1','ger2','eng') DEFAULT 'eng' NOT NULL,
            design TEXT NOT NULL,
            thanks_title VARCHAR(250) NOT NULL,
            thanks TEXT NOT NULL,
            statements ENUM('Y','N') DEFAULT 'Y' NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo ' <span class="text-success">Abgeschlossen.</span><br>';

        // Create entries table
        echo "Erstelle Tabelle <b>{$pb_entries}</b>...";
        $pdo->exec("CREATE TABLE {$pb_entries} (
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
            statement TEXT NOT NULL DEFAULT '',
            statement_by VARCHAR(250) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            INDEX idx_status (status),
            INDEX idx_ip (ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo ' <span class="text-success">Abgeschlossen.</span><br>';

        // Insert standard config
        echo 'Fuege Standard-Konfiguration ein...';

        // Bootstrap-5-Default-Design für Einträge. Wer sein Layout customizen will,
        // findet die Platzhalter (#ICON#) … (#URL#) im AdminCenter > Konfiguration.
        $defaultDesign = '<article class="card pb-entry-card shadow-sm">'
            . '<header class="card-header d-flex flex-wrap justify-content-between align-items-center">'
            . '<span>(#ICON#)<b>(#DATE#)</b>, <small class="text-body-secondary">(#TIME#)h</small></span>'
            . '<span>(#EMAIL_NAME#)</span>'
            . '</header>'
            . '<div class="card-body">(#TEXT#)</div>'
            . '<footer class="card-footer d-flex flex-wrap justify-content-end gap-3 align-items-center text-end">'
            . '<span>(#URL#)</span>'
            . '</footer>'
            . '</article>';

        $defaultThanks = 'Hallo (#NAME#)!

Vielen Dank für Ihren Eintrag in meinem Gästebuch!

Mit freundlichen Gruessen
Der Admin';

        $stmt = $pdo->prepare("INSERT INTO {$pb_config} VALUES (
            'R', 'N', '', 'l, j. F Y', 'H:i', 30, '#FF0000', 10, 'pbook.php', '',
            'Y', 'Y', 'Y', 'D', 'N', 'eng', :design, 'Danke für Ihren Eintrag!', :thanks, 'Y'
        )");
        $stmt->execute([':design' => $defaultDesign, ':thanks' => $defaultThanks]);
        echo ' <span class="text-success">Abgeschlossen.</span><br>';

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
        echo ' <span class="text-success">Abgeschlossen.</span><br>';

        // BUG-011: Lock-File setzen, damit install_deu.php nicht erneut aufgerufen werden kann.
        @file_put_contents(__DIR__ . '/.installed', date('c') . "\n");

        echo '</div>';

        echo '<div class="alert alert-success" role="status">';
        echo '<h2 class="h5 alert-heading">Installation erfolgreich!</h2>';
        echo '<p>Bitte <b>löschen Sie diese Datei</b> (install_deu.php) aus Sicherheitsgruenden.</p>';
        echo '<p>Bearbeiten Sie die Konfiguration und Ihre Admin-Daten im <a class="alert-link" href="pb_inc/admincenter/">AdminCenter</a>.</p>';
        echo '<hr>';
        echo '<p class="mb-1"><b>Login-Daten:</b></p>';
        echo '<ul class="mb-2"><li><b>Name:</b> PowerBook</li>';
        echo '<li><b>Passwort:</b> <code class="bg-dark text-warning px-2 py-1 rounded">' . htmlspecialchars($initialAdminPassword, ENT_QUOTES, 'UTF-8') . '</code></li></ul>';
        echo '<p class="mb-0"><b>Wichtig:</b> Notieren Sie dieses Passwort JETZT &mdash; es wird nicht erneut angezeigt! Ändern Sie es nach dem ersten Login.</p>';
        echo '</div>';

    } catch (PDOException $e) {
        echo '<div class="alert alert-danger" role="alert">';
        echo '<b>Fehler bei der Installation:</b><br>';
        echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '<br><br>Bitte überpruefen Sie die Datenbankeinstellungen in <code>pb_inc/mysql.inc.php</code>.';
        echo '</div>';
    }

} else {
    ?>

<h2 class="h5 mb-3">Willkommen zur Installation von PowerBook!</h2>

<p>Diese Datei erstellt automatisch alle benoetigten MySQL-Tabellen.</p>

<h3 class="h6 mt-4">Vor der Installation</h3>
<ol>
    <li>Stellen Sie sicher, dass die Datei <code>pb_inc/mysql.inc.php</code> korrekt konfiguriert ist.</li>
    <li>Die MySQL-Datenbank muss existieren und der Benutzer muss Schreibrechte haben.</li>
    <li>Bei Verwendung von Docker: Starten Sie zuerst die Container mit <code>docker compose up -d</code>.</li>
</ol>

<h3 class="h6 mt-4">Standard-Datenbank-Einstellungen (Docker)</h3>
<ul class="list-group list-group-flush mb-3">
    <li class="list-group-item"><b>Host:</b> db</li>
    <li class="list-group-item"><b>Datenbank:</b> powerbook</li>
    <li class="list-group-item"><b>Benutzer:</b> powerbook</li>
    <li class="list-group-item"><b>Passwort:</b> powerbook_secret</li>
</ul>

<div class="alert alert-warning" role="alert">
    <b>Hinweis:</b> Diese Installation loescht möglicherweise vorhandene PowerBook-Tabellen!
</div>

<div class="d-flex justify-content-center mt-3">
    <a href="install_deu.php?install=yes" class="btn btn-primary btn-lg">
        Installation starten
    </a>
</div>

<?php
}
?>

            </div>
        </section>
    </div>
</div>

<?php
pb_layout_footer();
