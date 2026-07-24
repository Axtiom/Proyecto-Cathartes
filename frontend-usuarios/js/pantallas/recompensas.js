/* ============================================================================
 * pantallas/recompensas.js — Controlador de la pantalla de Recompensas.
 * Pantalla: recompensas.html. Datos: API de Usuarios (GET /recompensas, POST /canjes).
 * Solo hace tres cosas: pedir datos, rellenar los data-campo del <template>, y
 * enganchar el botón Canjear. La estructura y los textos están en el HTML.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes;

    function render(ctx) {
        var saldo = (ctx.saldo == null ? 0 : ctx.saldo);
        var lista = document.getElementById('lista-recompensas');
        var tpl   = document.getElementById('tpl-recompensa');
        var todas = [];

        // Actualiza TODOS los [data-campo="saldo"] a la vez: el panel de la
        // pantalla y el badge del header (mismo atributo).
        function pintarSaldo() {
            document.querySelectorAll('[data-campo="saldo"]').forEach(function (el) { el.textContent = saldo; });
        }

        function tarjeta(r) {
            var nodo = document.importNode(tpl.content, true);
            nodo.querySelector('[data-campo="nombre"]').textContent      = r.nombre;
            nodo.querySelector('[data-campo="descripcion"]').textContent = r.descripcion || '';
            nodo.querySelector('[data-campo="valor"]').textContent       = r.valor;

            var btn  = nodo.querySelector('[data-accion="canjear"]');
            var falt = nodo.querySelector('[data-faltan]');
            if (saldo >= r.valor) {
                falt.hidden = true;
                btn.hidden = false;
                btn.addEventListener('click', function () { canjear(r, btn); });
            } else {
                btn.hidden = true;
                falt.hidden = false;
                falt.querySelector('[data-campo="faltan"]').textContent = (r.valor - saldo);
            }
            return nodo;
        }

        function pintar(items) {
            lista.textContent = '';
            items.forEach(function (r) { lista.appendChild(tarjeta(r)); });
        }

        function canjear(r, btn) {
            btn.disabled = true;
            C.Usuarios.post('/canjes', { id_recompensa: r.id }).then(function (res) {
                if (res.status === 200 && res.data && res.data.ok) {
                    saldo = res.data.saldo;
                    pintarSaldo();
                    C.UI.toast('Canjeaste «' + r.nombre + '».', 'ok');
                    pintar(todas);   // recalcula habilitados / "faltan N"
                } else {
                    C.UI.toast((res.data && res.data.error) || 'No se pudo canjear.', 'error');
                    btn.disabled = false;
                }
            });
        }

        pintarSaldo();
        C.Usuarios.get('/recompensas').then(function (res) {
            todas = (res.data && res.data.items) || [];
            pintar(todas);
        });
    }

    C.App.iniciar({ activo: 'recompensas' }, render);
})();
