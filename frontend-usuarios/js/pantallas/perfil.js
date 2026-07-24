/* ============================================================================
 * pantallas/perfil.js — Controlador de "Mi perfil" del vecino.
 * Pantalla: perfil.html. Datos: API de Usuarios (GET/POST /perfil, GET /mis-canjes).
 * Solo pide datos, clona los <template> (fila de teléfono, marco) y engancha
 * eventos (tabs, agregar/quitar teléfono, guardar, seleccionar marco). La
 * estructura y el texto fijo viven en perfil.html.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, U = C.Usuarios, UI = C.UI, Fmt = C.Fmt;

    function render(ctx) {
        // --- Tabs -------------------------------------------------------------
        var tabs   = { datos: document.getElementById('p-tab-datos'), pers: document.getElementById('p-tab-pers') };
        var panels = { datos: document.getElementById('p-panel-datos'), pers: document.getElementById('p-panel-pers') };
        function activar(cual) {
            Object.keys(tabs).forEach(function (k) {
                tabs[k].classList.toggle('chip--activo', k === cual);
                panels[k].hidden = k !== cual;
            });
        }
        tabs.datos.addEventListener('click', function () { activar('datos'); });
        tabs.pers.addEventListener('click',  function () { activar('pers'); });
        activar('datos');

        // --- Datos personales -------------------------------------------------
        var telsCont = document.getElementById('p-telefonos');
        var tplTel   = document.getElementById('tpl-telefono');
        function filaTel(valor) {
            var nodo = document.importNode(tplTel.content, true);
            nodo.querySelector('.p-tel').value = valor || '';
            nodo.querySelector('[data-quitar]').addEventListener('click', function (e) {
                e.target.closest('.p-tel-fila').remove();
            });
            telsCont.appendChild(nodo);
        }

        U.get('/perfil').then(function (res) {
            var p = (res.data && res.data.perfil) || { nombre: '', email: '', telefonos: [] };
            document.getElementById('p-nombre').value = p.nombre || '';
            document.getElementById('p-email').value  = p.email || '';
            telsCont.textContent = '';
            (p.telefonos || []).forEach(function (t) { filaTel(t.telefono); });
            if (!(p.telefonos || []).length) filaTel('');
        });

        document.getElementById('p-add-tel').addEventListener('click', function () { filaTel(''); });

        document.getElementById('p-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var tels = Array.prototype.map.call(document.querySelectorAll('.p-tel'), function (i) { return i.value.trim(); }).filter(Boolean);
            var datos = {
                nombre:    document.getElementById('p-nombre').value.trim(),
                email:     document.getElementById('p-email').value.trim(),
                telefonos: tels
            };
            U.post('/perfil', datos).then(function (res) {
                if (res.status === 200 && res.data && res.data.ok) UI.toast('Perfil actualizado.', 'ok');
                else UI.toast((res.data && res.data.error) || 'No se pudo guardar.', 'error');
            });
        });

        // --- Personalización: marcos desbloqueados (canjes del vecino) --------
        var galeria = document.getElementById('p-marcos');
        var vacio   = document.getElementById('p-marcos-vacio');
        var tplMarco = document.getElementById('tpl-marco');
        U.get('/mis-canjes').then(function (res) {
            var items = (res.data && res.data.items) || [];
            galeria.textContent = '';
            if (!items.length) { vacio.hidden = false; return; }
            vacio.hidden = true;
            items.forEach(function (r) {
                var nodo = document.importNode(tplMarco.content, true);
                var card = nodo.querySelector('.panel');
                card.querySelector('[data-campo="nombre"]').textContent = r.nombre;
                card.querySelector('[data-campo="fecha"]').textContent  = Fmt.fechaCorta(r.fecha);
                card.addEventListener('click', function () {
                    Array.prototype.forEach.call(galeria.children, function (ch) { ch.style.borderColor = 'var(--borde)'; });
                    card.style.borderColor = 'var(--verde)';
                    UI.toast('Marco «' + r.nombre + '» seleccionado.', 'info');
                });
                galeria.appendChild(nodo);
            });
        });
    }

    C.App.iniciar({}, render);
})();
