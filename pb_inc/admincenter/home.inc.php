<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Center Home Page
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);
?>

<tr>
    <td bgcolor="#3F5070" align="center">
        <b class="headline">W I L L K O M M E N</b>
    </td>
</tr>
<tr>
    <td bgcolor="#001F3F" valign="top">

<p>Willkommen im AdminCenter von PowerBook!</p>

<p>
    Vielen Dank, dass Sie PowerBook benutzen. Bei Fragen oder Problemen besuchen Sie bitte das
    <a href="https://github.com/schubertnico/PowerBook.git" target="_blank">GitHub Repository</a>.
</p>

<h3>Schnellnavigation</h3>
<ul>
    <li><a href="?page=entries">Einträge verwalten</a></li>
    <li><a href="?page=release">Einträge freischalten</a></li>
    <li><a href="?page=admins">Administratoren verwalten</a></li>
    <li><a href="?page=configuration">Konfiguration</a></li>
</ul>

<h3>Systeminfo</h3>
<ul>
    <li><strong>PHP Version:</strong> <?= e(PHP_VERSION) ?></li>
    <li><strong>PowerBook Version:</strong> 2.0 (PHP 8.4 Update)</li>
    <li><strong>Öffentliche Einträge:</strong> <?= $head_count_entries ?></li>
    <li><strong>Versteckte Einträge:</strong> <?= $head_count_unreleased ?></li>
</ul>

<p><small>
    Original: PowerBook 1.21 &copy; 2002 by
    <a href="mailto:expandable@powerscripts.org">Axel Habermaier</a><br>
    PHP 8.4 Update: 2025 by Nico Schubert
</small></p>

    </td>
</tr>
