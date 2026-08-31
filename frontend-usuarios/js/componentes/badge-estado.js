/* ============================================================================
 * componentes/badge-estado.js — Badge de estado con los colores del dominio.
 * ----------------------------------------------------------------------------
 * UI.badgeEstado(estado) -> HTMLElement (span)
 *   estado: string del dominio real. Estados conocidos y su color:
 *     funcional · desbordado · no funcional · dado de baja · en stock
 *   Cualquier otro estado cae a un gris neutro (no rompe).
 * El texto se muestra tal cual viene (textContent).
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};
    var UI = window.Cathartes.UI = window.Cathartes.UI || {};

    // estado del dominio -> sufijo de clase CSS (.badge--<sufijo>)
    var MAPA = {
        'funcional':     'funcional',
        'desbordado':    'desbordado',
        'no funcional':  'no-funcional',
        'dado de baja':  'dado-de-baja',
        'en stock':      'en-stock',
        // alias útiles de otros dominios (incidencias, cuentas)
        'en curso':      'desbordado',
        'finalizada':    'funcional',
        'activo':        'funcional',
        'no activo':     'dado-de-baja'
    };

    UI.badgeEstado = function (estado) {
        var clave = String(estado || '').trim().toLowerCase();
        var sufijo = MAPA[clave] || 'dado-de-baja';

        var span = document.createElement('span');
        span.className = 'badge badge--' + sufijo;
        span.textContent = estado || '—';
        return span;
    };
})();
