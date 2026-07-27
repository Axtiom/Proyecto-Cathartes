/* ============================================================================
 * pantallas/cuadrilla-rutas.js — Mis rutas (C2).
 * Pantalla: cuadrilla-rutas.html. Datos: API Recolección (GET /cuadrilla/rutas).
 *
 * Clona una tarjeta del <template> por ruta y un chip por contenedor. No arma
 * estructura: toda está en el HTML.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, R = C.Recoleccion, Fmt = C.Fmt;

    function render() {
        var reg   = document.getElementById('reg-rutas');
        var lista = document.getElementById('lista-rutas');
        var tplR  = document.getElementById('tpl-ruta');
        var tplC  = document.getElementById('tpl-chip');

        R.get('/cuadrilla/rutas').then(function (res) {
            if (res.status !== 200 || !res.data) { Fmt.estado(reg, 'error'); return; }

            var items = res.data.items || [];
            if (!items.length) { Fmt.estado(reg, 'vacio'); return; }

            lista.textContent = '';
            items.forEach(function (r) {
                lista.appendChild(tarjeta(r, tplR, tplC));
            });
            Fmt.estado(reg, 'ok');
        });
    }

    function tarjeta(r, tplR, tplC) {
        var nodo = document.importNode(tplR.content, true);
        var art  = nodo.querySelector('.ruta-tarjeta');
        var cont = r.contenedores || [];
        var pct  = Fmt.pct(r.verificados, cont.length);

        Fmt.rellenar(art, {
            nombre:      r.nombre,
            zona:        r.zona,
            frecuencia:  r.frecuencia,
            fecha_toma:  Fmt.fechaCorta(r.fecha_toma),
            verificados: r.verificados || 0,
            total:       cont.length,
            pct:         pct + '%'
        });
        Fmt.badge(art.querySelector('[data-campo="estado"]'), r.estado);
        art.querySelector('[data-barra]').style.width = pct + '%';
        art.querySelector('[data-ir-mapa]').setAttribute('href', 'cuadrilla-mapa.html?ruta=' + r.id);

        var chips = art.querySelector('[data-chips]');
        if (!cont.length) {
            art.querySelector('[data-sin-contenedores]').hidden = false;
        } else {
            cont.forEach(function (c) {
                var chip = document.importNode(tplC.content, true);
                var span = chip.querySelector('[data-campo="texto"]');
                span.textContent = '#' + c.id + ' · ' + c.ubicacion + ' · ' + c.estado;
                span.setAttribute('title', c.tipo + ' · ' + c.estado);
                chips.appendChild(chip);
            });
        }
        return art;
    }

    C.App.iniciar({ activo: 'rutas', rol: 'cuadrilla' }, render);
})();
