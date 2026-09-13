/* ============================================================================
 * pantallas/operario-informes.js — Informes del operario (O3).
 * Pantalla: operario-informes.html.
 *
 * La pantalla NO tiene serie histórica que mostrar (ver el comentario del .html:
 * `instalacion.ocupacion_actual` es un único valor que se pisa a sí mismo). Este
 * controlador es corto a propósito: solo rellena el ÚNICO dato que el modelo sí
 * guarda hoy — la ocupación actual — y deja el resto en su leyenda.
 *
 * No se deriva una serie de un solo valor: eso sería inventar datos.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, R = C.Recoleccion, Fmt = C.Fmt;

    function render() {
        R.get('/operario/instalacion').then(function (res) {
            if (res.status !== 200 || !res.data || !res.data.instalacion) return;
            Fmt.rellenar(document.body, {
                actual: res.data.instalacion.ocupacion_actual
            });
        });
    }

    C.App.iniciar({ activo: 'informes', rol: 'operario' }, render);
})();
