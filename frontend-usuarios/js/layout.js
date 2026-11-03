/* ============================================================================
 * layout.js — Monta el shell compartido (header + footer) en cada pantalla.
 * ----------------------------------------------------------------------------
 * Regla "el HTML manda": la estructura y el texto del header/footer viven en
 * partials/layout.html (dos <template>). Este módulo NO construye HTML con
 * createElement: clona esos templates y solo rellena los data-campo (nombre,
 * rol, iniciales) y engancha los eventos (menú del avatar, cerrar sesión).
 *
 * UN HEADER POR ROL (Fase 3): el partial trae tpl-header-vecino,
 * tpl-header-cuadrilla y tpl-header-operario. Acá se clona el del rol de la sesión
 * — el rol sale de ctx.usuario.rol, que viene de GET /yo. NO hay rol por defecto:
 * si la sesión no dice el rol, no se adivina.
 *
 * Cathartes.Layout.montar(ctx, opts) -> Promise que NUNCA rechaza. Si el shell
 * falla (no se pudo bajar el partial, falta un template), se resuelve igual para
 * que la pantalla siga cargando sus datos: una pantalla sin header es fea, una
 * pantalla congelada en "Cargando…" parece rota.
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};
    var Auth = window.Cathartes.Auth;
    var Api  = window.Cathartes.Api;

    var partialCache = null;

    /**
     * Baja el partial del chrome. `cache: 'no-cache'` NO es paranoia: el partial se
     * pide por fetch, así que el navegador lo cachea como cualquier recurso, y una
     * versión vieja combinada con un layout.js nuevo deja el chrome roto sin un solo
     * error visible. Con 'no-cache' el navegador revalida contra el servidor (un 304
     * barato si no cambió) y nunca se sirve un partial rancio. Ver BUG-016.
     *
     * El cacheo en memoria (`partialCache`) se mantiene: es por carga de página, así
     * que hay un solo request por pantalla.
     */
    function cargarPartial() {
        if (partialCache) return Promise.resolve(partialCache);
        return fetch('partials/layout.html', { cache: 'no-cache' })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                partialCache = new DOMParser().parseFromString(html, 'text/html');
                return partialCache;
            });
    }

    function iniciales(nombre) {
        var p = String(nombre || '').trim().split(/\s+/);
        return ((p[0] || '').charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase() || '?';
    }

    /** Rellena los [data-campo] de un fragmento con un objeto {campo: valor}. */
    function rellenar(root, datos) {
        root.querySelectorAll('[data-campo]').forEach(function (el) {
            var k = el.getAttribute('data-campo');
            if (Object.prototype.hasOwnProperty.call(datos, k) && datos[k] !== null && datos[k] !== undefined) {
                el.textContent = String(datos[k]);   // dato de la API por textContent (anti-XSS)
            }
        });
    }

    function cerrarSesion(e) {
        if (e) e.preventDefault();
        Api.post('/logout').then(function () { Auth.limpiar(); window.location.href = 'login.html'; });
    }

    /**
     * Muestra los bloques [data-rol] que corresponden al rol y deja ocultos los
     * demás. Se usa en el header y en el footer.
     */
    function porRol(root, rol) {
        root.querySelectorAll('[data-rol]').forEach(function (el) {
            el.hidden = el.getAttribute('data-rol') !== rol;
        });
    }

    /** Clona un template del partial dentro de cada punto marcado con `attr`. */
    function insertar(doc, raiz, attr, idTpl) {
        var tpl = doc.getElementById(idTpl);
        if (!tpl) return;
        raiz.querySelectorAll('[' + attr + ']').forEach(function (slot) {
            slot.replaceWith(document.importNode(tpl.content, true));
        });
    }

    function armarHeader(doc, ctx, opts) {
        var rol = ctx.usuario.rol;
        var tpl = doc.getElementById('tpl-header-' + rol);
        var frag, header;

        if (!tpl) {
            // Falta el header de ese rol. NO se cae al header del vecino: mostrarle a
            // una cuadrilla links que no le corresponden es el bug que se venía
            // arrastrando (BUG-014). Pero tampoco se devuelve nada: sin header el
            // usuario se queda SIN poder cerrar sesión, que es peor (BUG-016).
            //
            // Se usa tpl-header-minimo, que está escrito en el partial: marca y
            // avatar, sin nav. Una nav vacía es honesta; una nav ajena, no.
            if (window.console) console.error('[layout] no hay header para el rol "' + rol + '": se usa el mínimo');
            tpl = doc.getElementById('tpl-header-minimo');
        }
        if (!tpl) return null;

        frag = document.importNode(tpl.content, true);
        header = frag.querySelector('.hdr');
        if (!header) return null;

        // Piezas comunes: logo e avatar viven en su propio template.
        insertar(doc, header, 'data-logo', 'tpl-logo');
        insertar(doc, header, 'data-avatar', 'tpl-avatar');

        // Ítems del menú acotados a un rol (hoy: «Mi perfil», solo del vecino).
        porRol(header, rol);

        rellenar(header, {
            iniciales: iniciales(ctx.usuario.nombre),
            nombre:    ctx.usuario.nombre,
            rol:       ctx.usuario.rol
        });

        // Link de navegación activo. El header ya es el del rol, así que no hace
        // falta acotar por data-rol.
        var activo = header.querySelector('[data-nav="' + (opts && opts.activo) + '"]');
        if (activo) activo.classList.add('hdr__link--activo');

        // Menú del avatar (abrir/cerrar) + cerrar sesión.
        var btn  = header.querySelector('[data-avatar-btn]');
        var menu = header.querySelector('[data-avatar-menu]');
        if (btn && menu) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var abierto = !menu.hidden;
                menu.hidden = abierto;
                btn.setAttribute('aria-expanded', String(!abierto));
            });
            document.addEventListener('click', function () { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); });
        }
        var salir = header.querySelector('[data-accion="logout"]');
        if (salir) salir.addEventListener('click', cerrarSesion);

        // Campana de notificaciones: resaltada en su pantalla y con el globito de «sin leer».
        var campana = header.querySelector('[data-campana]');
        if (campana) {
            if (opts && opts.activo === 'notificaciones') campana.classList.add('campana--activa');
            Api.get('/notificaciones/contador').then(function (res) {
                var n = res && res.status === 200 && res.data ? Number(res.data.no_leidas) : 0;
                var globo = campana.querySelector('[data-campana-n]');
                if (globo && n > 0) {
                    globo.textContent = n > 99 ? '99+' : String(n);
                    globo.hidden = false;
                    campana.setAttribute('aria-label', 'Notificaciones: ' + n + ' sin leer');
                }
            }).catch(function () { /* sin contador: la campana se ve igual */ });
        }

        return header;
    }

    function armarFooter(doc, ctx) {
        var frag = document.importNode(doc.getElementById('tpl-footer').content, true);
        var footer = frag.querySelector('.ftr');
        porRol(footer, ctx.usuario.rol);
        return footer;
    }

    window.Cathartes.Layout = {
        /**
         * Monta el shell. La promesa SIEMPRE se resuelve: si el partial no baja o un
         * template no está, se loguea y se sigue. Antes, un error acá rechazaba la
         * promesa, el render de la pantalla nunca corría y todas las regiones
         * quedaban en "Cargando…" para siempre — se veía como una pantalla rota.
         */
        montar: function (ctx, opts) {
            return cargarPartial().then(function (doc) {
                var header = armarHeader(doc, ctx, opts || {});
                var slotH = document.querySelector('[data-shell-header]');
                if (header) {
                    if (slotH) slotH.replaceWith(header);
                    else document.body.insertBefore(header, document.body.firstChild);
                }

                var footer = armarFooter(doc, ctx);
                var slotF = document.querySelector('[data-shell-footer]');
                if (footer) {
                    if (slotF) slotF.replaceWith(footer);
                    else document.body.appendChild(footer);
                }
            }).catch(function (e) {
                if (window.console) console.error('[layout] no se pudo montar el shell:', e);
            });
        }
    };
})();
