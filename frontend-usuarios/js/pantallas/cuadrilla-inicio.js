/* ============================================================================
 * pantallas/cuadrilla-inicio.js — Inicio de la cuadrilla (C1).
 * Pantalla: cuadrilla.html. Datos: API Recolección (GET /cuadrilla/resumen).
 *
 * Un solo request para las tres regiones: el resumen trae métricas, ruta activa y
 * urgentes. Si falla, cada región muestra SU estado de error — no se inventan
 * datos de ejemplo.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, R = C.Recoleccion, Fmt = C.Fmt;

    function render(ctx) {
        var regM = document.getElementById('reg-metricas');
        var regR = document.getElementById('reg-ruta');
        var regU = document.getElementById('reg-urgentes');

        Fmt.rellenar(document.body, {
            nombre: ctx.usuario.nombre || 'cuadrilla'
        });

        R.get('/cuadrilla/resumen').then(function (res) {
            if (res.status !== 200 || !res.data) {
                Fmt.estado(regM, 'error');
                Fmt.estado(regR, 'error');
                Fmt.estado(regU, 'error');
                return;
            }
            var d = res.data;
            pintarMetricas(regM, d.metricas);
            pintarRuta(regR, d.ruta_activa);
            pintarUrgentes(regU, d.urgentes);
        });
    }

    function pintarMetricas(reg, m) {
        if (!m) { Fmt.estado(reg, 'vacio'); return; }
        Fmt.rellenar(reg, {
            rutas_hoy:                 m.rutas_hoy || 0,
            intervenciones_pendientes: m.intervenciones_pendientes || 0,
            contenedores_a_verificar:  m.contenedores_a_verificar || 0
        });
        Fmt.estado(reg, 'ok');
    }

    function pintarRuta(reg, r) {
        if (!r) { Fmt.estado(reg, 'vacio'); return; }
        var pct = Fmt.pct(r.verificados, r.total_contenedores);
        Fmt.rellenar(reg, {
            ruta_nombre:        r.nombre,
            ruta_zona:          r.zona,
            ruta_frecuencia:    r.frecuencia,
            ruta_fecha:         Fmt.fechaCorta(r.fecha_toma),
            verificados:        r.verificados || 0,
            total_contenedores: r.total_contenedores || 0,
            pct:                pct + '%'
        });
        Fmt.badge(reg.querySelector('[data-campo="ruta_estado"]'), r.estado);
        reg.querySelector('[data-barra]').style.width = pct + '%';
        Fmt.estado(reg, 'ok');
    }

    function pintarUrgentes(reg, items) {
        items = items || [];
        if (!items.length) { Fmt.estado(reg, 'vacio'); return; }

        var lista = document.getElementById('lista-urgentes');
        var tpl   = document.getElementById('tpl-urgente');
        lista.textContent = '';
        items.forEach(function (i) {
            var nodo = document.importNode(tpl.content, true);
            nodo.querySelector('.lista__item').setAttribute(
                'href', 'cuadrilla-intervenciones.html?id=' + i.id_intervencion
            );
            nodo.querySelector('[data-campo="titulo"]').textContent =
                '#' + i.id_incidencia + ' · ' + i.tipo;
            nodo.querySelector('[data-campo="detalle"]').textContent =
                Fmt.fechaCorta(i.fecha_inicio) + ' · ' + i.ubicacion;
            Fmt.badge(nodo.querySelector('[data-campo="estado"]'), i.estado_contenedor);
            lista.appendChild(nodo);
        });
        Fmt.estado(reg, 'ok');
    }

    C.App.iniciar({ activo: 'inicio', rol: 'cuadrilla' }, render);
})();
