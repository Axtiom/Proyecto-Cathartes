/* ============================================================================
 * pantallas/perfil.js — Controlador de "Mi perfil" del vecino.
 * Pantalla: perfil.html. Datos: API de Usuarios (GET/POST /perfil).
 * Solo pide datos, clona los <template> (fila de teléfono) y engancha
 * eventos (agregar/quitar teléfono, guardar). La estructura y el texto fijo
 * viven en perfil.html.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, U = C.Usuarios, UI = C.UI;

    function render(ctx) {
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
    }

    C.App.iniciar({ rol: 'vecino' }, render);
})();
