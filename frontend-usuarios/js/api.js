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
 * QUÉ SE ELIGIÓ
 *   El token de sesión se guarda en Web Storage del navegador y se envía en
 *   cada request como header `Authorization: Bearer <token>`.
 *     - "Recordarme" TILDADO   -> localStorage   (persiste ~90 días; el TTL real
 *                                  lo fija la firma del token del lado de la API).
 *     - "Recordarme" DESTILDADO -> sessionStorage (muere al cerrar el navegador).
 *   Esto preserva la especificación de auth ya definida (duración según
 *   "Recordarme", sin timeout por inactividad). Se descarta CSRF: al autenticar
 *   con Bearer en storage (no con cookies enviadas automáticamente por el
 *   navegador), no existe el vector de request cross-site montado sobre una
 *   cookie ambiental. CSRF deja de aplicar.
 *
 * QUÉ RIESGO SE ACEPTA
 *   Un token en localStorage/sessionStorage es legible por cualquier JavaScript
 *   que corra en el origen. Por lo tanto, el riesgo principal pasa a ser XSS:
 *   un script inyectado podría leer el token y robar la sesión. (Una cookie
 *   HttpOnly no sería legible por JS, pero reintroduce CSRF y no encaja con el
 *   modelo Bearer/stateless de la API. Se optó por Bearer + cierre de XSS.)
 *
 * CÓMO SE MITIGA
 *   El riesgo se cierra en el CÓDIGO, no en la configuración:
 *   1. El DOM se puebla SIEMPRE con textContent / createElement, NUNCA con
 *      innerHTML a partir de datos de la API o del usuario. Sin sink de HTML,
 *      no hay inyección. (Ver acceso.js: todo dato externo va por textContent.)
 *   2. Las APIs deben enviar `Content-Security-Policy` para bloquear scripts
 *      inline/externos no autorizados como segunda barrera. Config base y punto
 *      de ajuste: se define del lado de la API (api-usuarios), en su front
 *      controller / .htaccess — PENDIENTE en este frontend estático, que no
 *      puede emitir cabeceras por sí mismo. Ver nota CORS/CSP más abajo.
 *   3. El kill-switch por `estado` de la API sigue vigente: revocar la cuenta
 *      invalida el token en el próximo request, aunque haya sido robado.
 *
 * NOTA CORS/CSP (dependencia del lado API, fuera del alcance de esta corrida):
 *   Al ser fetch cross-origin (frontend y API en orígenes distintos), la API
 *   de Usuarios debe responder con cabeceras CORS (Access-Control-Allow-Origin
 *   acotado al origen del frontend) y con Content-Security-Policy. Ambas se
 *   configuran en api-usuarios; acá quedan anotadas como pendiente.
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

    var K_TOKEN    = 'cathartes.token';
    var K_PREAUTH  = 'cathartes.preauth';
    var K_RECORDAR = 'cathartes.recordar';
    var K_FLASH    = 'cathartes.flash';

    // -------------------------------------------------------------- Auth ----
    var Auth = {
        /** Guarda el token según "Recordarme": localStorage (persistente) o
         *  sessionStorage (muere al cerrar el navegador). */
        guardarToken: function (token, recordar) {
            this.limpiar();
            (recordar ? localStorage : sessionStorage).setItem(K_TOKEN, token);
        },
        token: function () {
            return localStorage.getItem(K_TOKEN) || sessionStorage.getItem(K_TOKEN);
        },
        autenticado: function () {
            return this.token() !== null;
        },
        limpiar: function () {
            localStorage.removeItem(K_TOKEN);
            sessionStorage.removeItem(K_TOKEN);
            sessionStorage.removeItem(K_PREAUTH);
            sessionStorage.removeItem(K_RECORDAR);
        },

        // Handoff efímero del 2FA entre /login y /verificar (tab-scoped).
        guardarPreauth: function (preauth, recordar) {
            sessionStorage.setItem(K_PREAUTH, preauth);
            sessionStorage.setItem(K_RECORDAR, recordar ? '1' : '0');
        },
        preauth: function () { return sessionStorage.getItem(K_PREAUTH); },
        recordarPreauth: function () { return sessionStorage.getItem(K_RECORDAR) === '1'; },
        borrarPreauth: function () {
            sessionStorage.removeItem(K_PREAUTH);
            sessionStorage.removeItem(K_RECORDAR);
        },

        // Mensajes flash de una sola lectura (p. ej. "Tu sesión fue cerrada.").
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
     *  status 0. El token (si hay) viaja como Bearer a cualquiera de las APIs:
     *  todas comparten el mismo secreto de firma. */
    function request(base, metodo, ruta, datos) {
        var url = String(base).replace(/\/+$/, '') + '/' + String(ruta).replace(/^\/+/, '');
        var headers = { 'Accept': 'application/json' };

        var token = Auth.token();
        if (token) headers['Authorization'] = 'Bearer ' + token;

        var opts = { method: metodo, headers: headers };
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

        return fetch(url, opts).then(function (resp) {
            return resp.json()
                .then(function (data) { return { status: resp.status, data: data }; })
                .catch(function () { return { status: resp.status, data: null }; });
        }).catch(function () {
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
