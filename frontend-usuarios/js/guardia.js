/* ============================================================================
 * guardia.js — Guardia de sesión + bootstrap de las pantallas autenticadas.
 * ----------------------------------------------------------------------------
 * Cathartes.Guardia.proteger() -> Promise<{ usuario, saldo }>
 *   Valida el token contra la API ANTES de renderizar:
 *     - sin token en storage          -> redirige a login.html
 *     - GET /yo != 200 (401, red, ...) -> limpia, avisa y redirige a login.html
 *       (el kill-switch por estado vive del lado API: si la cuenta dejó de estar
 *        activa, /yo responde 401 y acá se cae la sesión sola)
 *     - OK -> resuelve { usuario, saldo }. El saldo sale de /saldo (null si no
 *       es vecino: los otros roles no muestran badge de puntos).
 *
 * Cathartes.App.iniciar(opts, render)
 *   Azúcar: corre la guardia, monta el shell (header/footer) y recién entonces
 *   llama a render(ctx) para el contenido propio de la pantalla. Así ninguna
 *   vista se dibuja antes de confirmar la sesión.
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};
    var Auth = window.Cathartes.Auth;
    var Api  = window.Cathartes.Api;

    function aLogin(mensajeFlash) {
        Auth.limpiar();
        if (mensajeFlash) Auth.flash(mensajeFlash);
        window.location.href = 'login.html';
    }

    var Guardia = window.Cathartes.Guardia = {
        proteger: function () {
            return new Promise(function (resolve) {
                // No se puede saber desde JS si hay cookie (es HttpOnly): se pregunta
                // a la API con /yo (la cookie viaja sola) y se maneja el 401.
                Api.get('/yo').then(function (res) {
                    var d = res.data || {};
                    if (res.status === 200 && d.usuario) {
                        // Sesión válida. Buscar saldo (badge) y canjes (marco del
                        // avatar). No bloqueantes: si fallan, el header degrada.
                        var esVecino = d.usuario.rol === 'vecino';
                        Promise.all([
                            Api.get('/saldo'),
                            esVecino ? Api.get('/mis-canjes') : Promise.resolve({ status: 0, data: null })
                        ]).then(function (rs) {
                            var saldo  = (rs[0].status === 200 && rs[0].data) ? rs[0].data.saldo : null;
                            var canjes = (rs[1].status === 200 && rs[1].data) ? (rs[1].data.items || []) : [];
                            resolve({ usuario: d.usuario, saldo: saldo, canjes: canjes });
                        });
                        return;
                    }
                    // 401 = cuenta desactivada (kill-switch) o token inválido/vencido.
                    aLogin('Tu sesión fue cerrada.');
                });
            });
        }
    };

    window.Cathartes.App = {
        iniciar: function (opts, render) {
            document.addEventListener('DOMContentLoaded', function () {
                Guardia.proteger().then(function (ctx) {
                    // Guardia de rol (solo UX; el límite duro está en la API, que
                    // devuelve 403 a un endpoint ajeno). Las pantallas de este
                    // frontend son de vecino salvo que digan otra cosa (opts.rol).
                    var rolEsperado = (opts && opts.rol) || 'vecino';
                    if (ctx.usuario.rol !== rolEsperado) {
                        window.location.href = '403.html';
                        return;
                    }
                    // El shell se monta (async: clona el partial) ANTES de render.
                    window.Cathartes.Layout.montar(ctx, opts || {}).then(function () {
                        if (typeof render === 'function') render(ctx);
                    });
                });
            });
        }
    };
})();
