/* ============================================================================
 * pantallas/incidencia.js — Detalle de una incidencia del vecino.
 * Pantalla: incidencia.html. Datos: API Gestión (GET /vecino/incidencia?id=N,
 * GET /vecino/incidencia-foto?id=N). Rellena datos, foto y marca los hitos.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, G = C.Gestion, Fmt = C.Fmt;

    // La foto está protegida: se baja por fetch (la cookie de sesión viaja sola con
    // credentials same-origin) y se pinta como object URL, porque <img src> a un
    // endpoint protegido igual serviría, pero así se maneja el caso "sin foto".
    function cargarFoto(id, img, sinfoto) {
        var base = C.config.apiGestion.replace(/\/+$/, '');
        fetch(base + '/vecino/incidencia-foto?id=' + id, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('sin foto'); return r.blob(); })
            .then(function (b) { img.src = URL.createObjectURL(b); img.hidden = false; })
            .catch(function () { sinfoto.hidden = false; });
    }

    /** Marca un hito como alcanzado (punto lleno) y le pone la fecha. */
    function alcanzar(hito, fechaTexto) {
        var el = document.querySelector('[data-hito="' + hito + '"]');
        if (!el) return;
        el.classList.remove('hito--pendiente');
        el.querySelector('.hito__fecha').textContent = fechaTexto || '';
    }

    function render(ctx) {
        var id = parseInt(new URLSearchParams(window.location.search).get('id'), 10);
        if (!id) { window.location.href = 'mis-incidencias.html'; return; }

        G.get('/vecino/incidencia?id=' + id).then(function (res) {
            if (res.status !== 200 || !res.data || !res.data.incidencia) {
                C.UI.toast('No se encontró la incidencia.', 'error');
                setTimeout(function () { window.location.href = 'mis-incidencias.html'; }, 1200);
                return;
            }
            var i = res.data.incidencia;
            Fmt.rellenar(document.body, {
                id:          '#' + i.id,
                tipo:        i.tipo,
                validada:    i.validada === 'si' ? 'Sí' : 'No',
                contenedor:  '#' + i.id_contenedor + ' · ' + i.ubicacion,
                descripcion: i.descripcion || '—'
            });
            Fmt.badge(document.querySelector('[data-campo="estado"]'), i.estado);

            // Foto
            if (i.tiene_foto) cargarFoto(i.id, document.querySelector('[data-foto]'), document.querySelector('[data-sinfoto]'));
            else document.querySelector('[data-sinfoto]').hidden = false;

            // Línea de tiempo (hitos alcanzados)
            var iv = i.intervencion;
            alcanzar('reportada', Fmt.fechaHora(i.fecha_ing));
            if (i.validada === 'si') alcanzar('validada', 'validada');
            if (iv) alcanzar('asignada', Fmt.fechaHora(iv.fecha_inicio));
            if (iv && iv.tarea_finalizada === 'si' && i.estado === 'finalizada') alcanzar('finalizada', Fmt.fechaHora(iv.fecha_fin));
        });
    }

    C.App.iniciar({ activo: 'mis-incidencias', rol: 'vecino' }, render);
})();
