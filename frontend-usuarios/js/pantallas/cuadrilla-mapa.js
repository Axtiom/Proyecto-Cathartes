/* ============================================================================
 * pantallas/cuadrilla-mapa.js — Mapa operativo de la cuadrilla (C4).
 * Pantalla: cuadrilla-mapa.html. Datos: API Recolección (GET /cuadrilla/mapa).
 *
 * Reusa Cathartes.Mapa. Marcar un contenedor como recorrido va por POST y se
 * persiste en `verificacion`; nada muta estado por GET.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, R = C.Recoleccion, Fmt = C.Fmt;

    var reg, mapa = null, datos = null;

    function render() {
        reg = document.getElementById('reg-mapa');
        var ruta = Number(new URLSearchParams(location.search).get('ruta')) || 0;
        cargar(ruta ? ('/cuadrilla/mapa?ruta=' + ruta) : '/cuadrilla/mapa');
    }

    function cargar(url) {
        R.get(url).then(function (res) {
            if (res.status !== 200 || !res.data) { Fmt.estado(reg, 'error'); return; }
            if (!res.data.ruta) { Fmt.estado(reg, 'vacio'); return; }
            datos = res.data;
            Fmt.estado(reg, 'ok');
            pintar();
        });
    }

    function pintar() {
        var cont = datos.contenedores || [];
        var verif = cont.filter(function (c) { return c.verificado_hoy; }).length;
        var pct = Fmt.pct(verif, cont.length);

        Fmt.rellenar(reg, {
            ruta_nombre: datos.ruta.zona + ' · ' + datos.ruta.nombre,
            verificados: verif,
            total:       cont.length,
            pct:         pct + '%'
        });
        reg.querySelector('[data-barra]').style.width = pct + '%';
        reg.querySelector('[data-sin-contenedores]').hidden = cont.length > 0;

        // Solo los que tienen coordenadas se pueden dibujar; el resto se avisa.
        var conCoords = cont.filter(function (c) { return c.latitud !== null && c.longitud !== null; });
        reg.querySelector('[data-sin-coords]').hidden = conCoords.length === cont.length;

        var marcadores = conCoords.map(function (c) {
            return {
                id: c.id, latitud: c.latitud, longitud: c.longitud,
                tipo: 'contenedor', estado: c.estado,
                titulo: c.ubicacion, ubicacion: c.tipo + (c.verificado_hoy ? ' · verificado hoy' : '')
            };
        });

        if (mapa) mapa.setMarcadores(marcadores);
        else mapa = C.Mapa.crear({ contenedor: '#mapa-cuadrilla', marcadores: marcadores, alto: '440px' });

        pintarPanel(cont);
    }

    function pintarPanel(cont) {
        var lista = document.getElementById('lista-proximos');
        var tpl   = document.getElementById('tpl-proximo');
        lista.textContent = '';

        cont.forEach(function (c) {
            var nodo = document.importNode(tpl.content, true);
            nodo.querySelector('[data-campo="ubicacion"]').textContent = c.ubicacion;
            nodo.querySelector('[data-campo="detalle"]').textContent   = c.tipo;
            Fmt.badge(nodo.querySelector('[data-campo="estado"]'), c.estado);

            var btn = nodo.querySelector('[data-accion="verificar"]');
            var ok  = nodo.querySelector('[data-verificado]');
            if (c.verificado_hoy) {
                btn.hidden = true;      // ya verificado: no se ofrece de nuevo
                ok.hidden = false;
            } else {
                btn.addEventListener('click', function () { verificar(c.id, btn); });
            }
            lista.appendChild(nodo);
        });
    }

    function verificar(idContenedor, btn) {
        btn.disabled = true;
        R.post('/cuadrilla/contenedor/verificar', { id_contenedor: idContenedor }).then(function (res) {
            if (res.status === 200) {
                C.UI.toast('Contenedor marcado como recorrido.', 'ok');
                cargar(location.search.indexOf('ruta=') > -1
                    ? '/cuadrilla/mapa' + location.search
                    : '/cuadrilla/mapa');
                return;
            }
            btn.disabled = false;
            C.UI.toast((res.data && res.data.error) || 'No se pudo registrar la verificación.', 'error');
        });
    }

    C.App.iniciar({ activo: 'mapa', rol: 'cuadrilla' }, render);
})();
