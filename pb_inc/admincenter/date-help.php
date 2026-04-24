<?php
/**
 * PowerBook - PHP Guestbook System
 * Date/Time Format Help
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

$section = $_GET['section'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerBook - Hilfe</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #FFFFFF;
            background-color: #000000;
            margin: 10px;
        }
        table {
            border-collapse: collapse;
        }
        td {
            font-size: 10pt;
            color: #FFFFFF;
            padding: 2px 5px;
        }
        th {
            font-size: 10pt;
            color: #FFFFFF;
            font-weight: bold;
            padding: 5px;
        }
        .code {
            font-family: monospace;
            color: #FF9900;
        }
    </style>
</head>
<body>

<?php if ($section === 'date') { ?>
<table border="0" width="250">
    <tr><th colspan="2">Datumsformate</th></tr>
    <tr>
        <td class="code">d</td>
        <td>Tag des Monats: "01" - "31"</td>
    </tr>
    <tr>
        <td class="code">j</td>
        <td>Tag des Monats: "1" - "31"</td>
    </tr>
    <tr>
        <td class="code">D</td>
        <td>Tag der Woche, 3 Buchstaben ("Mo")</td>
    </tr>
    <tr>
        <td class="code">l</td>
        <td>Tag der Woche, wie "Montag"</td>
    </tr>
    <tr>
        <td class="code">F</td>
        <td>Monat, wie "März"</td>
    </tr>
    <tr>
        <td class="code">m</td>
        <td>Monat: "01" - "12"</td>
    </tr>
    <tr>
        <td class="code">n</td>
        <td>Monat: "1" - "12"</td>
    </tr>
    <tr>
        <td class="code">M</td>
        <td>Monat, 3 Buchstaben ("Jan")</td>
    </tr>
    <tr>
        <td class="code">Y</td>
        <td>Jahr, vierstellig ("2025")</td>
    </tr>
    <tr>
        <td class="code">y</td>
        <td>Jahr, zweistellig ("25")</td>
    </tr>
    <tr>
        <td class="code">S</td>
        <td>Ordinalendung ("1st", "2nd")</td>
    </tr>
</table>
<br>
<p><b>Beispiel:</b> <span class="code">l, j. F Y</span><br>
Ergibt: "Montag, 1. Januar 2025"</p>

<?php } elseif ($section === 'time') { ?>
<table border="0" width="180">
    <tr><th colspan="2">Zeitformate</th></tr>
    <tr>
        <td class="code">a</td>
        <td>"am" / "pm"</td>
    </tr>
    <tr>
        <td class="code">A</td>
        <td>"AM" / "PM"</td>
    </tr>
    <tr>
        <td class="code">g</td>
        <td>Stunden: "1" - "12"</td>
    </tr>
    <tr>
        <td class="code">G</td>
        <td>Stunden: "0" - "23"</td>
    </tr>
    <tr>
        <td class="code">h</td>
        <td>Stunden: "01" - "12"</td>
    </tr>
    <tr>
        <td class="code">H</td>
        <td>Stunden: "00" - "23"</td>
    </tr>
    <tr>
        <td class="code">i</td>
        <td>Minuten: "00" - "59"</td>
    </tr>
    <tr>
        <td class="code">s</td>
        <td>Sekunden: "00" - "59"</td>
    </tr>
</table>
<br>
<p><b>Beispiel:</b> <span class="code">H:i</span><br>
Ergibt: "14:30"</p>

<?php } else { ?>
<p>Kein Abschnitt ausgewählt!</p>
<p><a href="?section=date">Datumsformate</a> | <a href="?section=time">Zeitformate</a></p>
<?php } ?>

</body>
</html>
