<?php
$xml = simplexml_load_file('coverage.xml');
$files = [];

foreach ($xml->project->package as $pkg) {
    foreach ($pkg->file as $file) {
        $metrics = $file->metrics;
        $total = (int)$metrics['statements'];
        $covered = (int)$metrics['coveredstatements'];
        $pct = $total > 0 ? round($covered/$total*100, 1) : 0;
        // BUG-012: Doppel-Backslash, damit \' nicht den String-Terminator maskiert.
        $name = str_replace('D:\\restricted\\powerscripts.org\\PowerBook\\', '', (string) $file['name']);
        $files[] = [$name, $covered, $total, $pct];
    }
}
foreach ($xml->project->file as $file) {
    $metrics = $file->metrics;
    $total = (int)$metrics['statements'];
    $covered = (int)$metrics['coveredstatements'];
    $pct = $total > 0 ? round($covered/$total*100, 1) : 0;
    $name = str_replace('D:\\restricted\\powerscripts.org\\PowerBook\\', '', (string) $file['name']);
    $files[] = [$name, $covered, $total, $pct];
}

usort($files, fn($a, $b) => $a[3] <=> $b[3]);
foreach ($files as $f) {
    printf("%-60s %4d/%4d  %5.1f%%\n", $f[0], $f[1], $f[2], $f[3]);
}
