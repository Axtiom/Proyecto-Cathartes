/* ============================================================================
 * componentes/chips-filtro.js — Grupo de chips de filtro (selección única).
 * ----------------------------------------------------------------------------
 * UI.chipsFiltro({ opciones, activo, onChange }) -> HTMLElement
 *   opciones : [{ valor, etiqueta }]
 *   activo   : valor inicialmente seleccionado (opcional)
 *   onChange : function(valor) — se llama al cambiar de chip
 * Un solo chip activo a la vez. Reasigna la clase --activo al hacer click.
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};
    var UI = window.Cathartes.UI = window.Cathartes.UI || {};

    UI.chipsFiltro = function (opts) {
        opts = opts || {};
        var opciones = opts.opciones || [];
        var activo   = opts.activo;
        var onChange = typeof opts.onChange === 'function' ? opts.onChange : function () {};

        var cont = document.createElement('div');
        cont.className = 'chips';

        opciones.forEach(function (op) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'chip' + (op.valor === activo ? ' chip--activo' : '');
            chip.textContent = op.etiqueta;
            chip.setAttribute('data-valor', String(op.valor));

            chip.addEventListener('click', function () {
                if (op.valor === activo) return;
                activo = op.valor;
                Array.prototype.forEach.call(cont.children, function (c) {
                    c.classList.toggle('chip--activo', c.getAttribute('data-valor') === String(activo));
                });
                onChange(activo);
            });
            cont.appendChild(chip);
        });
        return cont;
    };
})();
