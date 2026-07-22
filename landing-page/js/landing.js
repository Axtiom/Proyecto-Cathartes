/* ============================================================================
 * landing.js — Poblado del DOM de la Landing con datos de la API de Gestión.
 * ----------------------------------------------------------------------------
 * Reemplaza al viejo Model PHP (app/Models/Estadisticas.php). Los números NO
 * se hardcodean: se piden a la API y se escriben en los [data-stat] del HTML.
 *
 * DEGRADADO ELEGANTE (requisito de negocio):
 *   Si el endpoint todavía no existe o falla, la página queda intacta y
 *   estilada, cada estadística muestra un guión "—", y solo se emite un
 *   console.warn. Nunca una página rota ni errores rojos en consola.
 *
 * SEGURIDAD (anti-XSS): el DOM se puebla SIEMPRE con textContent, nunca con
 * innerHTML. Ningún dato de la API se interpola como HTML.
 * ==========================================================================*/
(function () {
    'use strict';

    // Endpoint previsto (aún no implementado en api-gestion). Forma esperada:
    //   { "hero.contenedores": "1240", "stat.resueltas": "312", ... }
    // Se lee de forma resiliente: cualquier [data-stat] sin dato queda en "—".
    var ENDPOINT = '/estadisticas/publicas';

    function poblar(mapa) {
        var nodos = document.querySelectorAll('[data-stat]');
        for (var i = 0; i < nodos.length; i++) {
            var clave = nodos[i].getAttribute('data-stat');
            if (mapa && Object.prototype.hasOwnProperty.call(mapa, clave) &&
                mapa[clave] !== null && mapa[clave] !== undefined) {
                // textContent (nunca innerHTML): el dato de la API no puede inyectar HTML.
                nodos[i].textContent = String(mapa[clave]);
            }
            // Si no vino la clave, se respeta el "—" que ya trae el HTML.
        }
    }

    if (!window.CathartesApi) {
        console.warn('[landing] api.js no disponible: se mantienen los valores por defecto ("—").');
        return;
    }

    window.CathartesApi.get(ENDPOINT).then(function (res) {
        if (res.status === 200 && res.data && typeof res.data === 'object') {
            poblar(res.data);
        } else {
            console.warn('[landing] Estadísticas no disponibles (status ' + res.status +
                '). Se muestran guiones hasta que la API de Gestión exponga ' + ENDPOINT + '.');
        }
    });
})();
