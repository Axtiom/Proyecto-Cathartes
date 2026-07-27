/* ============================================================================
 * pantallas/cuadrilla-intervenciones.js — Intervenciones de la cuadrilla (C3).
 * Pantalla: cuadrilla-intervenciones.html.
 * Datos: API Recolección (ver el contrato en docs/contrato-api-recoleccion.md).
 *
 * Maestro-detalle. Las tres acciones que cierran el ciclo de la incidencia van por
 * POST (nada muta estado por GET): estado del contenedor, repuestos usados y cierre
 * de la tarea. Toda la estructura está en el HTML; acá solo se clona y se rellena.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, R = C.Recoleccion, Fmt = C.Fmt;

    var FILTROS = {
        abiertas:    function (i) { return i.tarea_finalizada === 'no'; },
        finalizadas: function (i) { return i.tarea_finalizada === 'si'; },
        todas:       function () { return true; }
    };

    var regLista, regDet, regRep, todas = [], filtro = 'abiertas', actual = null;

    function render() {
        regLista = document.getElementById('reg-lista');
        regDet   = document.getElementById('reg-detalle');
        regRep   = document.getElementById('reg-repuestos');

        document.querySelectorAll('#chips .chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                document.querySelectorAll('#chips .chip').forEach(function (c) { c.classList.remove('chip--activo'); });
                chip.classList.add('chip--activo');
                filtro = chip.getAttribute('data-filtro');
                pintarLista();
            });
        });

        regDet.querySelector('[data-form="estado"]').addEventListener('submit', guardarEstado);
        regDet.querySelector('[data-form="repuesto"]').addEventListener('submit', agregarRepuesto);
        regDet.querySelector('[data-accion="finalizar"]').addEventListener('click', finalizar);

        cargarLista(Number(new URLSearchParams(location.search).get('id')) || 0);
        cargarInventario();
    }

    // ------------------------------------------------------------- maestro ----
    function cargarLista(abrir) {
        R.get('/cuadrilla/intervenciones').then(function (res) {
            if (res.status !== 200 || !res.data) { Fmt.estado(regLista, 'error'); return; }
            todas = res.data.items || [];
            pintarLista();
            if (abrir) abrirDetalle(abrir);
        });
    }

    function pintarLista() {
        var arr = todas.filter(FILTROS[filtro] || FILTROS.todas);
        if (!arr.length) { Fmt.estado(regLista, 'vacio'); return; }

        var lista = document.getElementById('lista-intervenciones');
        var tpl   = document.getElementById('tpl-item');
        lista.textContent = '';
        arr.forEach(function (i) {
            var nodo = document.importNode(tpl.content, true);
            var btn  = nodo.querySelector('.lista__item');
            nodo.querySelector('[data-campo="titulo"]').textContent  = '#' + i.id_incidencia + ' · ' + i.tipo;
            nodo.querySelector('[data-campo="detalle"]').textContent = Fmt.fechaCorta(i.fecha_inicio) + ' · ' + i.ubicacion;
            var badge = nodo.querySelector('[data-campo="estado"]');
            badge.textContent = i.tarea_finalizada === 'si' ? 'finalizada' : 'abierta';
            badge.className = 'badge badge--' + (i.tarea_finalizada === 'si' ? 'funcional' : 'desbordado');
            if (actual === i.id_intervencion) btn.classList.add('lista__item--activo');
            btn.addEventListener('click', function () { abrirDetalle(i.id_intervencion); });
            lista.appendChild(nodo);
        });
        Fmt.estado(regLista, 'ok');
    }

    // ------------------------------------------------------------- detalle ----
    function abrirDetalle(id) {
        actual = id;
        pintarLista();
        Fmt.estado(regDet, 'cargando');
        R.get('/cuadrilla/intervencion?id=' + id).then(function (res) {
            if (res.status !== 200 || !res.data || !res.data.intervencion) { Fmt.estado(regDet, 'error'); return; }
            pintarDetalle(res.data.intervencion, res.data.repuestos || []);
        });
    }

    function pintarDetalle(d, usados) {
        var cerrada = d.tarea_finalizada === 'si';
        Fmt.rellenar(regDet, {
            id_incidencia: d.id_incidencia,
            tipo:          d.tipo,
            contenedor:    '#' + d.id_contenedor + ' · ' + d.ubicacion,
            vecino:        d.vecino || '—',
            fecha_inicio:  Fmt.fechaHora(d.fecha_inicio),
            descripcion:   d.descripcion || 'Sin descripción',
            fecha_fin:     Fmt.fechaHora(d.fecha_fin)
        });
        Fmt.badge(regDet.querySelector('[data-campo="estado_incidencia"]'), d.estado_incidencia);
        Fmt.badge(regDet.querySelector('[data-campo="estado_contenedor"]'), d.estado_contenedor);

        var badgeFin = regDet.querySelector('[data-campo="tarea_finalizada"]');
        badgeFin.textContent = cerrada ? 'sí' : 'no';
        badgeFin.className = 'badge badge--' + (cerrada ? 'funcional' : 'desbordado');

        regDet.querySelector('#sel-estado').value = d.estado_contenedor || 'funcional';
        regDet.querySelector('[data-fin]').hidden = !cerrada;
        regDet.querySelector('[data-accion="finalizar"]').hidden = cerrada;
        regDet.querySelector('[data-ya-finalizada]').hidden = !cerrada;

        pintarUsados(usados);
        Fmt.estado(regDet, 'ok');
    }

    function pintarUsados(usados) {
        var caja = regRep.querySelector('[data-usados]');
        var tpl  = document.getElementById('tpl-usado');
        caja.textContent = '';
        regRep.querySelector('[data-sin-usados]').hidden = usados.length > 0;
        caja.hidden = usados.length === 0;
        usados.forEach(function (u) {
            var nodo = document.importNode(tpl.content, true);
            nodo.querySelector('[data-campo="nombre"]').textContent  = u.nombre;
            nodo.querySelector('[data-campo="detalle"]').textContent = u.tipo;
            nodo.querySelector('[data-campo="cuantos"]').textContent = u.cuantos;
            caja.appendChild(nodo);
        });
    }

    // ----------------------------------------------------------- inventario ----
    function cargarInventario() {
        R.get('/cuadrilla/repuestos').then(function (res) {
            if (res.status !== 200 || !res.data) { Fmt.estado(regRep, 'error'); return; }
            var items = res.data.items || [];
            if (!items.length) { Fmt.estado(regRep, 'vacio'); return; }

            var sel = regRep.querySelector('#sel-repuesto');
            var tpl = document.getElementById('tpl-opcion');
            sel.textContent = '';
            items.forEach(function (r) {
                var op = document.importNode(tpl.content, true).querySelector('option');
                op.value = r.id;
                op.textContent = r.nombre + ' · ' + r.tipo + ' · quedan ' + r.cantidad;
                sel.appendChild(op);
            });
            Fmt.estado(regRep, 'ok');
        });
    }

    // -------------------------------------------------------------- acciones ----
    /**
     * Las tres acciones hacen lo mismo: POST con el id de la intervención abierta,
     * y si sale bien se recarga el detalle (y la lista, porque el badge de la fila
     * puede haber cambiado). Van por un solo camino para no repetirlo tres veces.
     */
    function accion(url, extra, exito) {
        if (!actual) return;
        var cuerpo = { id_intervencion: actual };
        Object.keys(extra || {}).forEach(function (k) { cuerpo[k] = extra[k]; });

        R.post(url, cuerpo).then(function (res) {
            if (res.status !== 200) {
                C.UI.toast((res.data && res.data.error) || 'No se pudo guardar. Probá de nuevo.', 'error');
                return;
            }
            C.UI.toast(exito, 'ok');
            cargarLista(actual);   // recarga lista y reabre el detalle
        });
    }

    function guardarEstado(e) {
        e.preventDefault();
        accion('/cuadrilla/contenedor/estado',
            { estado: regDet.querySelector('#sel-estado').value },
            'Estado del contenedor actualizado.');
    }

    function agregarRepuesto(e) {
        e.preventDefault();
        accion('/cuadrilla/intervencion/repuestos', {
            id_repuesto: Number(regRep.querySelector('#sel-repuesto').value),
            cuantos:     Number(regRep.querySelector('#inp-cuantos').value)
        }, 'Repuesto registrado.');
    }

    function finalizar() {
        accion('/cuadrilla/intervencion/finalizar', {}, 'Tarea marcada como finalizada.');
    }

    C.App.iniciar({ activo: 'intervenciones', rol: 'cuadrilla' }, render);
})();
