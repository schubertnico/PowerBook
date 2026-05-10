<?php
/**
 * PowerBook - PHP Guestbook System
 * License Information
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.inc.php';

pb_admin_card_open('MIT-Lizenz');
?>

<div class="card border-light bg-body-secondary">
    <div class="card-body">
        <pre class="pb-pre">MIT License

Copyright (c) 2002 Axel "Expandable" Habermaier (Original PowerBook 1.21)
Copyright (c) 2025 Nico Schubert (PHP 8.4 Migration & Security Updates)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
</pre>
    </div>
</div>

<p class="mt-3 mb-0">
    <b>Projekt:</b>
    <a href="https://www.powerscripts.org" target="_blank" rel="noopener noreferrer">https://www.powerscripts.org</a>
</p>
<p class="text-body-secondary mb-0"><small>
    Hinweis: Der oben angezeigte MIT-Lizenztext ist rechtlich notwendig
    und darf nicht entfernt werden, da PowerBook unter dieser Lizenz
    weiterverteilt wird.
</small></p>

<?php
pb_admin_card_close();
