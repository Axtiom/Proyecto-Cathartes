/* ============================================================================
 * pantallas/operario-instalacion.js — Mi instalación (O2).
 * Pantalla: operario-instalacion.html.
 * Datos: API Recolección (GET /operario/instalacion, POST /operario/ocupacion).
 *
 * La validación del número de acá es UX (mensaje inmediato). El límite duro va en
 * la API con `entero_en_rango` — ver BUG-013: un `(int)` de PHP no desborda con
 * 99999999999, y el INT de MySQL sí. No alcanza con validar en el front.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, R = C.Recoleccion, Fmt = C.Fmt;

    var UMBRAL = 80;
    var INT_MAX = 2147483647;   // tope del INT con signo de MySQL (BUG-013)

    var reg, regMaq, capacidad = 0;

    function render() {
        reg    = document.getElementById('reg-instalacion');
        regMaq = document.getElementById('reg-maquinaria');
        document.getElementById('form-ocupacion').addEventListener('submit', guardar);
        cargar();
    }

    function cargar() {
        R.get('/operario/instalacion').then(function (res) {
            if (res.status !== 200 || !res.data) { Fmt.estado(reg, 'error'); return; }
            var i = res.data.instalacion;
            if (!i) { Fmt.estado(reg, 'vacio'); return; }
            pintar(i);
            pintarMaquinaria(res.data.maquinaria);
            Fmt.estado(reg, 'ok');
        });
    }

    function pintar(i) {
        capacidad = Number(i.capacidad) || 0;
        var pct = Fmt.pct(i.ocupacion_actual, capacidad);

        Fmt.rellenar(reg, {
            nombre:    i.nombre,
            tipo:      i.tipo,
            ubicacion: i.ubicacion,
            ocupacion: i.ocupacion_actual,
            capacidad: capacidad,
            libre:     Math.max(0, capacidad - (Number(i.ocupacion_actual) || 0)),
            pct:       pct
        });
        reg.querySelector('[data-barra]').style.width = pct + '%';
        reg.querySelector('[data-alerta-umbral]').hidden = pct < UMBRAL;

        var inp = document.getElementById('inp-ocupacion');
        inp.value = i.ocupacion_actual;
        inp.setAttribute('max', String(capacidad));
    }

    function pintarMaquinaria(items) {
        if (!Array.isArray(items)) { Fmt.estado(regMaq, 'error'); return; }
        if (!items.length) { Fmt.estado(regMaq, 'vacio'); return; }

        var lista = document.getElementById('lista-maquinaria');
        var tpl   = document.getElementById('tpl-maquina');
        lista.textContent = '';
        items.forEach(function (m) {
            var nodo = document.importNode(tpl.content, true);
            nodo.querySelector('[data-campo="modelo"]').textContent  = m.modelo;
            nodo.querySelector('[data-campo="detalle"]').textContent = m.tipo;
            Fmt.badge(nodo.querySelector('[data-campo="estado"]'), m.estado);
            lista.appendChild(nodo);
        });
        Fmt.estado(regMaq, 'ok');
    }

    function guardar(e) {
        e.preventDefault();
        var crudo = document.getElementById('inp-ocupacion').value.trim();

        // Mismas reglas que la API, para avisar sin ir y volver.
        if (!/^\d+$/.test(crudo)) {
            C.UI.toast('La ocupación tiene que ser un número entero.', 'error');
            return;
        }
        var v = Number(crudo);
        if (v > INT_MAX) {
            C.UI.toast('Ese número es demasiado grande.', 'error');
            return;
        }
        if (v > capacidad) {
            C.UI.toast('La ocupación (' + v + ' m³) no puede superar la capacidad (' + capacidad + ' m³).', 'error');
            return;
        }

        R.post('/operario/ocupacion', { ocupacion_actual: v }).then(function (res) {
            if (res.status === 200) {
                C.UI.toast('Ocupación registrada.', 'ok');
                cargar();
                return;
            }
            C.UI.toast((res.data && res.data.error) || 'No se pudo guardar la ocupación.', 'error');
        });
    }

    C.App.iniciar({ activo: 'instalacion', rol: 'operario' }, render);
})();
