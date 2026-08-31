/* ============================================================================
 * componentes/estado-vacio.js — Estado vacío (sin datos).
 * ----------------------------------------------------------------------------
 * UI.estadoVacio({ titulo, texto }) -> HTMLElement
 * La base tiene poquísimas filas, así que esto se ve seguido: tiene que lucir
 * intencional, NO como un error. Icono + título + texto centrados.
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};
    var UI = window.Cathartes.UI = window.Cathartes.UI || {};

    var SVG_NS = 'http://www.w3.org/2000/svg';
    function icono() {
        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('class', 'vacio__icono');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '1.6');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        // caja abierta / inbox vacío
        var p = document.createElementNS(SVG_NS, 'path');
        p.setAttribute('d', 'M3 13h4l2 3h6l2-3h4M5 6h14l2 7v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-5z');
        svg.appendChild(p);
        return svg;
    }

    UI.estadoVacio = function (opts) {
        opts = opts || {};
        var box = document.createElement('div');
        box.className = 'vacio';
        box.appendChild(icono());

        var t = document.createElement('p');
        t.className = 'vacio__titulo';
        t.textContent = opts.titulo || 'No hay nada para mostrar';
        box.appendChild(t);

        var s = document.createElement('p');
        s.className = 'vacio__texto';
        s.textContent = opts.texto || 'Cuando haya datos, van a aparecer acá.';
        box.appendChild(s);
        return box;
    };
})();
