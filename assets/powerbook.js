/* PowerBook - kleine Helfer für das Gästebuch-Formular.
 *
 * Zeichenzähler unter dem Textfeld: zählt wie der Server (Zeilenumbruch = 1
 * Zeichen, Emoji = 1 Zeichen, Leerraum am Anfang und Ende zählt nicht).
 */
(function () {
    'use strict';

    var text = document.getElementById('pb_text');
    var counter = document.getElementById('pbTextCounter');
    if (!text || !counter) {
        return;
    }

    var max = parseInt(text.getAttribute('data-pb-max') || '5000', 10);
    var format = function (n) {
        return n.toLocaleString('de-DE');
    };

    var update = function () {
        var value = text.value.replace(/\r\n?/g, '\n').trim();
        var length = Array.from(value).length;
        var over = length > max;
        counter.textContent = format(length) + ' von ' + format(max) + ' Zeichen' + (over ? ' – bitte kürzen' : '');
        counter.classList.toggle('text-danger', over);
        counter.classList.toggle('fw-semibold', over);
    };

    text.addEventListener('input', update);
    update();
}());
