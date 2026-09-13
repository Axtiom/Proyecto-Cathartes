/* ============================================================================
 * api.js — Cliente HTTP de la Landing Page (Cathartes)
 * ----------------------------------------------------------------------------
 * La Landing es un frontend estático (HTML5 + CSS + JS). No toca la base de
 * datos: TODO dato dinámico lo obtiene consumiendo una API por HTTP (fetch).
 *
 * SEGURIDAD — esta app NO maneja credenciales:
 *   La Landing consume solo datos públicos (estadísticas abiertas). No guarda
 *   token ni maneja sesión, así que no expone secretos a un eventual XSS. El
 *   manejo de token/sesión y su análisis de riesgo viven en el otro frontend
 *   (frontend-usuarios/js/api.js), no acá.
 *
 * CONFIG — equivalente estático del .env:
 *   Al no haber PHP, no hay .env. El único punto a cambiar entre entornos
 *   (local -> producción) es CONFIG.apiGestion. Es una ruta RELATIVA a este
 *   archivo (nada de host/protocolo hardcodeado): apunta a la API de
 *   Gestión, que vive como carpeta hermana de esta landing.
 * ==========================================================================*/
(function () {
    'use strict';

    var CONFIG = {
        apiGestion: '../api-gestion/public'
    };

    /**
     * GET público. Devuelve { status, data } — data es el JSON parseado o null.
     * Nunca lanza: ante fallo de red devuelve status 0 para que el llamador
     * pueda degradar con elegancia (no romper la página).
     */
    function get(ruta) {
        var url = CONFIG.apiGestion.replace(/\/+$/, '') + '/' + String(ruta).replace(/^\/+/, '');
        return fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (resp) {
                return resp.json()
                    .then(function (data) { return { status: resp.status, data: data }; })
                    .catch(function () { return { status: resp.status, data: null }; });
            })
            .catch(function () { return { status: 0, data: null }; });
    }

    window.CathartesApi = { config: CONFIG, get: get };
})();
