/* ============================================================================
 * pantallas/saldo.js — Historial de puntos del vecino.
 * Pantalla: saldo.html. Datos: API de Usuarios (GET /movimientos). El saldo
 * grande sale del contexto de sesión (guardia). Pagina de a 10 y clona filas
 * del <template>; la estructura y el texto fijo viven en saldo.html.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, U = C.Usuarios, Fmt = C.Fmt;
    var POR_PAGINA = 10;

    function render(ctx) {
        Fmt.rellenar(document.body, { saldo: (ctx.saldo == null ? 0 : ctx.saldo) });

        var body = document.getElementById('tabla-body');
        var tpl  = document.getElementById('tpl-fila');
        var info = document.getElementById('pag-info');
        var prev = document.getElementById('pag-prev');
        var next = document.getElementById('pag-next');
        var todos = [], pagina = 1;

        function pintar() {
            var vacia = !todos.length;
            document.getElementById('tabla-wrap').hidden = vacia;
            document.getElementById('vacio').hidden = !vacia;
            if (vacia) return;

            var totalPag = Math.max(1, Math.ceil(todos.length / POR_PAGINA));
            if (pagina > totalPag) pagina = totalPag;
            body.textContent = '';
            todos.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA).forEach(function (m) {
                var nodo = document.importNode(tpl.content, true);
                nodo.querySelector('[data-campo="fecha"]').textContent    = Fmt.fechaCorta(m.fecha);
                nodo.querySelector('[data-campo="concepto"]').textContent = m.concepto;
                var pts = nodo.querySelector('[data-campo="puntos"]');
                pts.textContent = (m.puntos > 0 ? '+' : '') + m.puntos;
                pts.style.color = m.puntos > 0 ? 'var(--verde-vivo)' : 'var(--rojo)';
                body.appendChild(nodo);
            });
            info.textContent = 'Página ' + pagina + ' de ' + totalPag + ' · ' + todos.length + ' registros';
            prev.disabled = pagina <= 1;
            next.disabled = pagina >= totalPag;
        }

        prev.addEventListener('click', function () { if (pagina > 1) { pagina--; pintar(); } });
        next.addEventListener('click', function () { pagina++; pintar(); });

        U.get('/movimientos').then(function (res) {
            todos = (res.data && res.data.items) || [];
            pintar();
        });
    }

    C.App.iniciar({ activo: 'recompensas', rol: 'vecino' }, render);
})();
