/* ============================================================================
 * pantallas/notificaciones.js — Avisos de la persona con sesión (vecino, cuadrilla, operario).
 * Pantalla: notificaciones.html. Datos: API Usuarios (GET /notificaciones,
 * POST /notificaciones/leer?id=N y /notificaciones/leer-todas).
 * Texto de la API siempre por textContent (el mensaje incluye datos que cargó otra persona).
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, U = C.Api, Fmt = C.Fmt;

    /** A dónde lleva cada aviso según el rol de quien lo lee. null = sin enlace. */
    function destino(n, rol) {
        if (rol === 'vecino' && n.id_incidencia) return 'incidencia.html?id=' + n.id_incidencia;
        if (rol === 'cuadrilla' && n.tipo === 'intervencion asignada') return 'cuadrilla-intervenciones.html';
        return null;
    }

    function render(ctx) {
        var rol = ctx.usuario.rol;
        var region = document.querySelector('[data-region="notificaciones"]');
        var lista = document.getElementById('lista');
        var tpl = document.getElementById('tpl-notif');
        var btnTodas = document.getElementById('btn-todas');
        var todas = [], filtro = 'todas';

        function pintar() {
            var nuevas = todas.filter(function (n) { return !n.leida; });
            document.getElementById('n-nuevas').textContent = nuevas.length;
            btnTodas.hidden = nuevas.length === 0;

            var arr = filtro === 'nuevas' ? nuevas : todas;
            if (!arr.length) {
                document.getElementById('vacio-titulo').textContent = filtro === 'nuevas' ? 'Todo al día' : 'Sin notificaciones';
                document.getElementById('vacio-texto').textContent = filtro === 'nuevas' ? 'No tenés avisos sin leer.' : 'Cuando haya novedades, te avisamos acá.';
                Fmt.estado(region, 'vacio');
                return;
            }
            lista.textContent = '';
            arr.forEach(function (n) {
                var nodo = document.importNode(tpl.content, true);
                var item = nodo.querySelector('.notif__item');
                if (!n.leida) item.classList.add('notif__item--nueva');
                Fmt.rellenar(nodo, { mensaje: n.mensaje, fecha: Fmt.fechaHora(n.fecha), estado: n.leida ? '' : ' · sin leer' });

                var ver = nodo.querySelector('[data-accion="ver"]');
                var url = destino(n, rol);
                if (url) { ver.href = url; ver.hidden = false; }

                var leer = nodo.querySelector('[data-accion="leer"]');
                if (!n.leida) {
                    leer.hidden = false;
                    leer.addEventListener('click', function () {
                        U.post('/notificaciones/leer?id=' + n.id).then(function (res) {
                            if (res.status === 200) { n.leida = true; pintar(); }
                        });
                    });
                }
                lista.appendChild(nodo);
            });
            Fmt.estado(region, 'ok');
        }

        document.querySelectorAll('#chips .chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                document.querySelectorAll('#chips .chip').forEach(function (c) { c.classList.remove('chip--activo'); });
                chip.classList.add('chip--activo');
                filtro = chip.getAttribute('data-filtro');
                pintar();
            });
        });

        btnTodas.addEventListener('click', function () {
            U.post('/notificaciones/leer-todas').then(function (res) {
                if (res.status === 200) { todas.forEach(function (n) { n.leida = true; }); pintar(); }
            });
        });

        U.get('/notificaciones?limite=100').then(function (res) {
            if (res.status !== 200 || !res.data || !Array.isArray(res.data.items)) { Fmt.estado(region, 'error'); return; }
            todas = res.data.items;
            pintar();
        });
    }

    C.App.iniciar({ activo: 'notificaciones', rol: ['vecino', 'cuadrilla', 'operario'] }, render);
})();
