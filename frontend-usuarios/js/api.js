/* ============================================================================
 * api.js — Cliente HTTP + manejo de sesión del Frontend de Usuarios (Cathartes)
 * ----------------------------------------------------------------------------
 * Reemplaza a los antiguos app/Core/ApiClient.php (cliente cURL) y
 * app/Core/Sesion.php (sesión PHP). Este frontend es estático: HTML5 + CSS +
 * JS, sin PHP y sin acceso a base de datos. Toda operación pasa por la API de
 * Usuarios vía fetch, autenticada con un token firmado (HMAC) que emite la API.
 *
 * ============================================================================
 * DECISIÓN DE SEGURIDAD (reutilizable para el entregable de Ciberseguridad)
 * ============================================================================
 * QUÉ SE ELIGIÓ (Fase 3.95 — sesión unificada)
 *   El token de sesión (mismo HMAC-SHA256 de siempre, typ='sesion') viaja en una
 *   COOKIE `cathartes_sesion` con atributos:
 *     - HttpOnly : el JavaScript NO la puede leer.
 *     - SameSite=Lax : el navegador no la manda en requests cross-site (mitiga CSRF).
 *     - Path=/ : una sola sesión para todas las apps del host (frontend, backoffice).
 *     - Secure : según entorno (.env COOKIE_SECURE; true en prod HTTPS).
 *     - Expiración según "Recordarme": tildado -> 90 días; sin tildar -> cookie de
 *       sesión de navegador. Sin timeout por inactividad.
 *   La setea la API de Usuarios al validar el 2FA. Se abandonó el guardado en
 *   localStorage/sessionStorage con header Bearer.
 *
 * POR QUÉ EL CAMBIO
 *   Un token en Web Storage es legible por cualquier JS del origen: ante un XSS,
 *   se roba la sesión. HttpOnly cierra ese vector (el script no ve la cookie).
 *   Además unifica el login: PHP (backoffice) no puede leer localStorage, pero sí
 *   una cookie. El precio es que vuelve el riesgo de CSRF (el navegador manda la
 *   cookie sola) — se cubre abajo.
 *
 * CÓMO SE MITIGA
 *   1. CSRF: SameSite=Lax en la cookie + token CSRF por sesión en los POST con
 *      formulario (backoffice). Los POST de este frontend son fetch same-origin,
 *      que Lax ya protege. Regla derivada: ningún endpoint muta estado por GET.
 *   2. XSS: el DOM se puebla SIEMPRE con textContent, NUNCA con innerHTML a partir
 *      de datos de la API/usuario. Segunda barrera: Content-Security-Policy del
 *      lado API (pendiente de cablear en las cabeceras).
 *   3. Kill-switch por `estado`: revocar la cuenta invalida la cookie en el
 *      próximo request (401 + la API borra la cookie).
 *
 * NOTA: frontend y APIs viven en el MISMO host (Apache :80, distintas rutas), así
 *   que es todo same-origin: la cookie viaja sin CORS. En prod, mismo host o
 *   subdominios con la cookie acotada al dominio.
 *
 * CONFIG — equivalente estático del .env:
 *   Sin PHP no hay .env. El único punto a tocar entre entornos (local ->
 *   producción) es CONFIG.apiUsuarios. Nada de URLs hardcodeadas dispersas.
 * ==========================================================================*/
