/* ============================================================================
 * pantallas/inicio.js — Controlador del inicio del vecino.
 * Pantalla: inicio.html. Datos: /saldo (Usuarios), /vecino/metricas,
 * /vecino/incidencias?limite=5, /contenedores (Gestión).
 * Solo pide datos, rellena data-campo y clona los <template>.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, G = C.Gestion, Fmt = C.Fmt;

    function render(ctx) {
        // Saludo + métricas (saldo ya viene en ctx; el resto de la API).
        Fmt.rellenar(document.body, {
            nombre: (ctx.usuario.nombre || '').split(/\s+/)[0] || ctx.usuario.nombre,
            saldo:  (ctx.saldo == null ? 0 : ctx.saldo)
        });
        G.get('/vecino/metricas').then(function (res) {
            var m = (res.data && res.data.metricas) || {};
            Fmt.rellenar(document.body, { en_curso: m.en_curso || 0, finalizadas: m.finalizadas_anio || 0 });
        });

        // Incidencias recientes
        var listaR = document.getElementById('lista-recientes');
        var tplR   = document.getElementById('tpl-reciente');
        G.get('/vecino/incidencias?limite=5').then(function (res) {
            var items = (res.data && res.data.items) || [];
            if (!items.length) { document.getElementById('vacio-recientes').hidden = false; return; }
            items.forEach(function (i) {
                var nodo = document.importNode(tplR.content, true);
                nodo.querySelector('.lista__item').setAttribute('href', 'incidencia.html?id=' + i.id);
                nodo.querySelector('[data-campo="titulo"]').textContent  = '#' + i.id + ' · ' + i.tipo;
                nodo.querySelector('[data-campo="detalle"]').textContent = Fmt.fechaCorta(i.fecha_ing) + ' · ' + i.ubicacion;
                Fmt.badge(nodo.querySelector('[data-campo="estado"]'), i.estado);
                listaR.appendChild(nodo);
            });
        });

        // Contenedores cercanos
        var listaC = document.getElementById('lista-contenedores');
        var tplC   = document.getElementById('tpl-contenedor');
        G.get('/contenedores').then(function (res) {
            var items = (res.data && res.data.items) || [];
            if (!items.length) { document.getElementById('vacio-contenedores').hidden = false; return; }
            items.forEach(function (c) {
                var nodo = document.importNode(tplC.content, true);
                nodo.querySelector('[data-campo="ubicacion"]').textContent = c.ubicacion;
                nodo.querySelector('[data-campo="detalle"]').textContent   = c.tipo + ' · ' + c.ruta;
                Fmt.badge(nodo.querySelector('[data-campo="estado"]'), c.estado);
                listaC.appendChild(nodo);
            });
        });
    }

    C.App.iniciar({ activo: 'inicio', rol: 'vecino' }, render);
})();
