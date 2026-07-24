/* ============================================================================
 * layout.js — Monta el shell compartido (header + footer) en cada pantalla.
 * ----------------------------------------------------------------------------
 * Regla "el HTML manda": la estructura y el texto del header/footer viven en
 * partials/layout.html (dos <template>). Este módulo NO construye HTML con
 * createElement: clona esos templates y solo rellena los data-campo (nombre,
 * rol, saldo, iniciales) y engancha los eventos (menú del avatar, cerrar sesión).
 *
 * Además aplica el "marco" de avatar activo del vecino, derivado de sus canjes
 * (mapa explícito ID de recompensa -> clase CSS en MARCOS).
 *
 * Cathartes.Layout.montar(ctx, opts) -> Promise   (ctx: {usuario, saldo, canjes})
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};
    var Auth = window.Cathartes.Auth;
    var Api  = window.Cathartes.Api;

    // Mapa explícito y legible: ID de recompensa -> clase CSS del marco (app.css).
    var MARCOS = { 1: 'marco-reciclador', 3: 'marco-mural' };

    var partialCache = null;
    function cargarPartial() {
        if (partialCache) return Promise.resolve(partialCache);
        return fetch('partials/layout.html').then(function (r) { return r.text(); }).then(function (html) {
            partialCache = new DOMParser().parseFromString(html, 'text/html');
            return partialCache;
        });
    }

    function iniciales(nombre) {
        var p = String(nombre || '').trim().split(/\s+/);
        return ((p[0] || '').charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase() || '?';
    }

    function marcoActivo(canjes) {
        var clase = null, valor = -1;
        (canjes || []).forEach(function (c) {
            var cls = MARCOS[c.id];
            if (cls && (c.valor || 0) > valor) { clase = cls; valor = c.valor || 0; }
        });
        return clase;
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

    function armarHeader(doc, ctx, opts) {
        var frag = document.importNode(doc.getElementById('tpl-header').content, true);
        var header = frag.querySelector('.hdr');

        rellenar(header, {
            iniciales: iniciales(ctx.usuario.nombre),
            nombre:    ctx.usuario.nombre,
            rol:       ctx.usuario.rol,
            saldo:     (ctx.saldo == null ? null : ctx.saldo)
        });

        // Badge de puntos: solo si hay saldo (vecino).
        if (ctx.saldo !== null && ctx.saldo !== undefined) {
            var badge = header.querySelector('[data-badge]');
            if (badge) badge.hidden = false;
        }

        // Link de navegación activo.
        var activo = header.querySelector('[data-nav="' + (opts && opts.activo) + '"]');
        if (activo) activo.classList.add('hdr__link--activo');

        // Marco del avatar activo (derivado de los canjes del vecino).
        var marco = marcoActivo(ctx.canjes);
        var btn = header.querySelector('[data-avatar-btn]');
        if (marco && btn) btn.classList.add(marco);

        // Menú del avatar (abrir/cerrar) + cerrar sesión.
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

        return header;
    }

    function armarFooter(doc) {
        var frag = document.importNode(doc.getElementById('tpl-footer').content, true);
        return frag.querySelector('.ftr');
    }

    window.Cathartes.Layout = {
        montar: function (ctx, opts) {
            return cargarPartial().then(function (doc) {
                var header = armarHeader(doc, ctx, opts || {});
                var slotH = document.querySelector('[data-shell-header]');
                if (slotH) slotH.replaceWith(header); else document.body.insertBefore(header, document.body.firstChild);

                var footer = armarFooter(doc);
                var slotF = document.querySelector('[data-shell-footer]');
                if (slotF) slotF.replaceWith(footer); else document.body.appendChild(footer);
            });
        }
    };
})();
