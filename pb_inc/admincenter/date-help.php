<?php
/**
 * PowerBook - PHP Guestbook System
 * Hilfe zu Datums- und Zeitformaten (aus der Konfiguration verlinkt)
 *
 * Alle Beispiele werden mit dem heutigen Datum berechnet, genau so, wie
 * PowerBook sie im Gästebuch und im AdminCenter ausgibt.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/../functions.inc.php';

$section = isset($_GET['section']) && is_string($_GET['section']) ? $_GET['section'] : '';
$showDate = $section !== 'time';
$showTime = $section !== 'date';
$now = time();
$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$example = static fn (string $format): string => pb_format_date($format, $now);

$dateChars = [
    ['d', 'Tag, zweistellig (01 bis 31)'],
    ['j', 'Tag ohne führende Null (1 bis 31)'],
    ['l', 'Wochentag ausgeschrieben (kleines L)'],
    ['D', 'Wochentag, zwei Buchstaben'],
    ['m', 'Monat, zweistellig (01 bis 12)'],
    ['n', 'Monat ohne führende Null (1 bis 12)'],
    ['F', 'Monatsname ausgeschrieben'],
    ['M', 'Monatsname, drei Buchstaben'],
    ['Y', 'Jahr, vierstellig'],
    ['y', 'Jahr, zweistellig'],
    ['W', 'Kalenderwoche'],
];
$dateExamples = ['d.m.Y', 'j. F Y', 'l, j. F Y', 'D, d.m.y', '\K\W W, d.m.Y'];
$timeChars = [
    ['H', 'Stunde, zweistellig (00 bis 23)'],
    ['G', 'Stunde ohne führende Null (0 bis 23)'],
    ['i', 'Minute, zweistellig (00 bis 59)'],
    ['s', 'Sekunde, zweistellig (00 bis 59)'],
];
$timeExamples = ['H:i', 'G:i', 'H.i', 'H:i:s'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Hilfe: Datum und Uhrzeit · PowerBook AdminCenter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="../../assets/powerbook.css" rel="stylesheet">
</head>
<body class="pb-body bg-body-tertiary">
<main class="container py-4 pb-admin" style="max-width: 60rem">
    <h1 class="h3 mb-3">Hilfe: Datum und Uhrzeit</h1>
    <p>
        PowerBook schreibt Datum und Uhrzeit nach einem Muster. Jeder Buchstabe aus den Tabellen steht für
        einen Teil des Datums, alle anderen Zeichen wie Punkt, Komma und Leerzeichen erscheinen unverändert.
        Ein Muster darf höchstens 20 Zeichen lang sein. Die Beispiele zeigen das heutige Datum.
    </p>

    <?php if ($showDate) { ?>
    <section id="pbHelpDate" class="card shadow-sm mb-4">
        <header class="card-header bg-primary text-white"><h2 class="h5 mb-0">Datumsformat</h2></header>
        <div class="card-body">
            <table class="table table-sm align-middle">
                <thead><tr><th scope="col">Zeichen</th><th scope="col">Bedeutung</th><th scope="col">Heute</th></tr></thead>
                <tbody>
                <?php foreach ($dateChars as [$char, $meaning]) { ?>
                    <tr><td><code><?= $h($char) ?></code></td><td><?= $h($meaning) ?></td><td><?= $h($example($char)) ?></td></tr>
                <?php } ?>
                </tbody>
            </table>
            <h3 class="h6 mt-4">Beispiele</h3>
            <table class="table table-sm align-middle mb-3">
                <thead><tr><th scope="col">Muster</th><th scope="col">Ergebnis heute</th></tr></thead>
                <tbody>
                <?php foreach ($dateExamples as $format) { ?>
                    <tr><td><code><?= $h($format) ?></code></td><td><?= $h($example($format)) ?></td></tr>
                <?php } ?>
                </tbody>
            </table>
            <p class="mb-0 small text-body-secondary">
                Tages- und Monatsnamen erscheinen auf Deutsch. Soll ein Buchstabe wörtlich erscheinen,
                schreiben Sie einen Backslash davor: <code>\K\W W</code> ergibt „<?= $h($example('\K\W W')) ?>“.
            </p>
        </div>
    </section>
    <?php } ?>

    <?php if ($showTime) { ?>
    <section id="pbHelpTime" class="card shadow-sm mb-4">
        <header class="card-header bg-primary text-white"><h2 class="h5 mb-0">Zeitformat</h2></header>
        <div class="card-body">
            <table class="table table-sm align-middle">
                <thead><tr><th scope="col">Zeichen</th><th scope="col">Bedeutung</th><th scope="col">Jetzt</th></tr></thead>
                <tbody>
                <?php foreach ($timeChars as [$char, $meaning]) { ?>
                    <tr><td><code><?= $h($char) ?></code></td><td><?= $h($meaning) ?></td><td><?= $h($example($char)) ?></td></tr>
                <?php } ?>
                </tbody>
            </table>
            <h3 class="h6 mt-4">Beispiele</h3>
            <table class="table table-sm align-middle mb-3">
                <thead><tr><th scope="col">Muster</th><th scope="col">Ergebnis jetzt</th></tr></thead>
                <tbody>
                <?php foreach ($timeExamples as $format) { ?>
                    <tr><td><code><?= $h($format) ?></code></td><td><?= $h($example($format)) ?></td></tr>
                <?php } ?>
                </tbody>
            </table>
            <p class="mb-0 small text-body-secondary">
                Das Wort „Uhr“ steht in der Design-Vorlage hinter <code>(#TIME#)</code> und gehört nicht ins
                Zeitformat. Die Buchstaben <code>g</code>, <code>h</code>, <code>a</code> und <code>A</code>
                (12-Stunden-Uhr mit am/pm) passen nicht zu deutschen Uhrzeiten.
            </p>
        </div>
    </section>
    <?php } ?>

    <p class="mb-0"><a href="index.php?page=configuration">Zur Konfiguration</a></p>
</main>
</body>
</html>
