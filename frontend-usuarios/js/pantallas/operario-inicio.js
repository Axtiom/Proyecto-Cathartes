/* ============================================================================
 * pantallas/operario-inicio.js — Inicio del operario (O1).
 * Pantalla: operario.html. Datos: API Recolección (GET /operario/resumen).
 *
 * El «residuo que maneja» es una etiqueta DERIVADA del tipo de instalación: no hay
 * columna de tipos de residuo en el modelo. El mapa está acá, a la vista, y no
 * escondido en la API.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, R = C.Recoleccion, Fmt = C.Fmt;

    var UMBRAL = 80; // % de ocupación que dispara la alerta (regla del proyecto)

    // tipo de instalación -> residuo que maneja (etiqueta derivada, no columna)
    var RESIDUO = {
        'centro de acopio': 'reciclable',
        'vertedero':        'basura'
    };

    function render(ctx) {
        var reg = document.getElementById('reg-resumen');
        Fmt.rellenar(document.body, { nombre: ctx.usuario.nombre || 'operario' });

        R.get('/operario/resumen').then(function (res) {
            if (res.status !== 200 || !res.data) { Fmt.estado(reg, 'error'); return; }

            var i = res.data.instalacion;
            if (!i) { Fmt.estado(reg, 'vacio'); return; }

            var m   = res.data.maquinaria || { funcional: 0, total: 0 };
            var pct = Fmt.pct(i.ocupacion_actual, i.capacidad);

            Fmt.rellenar(reg, {
                instalacion_nombre:    i.nombre,
                instalacion_tipo:      i.tipo,
                instalacion_ubicacion: i.ubicacion,
                residuo:               RESIDUO[i.tipo] || '—',
                pct:                   pct,
                capacidad:             i.capacidad,
                libre:                 Math.max(0, (i.capacidad || 0) - (i.ocupacion_actual || 0)),
                maq_ok:                m.funcional || 0,
                maq_total:             m.total || 0
            });
            reg.querySelector('[data-barra]').style.width = pct + '%';
            reg.querySelector('[data-alerta-umbral]').hidden = pct < UMBRAL;
            Fmt.estado(reg, 'ok');
        });
    }

    C.App.iniciar({ activo: 'inicio', rol: 'operario' }, render);
})();
