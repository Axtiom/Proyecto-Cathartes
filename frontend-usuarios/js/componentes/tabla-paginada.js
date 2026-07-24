/* ============================================================================
 * componentes/tabla-paginada.js — Tabla con paginación del lado cliente.
 * ----------------------------------------------------------------------------
 * UI.tablaPaginada({ columnas, filas, porPagina, vacio }) -> HTMLElement
 *   columnas : [{ clave, titulo, mono?, render? }]
 *       clave  : propiedad de la fila a mostrar
 *       mono   : usar tipografía monoespaciada en la celda (opcional)
 *       render : function(fila) -> string | Node  (opcional; ej. un badge)
 *   filas    : array de objetos (datos reales de la API)
 *   porPagina: default 25 (la maqueta de Usuarios/Backoffice pagina de a 25)
 *   vacio    : { titulo, texto } para el estado vacío
 *   onFila   : function(fila) opcional — hace las filas clickeables
 *
 * Sin datos -> devuelve el componente de estado vacío (no una tabla pelada).
 * Todo el contenido de celdas se pinta con textContent o appendChild (nunca
 * innerHTML): datos de la API — ver decisión anti-XSS en api.js.
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};
    var UI = window.Cathartes.UI = window.Cathartes.UI || {};

    UI.tablaPaginada = function (opts) {
        opts = opts || {};
        var columnas  = opts.columnas || [];
        var filas     = opts.filas || [];
        var porPagina = opts.porPagina || 25;

        if (!filas.length) {
            return UI.estadoVacio(opts.vacio || {});
        }

        var pagina = 1;
        var totalPag = Math.max(1, Math.ceil(filas.length / porPagina));

        var wrap = document.createElement('div');
        wrap.className = 'tabla-wrap';

        var tabla = document.createElement('table');
        tabla.className = 'tabla';

        // encabezado
        var thead = document.createElement('thead');
        var trh = document.createElement('tr');
        columnas.forEach(function (col) {
            var th = document.createElement('th');
            th.textContent = col.titulo || col.clave;
            trh.appendChild(th);
        });
        thead.appendChild(trh);
        tabla.appendChild(thead);

        var tbody = document.createElement('tbody');
        tabla.appendChild(tbody);
        wrap.appendChild(tabla);

        // pie de paginación
        var pie = document.createElement('div');
        pie.className = 'paginacion';
        var info = document.createElement('span');
        var btns = document.createElement('div');
        btns.className = 'paginacion__btns';
        var prev = document.createElement('button');
        prev.type = 'button'; prev.textContent = 'Anterior';
        var next = document.createElement('button');
        next.type = 'button'; next.textContent = 'Siguiente';
        btns.appendChild(prev); btns.appendChild(next);
        pie.appendChild(info); pie.appendChild(btns);
        wrap.appendChild(pie);

        function celda(col, fila) {
            var td = document.createElement('td');
            if (col.mono) td.className = 'mono';
            var contenido = typeof col.render === 'function' ? col.render(fila) : fila[col.clave];
            if (contenido instanceof Node) {
                td.appendChild(contenido);
            } else {
                td.textContent = (contenido === undefined || contenido === null) ? '' : String(contenido);
            }
            return td;
        }

        function pintar() {
            tbody.textContent = '';
            var ini = (pagina - 1) * porPagina;
            var onFila = typeof opts.onFila === 'function' ? opts.onFila : null;
            filas.slice(ini, ini + porPagina).forEach(function (fila) {
                var tr = document.createElement('tr');
                columnas.forEach(function (col) { tr.appendChild(celda(col, fila)); });
                if (onFila) {
                    tr.style.cursor = 'pointer';
                    tr.addEventListener('click', function () { onFila(fila); });
                }
                tbody.appendChild(tr);
            });
            info.textContent = 'Página ' + pagina + ' de ' + totalPag + ' · ' + filas.length + ' registros';
            prev.disabled = pagina <= 1;
            next.disabled = pagina >= totalPag;
        }

        prev.addEventListener('click', function () { if (pagina > 1) { pagina--; pintar(); } });
        next.addEventListener('click', function () { if (pagina < totalPag) { pagina++; pintar(); } });
        pintar();

        return wrap;
    };
})();
