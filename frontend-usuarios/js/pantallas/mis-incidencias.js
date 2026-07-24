/* ============================================================================
 * pantallas/mis-incidencias.js — Listado de incidencias del vecino.
 * Pantalla: mis-incidencias.html. Datos: API Gestión (GET /vecino/incidencias).
 * Filtra en cliente por los chips, pagina de a 10 y clona filas del <template>.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, G = C.Gestion, Fmt = C.Fmt;
    var POR_PAGINA = 10;

    var FILTROS = {
        todas:       function () { return true; },
        curso:       function (i) { return i.estado === 'en curso'; },
        validadas:   function (i) { return i.validada === 'si'; },
        finalizadas: function (i) { return i.estado === 'finalizada'; }
    };

    function render(ctx) {
        var flash = C.Auth.tomarFlash();
        if (flash) C.UI.toast(flash, 'ok');

        var body = document.getElementById('tabla-body');
        var tpl  = document.getElementById('tpl-fila');
        var info = document.getElementById('pag-info');
        var prev = document.getElementById('pag-prev');
        var next = document.getElementById('pag-next');
        var todas = [], filtro = 'todas', pagina = 1;

        function filtradas() { return todas.filter(FILTROS[filtro] || FILTROS.todas); }

        function pintar() {
            var arr = filtradas();
            var vacia = !arr.length;
            document.getElementById('tabla-wrap').hidden = vacia;
            document.getElementById('vacio').hidden = !vacia;
            if (vacia) return;

            var totalPag = Math.max(1, Math.ceil(arr.length / POR_PAGINA));
            if (pagina > totalPag) pagina = totalPag;
            body.textContent = '';
            arr.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA).forEach(function (i) {
                var nodo = document.importNode(tpl.content, true);
                var tr = nodo.querySelector('tr');
                nodo.querySelector('[data-campo="id"]').textContent        = i.id;
                nodo.querySelector('[data-campo="fecha"]').textContent     = Fmt.fechaCorta(i.fecha_ing);
                nodo.querySelector('[data-campo="ubicacion"]').textContent = i.ubicacion;
                nodo.querySelector('[data-campo="tipo"]').textContent      = i.tipo;
                Fmt.badge(nodo.querySelector('[data-campo="estado"]'), i.estado);
                tr.style.cursor = 'pointer';
                tr.addEventListener('click', function () { window.location.href = 'incidencia.html?id=' + i.id; });
                body.appendChild(nodo);
            });
            info.textContent = 'Página ' + pagina + ' de ' + totalPag + ' · ' + arr.length + ' registros';
            prev.disabled = pagina <= 1;
            next.disabled = pagina >= totalPag;
        }

        prev.addEventListener('click', function () { if (pagina > 1) { pagina--; pintar(); } });
        next.addEventListener('click', function () { pagina++; pintar(); });

        document.querySelectorAll('#chips .chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                document.querySelectorAll('#chips .chip').forEach(function (c) { c.classList.remove('chip--activo'); });
                chip.classList.add('chip--activo');
                filtro = chip.getAttribute('data-filtro');
                pagina = 1;
                pintar();
            });
        });

        G.get('/vecino/incidencias').then(function (res) {
            todas = (res.data && res.data.items) || [];
            pintar();
        });
    }

    C.App.iniciar({ activo: 'mis-incidencias' }, render);
})();
