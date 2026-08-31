/* ============================================================================
 * componentes/tarjeta-metrica.js — Tarjeta de métrica reutilizable.
 * ----------------------------------------------------------------------------
 * UI.tarjetaMetrica({ valor, etiqueta, pie }) -> HTMLElement
 *   valor    : número o texto grande (ej. 150, "3")
 *   etiqueta : rótulo debajo del valor
 *   pie      : nota al pie opcional
 * Todo por textContent (dato de la API) — ver decisión anti-XSS en api.js.
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};
    var UI = window.Cathartes.UI = window.Cathartes.UI || {};

    UI.tarjetaMetrica = function (opts) {
        opts = opts || {};
        var card = document.createElement('div');
        card.className = 'metrica';

        var valor = document.createElement('div');
        valor.className = 'metrica__valor';
        valor.textContent = (opts.valor === undefined || opts.valor === null) ? '—' : String(opts.valor);
        card.appendChild(valor);

        var etiqueta = document.createElement('div');
        etiqueta.className = 'metrica__etiqueta';
        etiqueta.textContent = opts.etiqueta || '';
        card.appendChild(etiqueta);

        if (opts.pie) {
            var pie = document.createElement('div');
            pie.className = 'metrica__pie';
            pie.textContent = opts.pie;
            card.appendChild(pie);
        }
        return card;
    };
})();
