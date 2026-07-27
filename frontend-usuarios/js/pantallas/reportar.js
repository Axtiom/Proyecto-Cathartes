/* ============================================================================
 * pantallas/reportar.js — Alta de incidencia del vecino.
 * Pantalla: reportar.html. Datos: API Gestión (GET /contenedores, POST /vecino/incidencias).
 * Llena el selector de contenedores, valida la foto obligatoria (tipo/tamaño),
 * muestra el contenedor en el mapita y envía el formulario (multipart).
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, G = C.Gestion;
    var MAX = 10 * 1024 * 1024;

    function render(ctx) {
        var fotoOk = false;
        var sel = document.getElementById('r-contenedor');
        var ubic = document.getElementById('r-ubicacion');
        var selTipo = document.getElementById('r-tipo');
        var fileIn = document.getElementById('r-foto');
        var drop = document.getElementById('r-drop');
        var dropTxt = document.getElementById('r-drop-txt');
        var preview = document.getElementById('r-preview');
        var btn = document.getElementById('r-enviar');
        var TXT = 'Tocá para elegir una foto (PNG o JPG, hasta 10 MB)';

        // Mapita del contenedor elegido (módulo Leaflet; opcional).
        var contsById = {}, mapaR = null;
        if (window.L && C.Mapa) mapaR = C.Mapa.crear({ contenedor: '#reportar-mapa', alto: '240px' });
        function mostrarEnMapa(id) {
            var c = contsById[id];
            if (mapaR && c) mapaR.setMarcadores([{ id: c.id, latitud: c.latitud, longitud: c.longitud, tipo: 'contenedor', estado: c.estado, titulo: c.ubicacion, ubicacion: c.tipo + ' · ' + c.ruta }]);
        }
        function revisar() { btn.disabled = !(sel.value && selTipo.value && fotoOk); }

        var preSel = new URLSearchParams(window.location.search).get('contenedor');
        G.get('/contenedores').then(function (res) {
            ((res.data && res.data.items) || []).forEach(function (c) {
                contsById[c.id] = c;
                var o = document.createElement('option');   // <option> = dato de tabla, no estructura de pantalla
                o.value = String(c.id);
                o.textContent = '#' + c.id + ' · ' + c.ubicacion;
                o.setAttribute('data-ubic', c.ubicacion);
                sel.appendChild(o);
            });
            if (preSel) { sel.value = String(preSel); var op = sel.options[sel.selectedIndex]; if (op) ubic.textContent = op.getAttribute('data-ubic') || ''; mostrarEnMapa(preSel); revisar(); }
        });

        sel.addEventListener('change', function () {
            var op = sel.options[sel.selectedIndex];
            ubic.textContent = op && op.getAttribute('data-ubic') ? op.getAttribute('data-ubic') : '';
            mostrarEnMapa(sel.value); revisar();
        });
        selTipo.addEventListener('change', revisar);

        drop.addEventListener('click', function () { fileIn.click(); });
        fileIn.addEventListener('change', function () {
            fotoOk = false; preview.hidden = true; drop.classList.remove('foto-drop--cargada'); dropTxt.textContent = TXT;
            var f = fileIn.files && fileIn.files[0];
            if (!f) { revisar(); return; }
            if (!(/image\/(png|jpe?g)/.test(f.type) || /\.(png|jpe?g)$/i.test(f.name))) { C.UI.toast('La foto debe ser PNG o JPG.', 'error'); fileIn.value = ''; revisar(); return; }
            if (f.size > MAX) { C.UI.toast('La foto supera los 10 MB.', 'error'); fileIn.value = ''; revisar(); return; }
            fotoOk = true; drop.classList.add('foto-drop--cargada');
            dropTxt.textContent = f.name + ' · ' + Math.round(f.size / 1024) + ' KB';
            preview.src = URL.createObjectURL(f); preview.hidden = false; revisar();
        });

        document.getElementById('r-form').addEventListener('submit', function (e) {
            e.preventDefault();
            if (btn.disabled) return;
            btn.disabled = true;
            var fd = new FormData();
            fd.append('id_contenedor', sel.value);
            fd.append('tipo', selTipo.value);
            fd.append('descripcion', document.getElementById('r-desc').value.trim());
            fd.append('foto', fileIn.files[0]);
            G.post('/vecino/incidencias', fd).then(function (res) {
                if (res.status === 201 && res.data && res.data.ok) {
                    C.Auth.flash('Incidencia reportada. Suma 150 puntos al validarse.');
                    window.location.href = 'mis-incidencias.html';
                } else {
                    C.UI.toast((res.data && res.data.error) || 'No se pudo reportar.', 'error');
                    btn.disabled = false;
                }
            });
        });
    }

    C.App.iniciar({ activo: 'reportar', rol: 'vecino' }, render);
})();