(function () {
    'use strict';

    var CONFIG = {
        // Bases de las tres APIs. Ajustar por entorno (equivalente al .env). Mismo
        // origen que el frontend (Apache :80) -> sin CORS. El Router de cada API
        // recorta el prefijo del subdirectorio solo, así que estas URLs sirven
        // igual en local (subcarpeta) que en el vhost raíz de producción.
        //
        // El frontend SIEMPRE habla con una API por fetch: no hay acceso a base
        // ni "driver local" acá. Que una API sirva datos reales o todavía no
        // exista es problema del lado servidor, no de este cliente. El contrato
        // de cada endpoint está en docs/contratos-api.md.
        apiUsuarios:    'http://localhost/sigeru/api-usuarios/public',
        apiGestion:     'http://localhost/sigeru/api-gestion/public',
        apiRecoleccion: 'http://localhost/sigeru/api-recoleccion/public'
    };

    var K_FLASH = 'cathartes.flash';

    // Timeout de cada request, en ms. `fetch` NO tiene timeout propio: si el servidor
    // acepta la conexión y nunca contesta, la promesa no se resuelve nunca y la
    // pantalla se queda en "Cargando…" para siempre — se ve como si estuviera rota.
    // Con esto, un request colgado termina como status 0 y cada pantalla cae a su
    // estado de error, que ya está escrito en el HTML.
    var TIMEOUT_MS = 12000;

    // -------------------------------------------------------------- Auth ----
    // Fase 3.95: la sesión vive en una cookie HttpOnly firmada que setea la API al
    // validar el 2FA. El JS NO la puede leer (esa es la gracia: resiste XSS mejor
    // que localStorage). Ya no se guarda token ni preauth en storage. "Estar
    // logueado" se comprueba pidiendo GET /yo (la cookie viaja sola). Acá solo
    // quedan los mensajes flash (UX de una sola lectura), que no son secretos.
    var Auth = {
        limpiar: function () { sessionStorage.removeItem(K_FLASH); },
        flash: function (msg) { sessionStorage.setItem(K_FLASH, msg); },
        tomarFlash: function () {
            var m = sessionStorage.getItem(K_FLASH);
            sessionStorage.removeItem(K_FLASH);
            return m;
        }
    };

    // --------------------------------------------------------------- Api ----
    /** Hace el request contra `base`. Devuelve una promesa de { status, data }
     *  (data = JSON parseado o null). Nunca rechaza: ante fallo de red devuelve
     *  status 0. La cookie de sesión viaja sola (credentials same-origin) a
     *  cualquiera de las APIs: todas comparten el mismo secreto de firma. */
    function request(base, metodo, ruta, datos) {
        var url = String(base).replace(/\/+$/, '') + '/' + String(ruta).replace(/^\/+/, '');
        var headers = { 'Accept': 'application/json' };

        // La sesión viaja en la cookie HttpOnly: credentials 'same-origin' hace que
        // el navegador la mande sola. Ya no se arma header Authorization: Bearer.
        var opts = { method: metodo, headers: headers, credentials: 'same-origin' };
        if (datos !== undefined && datos !== null) {
            if (typeof FormData !== 'undefined' && datos instanceof FormData) {
                // Multipart (subida de archivos): NO fijar Content-Type a mano —
                // el navegador arma el boundary de multipart/form-data solo.
                opts.body = datos;
            } else {
                headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(datos);
            }
        }

        // Corta el request si el servidor no contesta a tiempo. AbortController hace
        // que fetch rechace, y el catch de abajo lo convierte en status 0 — el mismo
        // camino que un fallo de red, así que las pantallas no necesitan un caso
        // aparte para "colgado".
        var reloj = null;
        if (typeof AbortController !== 'undefined') {
            var ctrl = new AbortController();
            opts.signal = ctrl.signal;
            reloj = setTimeout(function () { ctrl.abort(); }, TIMEOUT_MS);
        }
        var listo = function () { if (reloj) clearTimeout(reloj); };

        return fetch(url, opts).then(function (resp) {
            listo();
            return resp.json()
                .then(function (data) { return { status: resp.status, data: data }; })
                .catch(function () { return { status: resp.status, data: null }; });
        }).catch(function () {
            listo();
            return { status: 0, data: null };
        });
    }

    /** Fabrica un cliente {get, post} atado a una base de API. Un cliente por
     *  API; nombres y contratos de los endpoints en docs/contratos-api.md. */
    function cliente(base) {
        return {
            get:  function (ruta)        { return request(base, 'GET',  ruta, null); },
            post: function (ruta, datos) { return request(base, 'POST', ruta, datos || {}); }
        };
    }

    // Api = cliente de Usuarios (compatibilidad con acceso.js, que usa Api.get/post).
    var Api = cliente(CONFIG.apiUsuarios);

    window.Cathartes = {
        config:      CONFIG,
        Auth:        Auth,
        Api:         Api,                          // Usuarios
        Usuarios:    Api,                          // alias explícito
        Gestion:     cliente(CONFIG.apiGestion),
        Recoleccion: cliente(CONFIG.apiRecoleccion)
    };
})();
