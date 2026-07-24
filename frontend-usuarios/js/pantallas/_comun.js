/* ============================================================================
 * pantallas/_comun.js — Utilidades compartidas por los controladores de pantalla.
 * ----------------------------------------------------------------------------
 * Solo LÓGICA (formato de fechas, relleno de un badge existente). NO crea
 * estructura: los badges, tarjetas y filas ya están escritos en el HTML o en
 * sus <template>; acá solo se rellenan/formatean valores de la API.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes = window.Cathartes || {};

    function partes(iso) {
        return String(iso || '').match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    }

    // estado del dominio -> sufijo de clase CSS (.badge--<sufijo>)
    var MAPA_BADGE = {
        'funcional': 'funcional', 'desbordado': 'desbordado', 'no funcional': 'no-funcional',
        'dado de baja': 'dado-de-baja', 'en stock': 'en-stock',
        'en curso': 'desbordado', 'finalizada': 'funcional', 'activo': 'funcional', 'no activo': 'dado-de-baja'
    };

    C.Fmt = {
        fechaCorta: function (iso) { var m = partes(iso); return m ? (m[3] + '/' + m[2] + '/' + m[1]) : ''; },
        fechaHora:  function (iso) { var m = partes(iso); return m ? (m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5]) : ''; },

        /** Rellena un <span class="badge"> YA EXISTENTE con el estado + su color. */
        badge: function (span, estado) {
            if (!span) return;
            var suf = MAPA_BADGE[String(estado || '').toLowerCase()] || 'dado-de-baja';
            span.textContent = estado || '—';
            span.className = 'badge badge--' + suf;
        },

        /** Rellena todos los [data-campo] de un root con un objeto {campo: valor}. */
        rellenar: function (root, datos) {
            root.querySelectorAll('[data-campo]').forEach(function (el) {
                var k = el.getAttribute('data-campo');
                if (Object.prototype.hasOwnProperty.call(datos, k) && datos[k] !== null && datos[k] !== undefined) {
                    el.textContent = String(datos[k]);
                }
            });
        }
    };
})();
