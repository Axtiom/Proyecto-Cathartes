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

    /**
     * Última red: si algo revienta a mitad del render, las regiones que quedaron sin
     * pintar caen a su estado de error en vez de quedarse en "Cargando…". Una
     * pantalla congelada en carga se lee como rota; una que dice "no se pudo cargar"
     * se lee como lo que es.
     */
    function caerAError() {
        document.querySelectorAll('[data-region]').forEach(function (reg) {
            var cargando = reg.querySelector('[data-estado="cargando"]');
            if (cargando && !cargando.hidden && window.Cathartes.Fmt) {
                window.Cathartes.Fmt.estado(reg, 'error');
            }
        });
    }

    window.Cathartes.App = {
        iniciar: function (opts, render) {
            opts = opts || {};
            document.addEventListener('DOMContentLoaded', function () {
                Guardia.proteger().then(function (ctx) {
                    // Guardia de rol (solo UX; el límite duro está en la API, que
                    // devuelve 403 a un endpoint ajeno). El rol de la sesión sale de
                    // ctx.usuario.rol (GET /yo); el que la pantalla exige, de
                    // opts.rol. NO hay valor por defecto: asumir "vecino" hacía que
                    // una pantalla sin declarar su rol pasara el guard de cualquiera.
                    // Si la pantalla no lo declara, no se adivina: se avisa y no se
                    // aplica el guard de rol.
                    if (!opts.rol) {
                        if (window.console) console.warn('[guardia] la pantalla no declara opts.rol: no se aplica guardia de rol');
                    } else if (ctx.usuario.rol !== opts.rol) {
                        window.location.href = '403.html';
                        return;
                    }

                    // El shell se monta (async: clona el partial) ANTES de render.
                    // montar() nunca rechaza, así que render siempre corre.
                    window.Cathartes.Layout.montar(ctx, opts).then(function () {
                        if (typeof render !== 'function') return;
                        try {
                            render(ctx);
                        } catch (e) {
                            if (window.console) console.error('[guardia] falló el render de la pantalla:', e);
                            caerAError();
                        }
                    });
                });
            });
        }
    };
})();
