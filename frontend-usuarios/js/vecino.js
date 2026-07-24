/* ============================================================================
 * vecino.js — Controladores del universo Vecino (Fase 2).
 * ----------------------------------------------------------------------------
 * El "MVC" del frontend vive en JS: el HTML es la vista, este módulo el
 * controlador, api.js el modelo. Todo dato sale por fetch de las APIs
 * (Cathartes.Gestion para incidencias/contenedores, Cathartes.Usuarios para el
 * saldo). CERO datos de demo. Anti-XSS: todo texto de la API va por textContent.
 *
 * Cada pantalla se arranca con Cathartes.App.iniciar({activo}, Vecino.<pantalla>).
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes;
    var UI = C.UI, G = C.Gestion;

    // ------------------------------------------------------------ helpers ----
    function partes(iso) {
        return String(iso || '').match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    }
    function fechaCorta(iso) { var m = partes(iso); return m ? (m[3] + '/' + m[2] + '/' + m[1]) : ''; }
    function fechaHora(iso)  { var m = partes(iso); return m ? (m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5]) : ''; }

    function el(tag, cls, texto) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (texto !== undefined && texto !== null) e.textContent = String(texto);
        return e;
    }
    function limpiar(nodo) { if (nodo) nodo.textContent = ''; }

    // Carga una foto protegida (necesita Bearer, que <img src> no manda): se baja
    // por fetch con el token y se pinta como object URL.
    function cargarFoto(id, imgEl, onFalla) {
        var base = C.config.apiGestion.replace(/\/+$/, '');
        fetch(base + '/vecino/incidencia-foto?id=' + id, {
            headers: { 'Authorization': 'Bearer ' + C.Auth.token() }
        }).then(function (r) {
            if (!r.ok) throw new Error('sin foto');
            return r.blob();
        }).then(function (b) {
            imgEl.src = URL.createObjectURL(b);
            imgEl.hidden = false;
        }).catch(function () { if (onFalla) onFalla(); });
    }

    // ============================================================ /inicio ====
    function inicio(ctx) {
        document.getElementById('v-nombre').textContent = (ctx.usuario.nombre || '').split(/\s+/)[0] || ctx.usuario.nombre || '';

        // Métricas: saldo (ya viene en ctx) + en curso/finalizadas (API).
        var grid = document.getElementById('v-metricas');
        G.get('/vecino/metricas').then(function (res) {
            var m = (res.data && res.data.metricas) || { en_curso: 0, finalizadas_anio: 0 };
            limpiar(grid);
            grid.appendChild(UI.tarjetaMetrica({ valor: (ctx.saldo == null ? 0 : ctx.saldo), etiqueta: 'Puntos disponibles', pie: '150 pts por incidencia validada' }));
            grid.appendChild(UI.tarjetaMetrica({ valor: m.en_curso, etiqueta: 'Incidencias en curso' }));
            grid.appendChild(UI.tarjetaMetrica({ valor: m.finalizadas_anio, etiqueta: 'Resueltas este año' }));
        });

        // Incidencias recientes (últimas 5)
        var cont = document.getElementById('v-recientes');
        G.get('/vecino/incidencias?limite=5').then(function (res) {
            var items = (res.data && res.data.items) || [];
            limpiar(cont);
            if (!items.length) { cont.appendChild(UI.estadoVacio({ titulo: 'Todavía no reportaste nada', texto: 'Cuando reportes una incidencia, la vas a ver acá.' })); return; }
            var lista = el('div', 'lista');
            items.forEach(function (i) {
                var a = document.createElement('a');
                a.className = 'lista__item';
                a.href = 'incidencia.html?id=' + i.id;
                var izq = el('div');
                izq.appendChild(el('div', 'principal', '#' + i.id + ' · ' + i.tipo));
                izq.appendChild(el('div', 'secundario', fechaCorta(i.fecha_ing) + ' · ' + i.ubicacion));
                a.appendChild(izq);
                a.appendChild(UI.badgeEstado(i.estado));
                lista.appendChild(a);
            });
            cont.appendChild(lista);
        });

        // Contenedores (el MER no modela "zona" por vecino: se listan los activos)
        var cc = document.getElementById('v-contenedores');
        G.get('/contenedores').then(function (res) {
            var items = (res.data && res.data.items) || [];
            limpiar(cc);
            if (!items.length) { cc.appendChild(UI.estadoVacio({ titulo: 'Sin contenedores', texto: 'No hay contenedores cargados.' })); return; }
            var lista = el('div', 'lista');
            items.forEach(function (c) {
                var row = el('div', 'lista__item');
                var izq = el('div');
                izq.appendChild(el('div', 'principal', c.ubicacion));
                izq.appendChild(el('div', 'secundario', 'Contenedor #' + c.id + ' · ' + c.tipo + ' · ' + c.ruta));
                row.appendChild(izq);
                row.appendChild(UI.badgeEstado(c.estado));
                lista.appendChild(row);
            });
            cc.appendChild(lista);
        });
    }

    // ========================================================== /reportar ====
    function reportar(ctx) {
        var TIPOS = ['desbordado', 'roto', 'falta de recoleccion'];
        var MAX = 10 * 1024 * 1024;
        var fotoOk = false;

        var sel   = document.getElementById('r-contenedor');
        var ubic  = document.getElementById('r-ubicacion');
        var selTipo = document.getElementById('r-tipo');
        var fileIn = document.getElementById('r-foto');
        var drop   = document.getElementById('r-drop');
        var dropTxt= document.getElementById('r-drop-txt');
        var preview= document.getElementById('r-preview');
        var btn    = document.getElementById('r-enviar');

        // tipos
        TIPOS.forEach(function (t) { var o = document.createElement('option'); o.value = t; o.textContent = t; selTipo.appendChild(o); });

        // Mapa chico del contenedor elegido (módulo Leaflet; opcional si no cargó)
        var contsById = {}, mapaR = null;
        if (window.L && C.Mapa) mapaR = C.Mapa.crear({ contenedor: '#reportar-mapa', alto: '240px' });
        function mk(c) {
            return { id: c.id, latitud: c.latitud, longitud: c.longitud, tipo: 'contenedor', estado: c.estado, titulo: c.ubicacion, ubicacion: c.tipo + ' · ' + c.ruta };
        }
        function mostrarEnMapa(id) { var c = contsById[id]; if (mapaR && c) mapaR.setMarcadores([mk(c)]); }

        // contenedores (con preselección opcional vía ?contenedor=N, p. ej. desde el mapa)
        var preSel = new URLSearchParams(window.location.search).get('contenedor');
        G.get('/contenedores').then(function (res) {
            var items = (res.data && res.data.items) || [];
            items.forEach(function (c) {
                contsById[c.id] = c;
                var o = document.createElement('option');
                o.value = String(c.id);
                o.textContent = '#' + c.id + ' · ' + c.ubicacion;
                o.setAttribute('data-ubic', c.ubicacion);
                sel.appendChild(o);
            });
            if (preSel) {
                sel.value = String(preSel);
                var op = sel.options[sel.selectedIndex];
                if (op) ubic.textContent = op.getAttribute('data-ubic') || '';
                mostrarEnMapa(preSel);
                revisar();
            }
        });
        sel.addEventListener('change', function () {
            var op = sel.options[sel.selectedIndex];
            ubic.textContent = op && op.getAttribute('data-ubic') ? op.getAttribute('data-ubic') : '';
            mostrarEnMapa(sel.value);
            revisar();
        });
        selTipo.addEventListener('change', revisar);

        // foto: abrir selector al click en el drop
        drop.addEventListener('click', function () { fileIn.click(); });
        fileIn.addEventListener('change', function () {
            fotoOk = false; preview.hidden = true; drop.classList.remove('foto-drop--cargada');
            var f = fileIn.files && fileIn.files[0];
            if (!f) { dropTxt.textContent = 'Tocá para elegir una foto (PNG o JPG, hasta 10 MB)'; revisar(); return; }
            var tipoOk = /image\/(png|jpe?g)/.test(f.type) || /\.(png|jpe?g)$/i.test(f.name);
            if (!tipoOk) { UI.toast('La foto debe ser PNG o JPG.', 'error'); fileIn.value = ''; dropTxt.textContent = 'Tocá para elegir una foto (PNG o JPG, hasta 10 MB)'; revisar(); return; }
            if (f.size > MAX) { UI.toast('La foto supera los 10 MB.', 'error'); fileIn.value = ''; dropTxt.textContent = 'Tocá para elegir una foto (PNG o JPG, hasta 10 MB)'; revisar(); return; }
            fotoOk = true;
            drop.classList.add('foto-drop--cargada');
            dropTxt.textContent = f.name + ' · ' + Math.round(f.size / 1024) + ' KB';
            preview.src = URL.createObjectURL(f); preview.hidden = false;
            revisar();
        });

        function revisar() { btn.disabled = !(sel.value && selTipo.value && fotoOk); }
        revisar();

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
                    return;
                }
                UI.toast((res.data && res.data.error) || 'No se pudo reportar.', 'error');
                btn.disabled = false;
            });
        });
    }

    // ==================================================== /mis-incidencias ====
    function misIncidencias(ctx) {
        var flash = C.Auth.tomarFlash();      // p. ej. "Incidencia reportada…"
        if (flash) UI.toast(flash, 'ok');

        var chipsCont = document.getElementById('mi-chips');
        var tablaCont = document.getElementById('mi-tabla');
        var todas = [];

        var FILTROS = {
            todas:       function () { return true; },
            curso:       function (i) { return i.estado === 'en curso'; },
            validadas:   function (i) { return i.validada === 'si'; },
            finalizadas: function (i) { return i.estado === 'finalizada'; }
        };

        function render(filtro) {
            var filas = todas.filter(FILTROS[filtro] || FILTROS.todas);
            limpiar(tablaCont);
            tablaCont.appendChild(UI.tablaPaginada({
                porPagina: 10,
                columnas: [
                    { clave: 'id', titulo: 'Nº', mono: true },
                    { titulo: 'Fecha', render: function (f) { return fechaCorta(f.fecha_ing); } },
                    { clave: 'ubicacion', titulo: 'Ubicación' },
                    { clave: 'tipo', titulo: 'Tipo' },
                    { titulo: 'Estado', render: function (f) { return UI.badgeEstado(f.estado); } }
                ],
                filas: filas,
                onFila: function (f) { window.location.href = 'incidencia.html?id=' + f.id; },
                vacio: { titulo: 'Sin incidencias', texto: 'No hay incidencias con ese filtro.' }
            }));
        }

        chipsCont.appendChild(UI.chipsFiltro({
            opciones: [
                { valor: 'todas',       etiqueta: 'Todas' },
                { valor: 'curso',       etiqueta: 'En curso' },
                { valor: 'validadas',   etiqueta: 'Validadas' },
                { valor: 'finalizadas', etiqueta: 'Finalizadas' }
            ],
            activo: 'todas',
            onChange: render
        }));

        G.get('/vecino/incidencias').then(function (res) {
            todas = (res.data && res.data.items) || [];
            render('todas');
        });
    }

    // ==================================================== /mis-incidencias/{id}
    function detalle(ctx) {
        var id = parseInt(new URLSearchParams(window.location.search).get('id'), 10);
        if (!id) { window.location.href = 'mis-incidencias.html'; return; }

        G.get('/vecino/incidencia?id=' + id).then(function (res) {
            if (res.status !== 200 || !res.data || !res.data.incidencia) {
                UI.toast('No se encontró la incidencia.', 'error');
                setTimeout(function () { window.location.href = 'mis-incidencias.html'; }, 1200);
                return;
            }
            var i = res.data.incidencia;
            document.getElementById('d-titulo').textContent = 'Incidencia #' + i.id;

            // Foto
            var img = document.getElementById('d-foto');
            var sinFoto = document.getElementById('d-sinfoto');
            if (i.tiene_foto) { cargarFoto(i.id, img, function () { sinFoto.hidden = false; }); }
            else { sinFoto.hidden = false; }

            // Datos
            var datos = document.getElementById('d-datos');
            function linea(k, v) {
                var row = el('div', 'dato-linea');
                row.appendChild(el('span', null, k));
                if (v instanceof Node) { var w = el('span'); w.appendChild(v); row.appendChild(w); }
                else { row.appendChild(el('span', null, v)); }
                datos.appendChild(row);
            }
            linea('Tipo', i.tipo);
            linea('Estado', UI.badgeEstado(i.estado));
            linea('Validada', i.validada === 'si' ? 'Sí' : 'No');
            linea('Contenedor', '#' + i.id_contenedor + ' · ' + i.ubicacion);
            linea('Descripción', i.descripcion || '—');
            linea('Puntos otorgados', i.validada === 'si' ? '150' : '—');

            // Línea de tiempo
            var iv = i.intervencion;
            var hitos = [
                { titulo: 'Reportada',          fecha: i.fecha_ing,               alcanzado: true },
                { titulo: 'Validada',           fecha: null,                      alcanzado: i.validada === 'si' },
                { titulo: 'Cuadrilla asignada', fecha: iv ? iv.fecha_inicio : null, alcanzado: !!iv },
                { titulo: 'Finalizada',         fecha: iv ? iv.fecha_fin : null,  alcanzado: !!(iv && iv.tarea_finalizada === 'si' && i.estado === 'finalizada') }
            ];
            var lt = document.getElementById('d-timeline');
            hitos.forEach(function (h) {
                var hito = el('div', 'hito' + (h.alcanzado ? '' : ' hito--pendiente'));
                var eje = el('div', 'hito__eje');
                eje.appendChild(el('div', 'hito__punto'));
                eje.appendChild(el('div', 'hito__linea'));
                hito.appendChild(eje);
                var cuerpo = el('div', 'hito__cuerpo');
                cuerpo.appendChild(el('div', 'hito__titulo', h.titulo));
                if (h.alcanzado && h.fecha) cuerpo.appendChild(el('div', 'hito__fecha', fechaHora(h.fecha)));
                else if (!h.alcanzado) cuerpo.appendChild(el('div', 'hito__fecha', 'pendiente'));
                hito.appendChild(cuerpo);
                lt.appendChild(hito);
            });
        });
    }

    // ------------------------------------------------ helpers Tanda B ----
    function actualizarBadgeHeader(saldo) {
        var spans = document.querySelectorAll('.badge-puntos span');
        var last = spans[spans.length - 1];
        if (last) last.textContent = saldo + ' pts';
    }

    // ============================================================== /mapa ====
    // Usa el módulo reutilizable Cathartes.Mapa (Leaflet/OSM). El screen solo
    // trae los datos, define los filtros y la acción del popup.
    function mapa(ctx) {
        G.get('/contenedores/todos').then(function (res) {
            var marcadores = ((res.data && res.data.items) || []).map(function (c) {
                return {
                    id: c.id, latitud: c.latitud, longitud: c.longitud,
                    tipo: 'contenedor', estado: c.estado,
                    titulo: c.ubicacion, ubicacion: c.tipo + ' · ' + c.ruta
                };
            });
            C.Mapa.crear({
                contenedor: '#mapa-vecino',
                marcadores: marcadores,
                accion: { etiqueta: 'Reportar acá', href: function (m) { return 'reportar.html?contenedor=' + m.id; } },
                filtros: [
                    { etiqueta: 'Todos',          predicado: function () { return true; } },
                    { etiqueta: 'Cerca tuyo',     predicado: function () { return true; }, alActivar: function (api) {
                        if (!navigator.geolocation) { UI.toast('Tu navegador no da ubicación.', 'error'); return; }
                        navigator.geolocation.getCurrentPosition(
                            function (pos) { api.centrar([pos.coords.latitude, pos.coords.longitude], 15); },
                            function () { UI.toast('No pudimos obtener tu ubicación.', 'info'); }
                        );
                    } },
                    { etiqueta: 'Desbordados',    predicado: function (m) { return m.estado === 'desbordado'; } },
                    { etiqueta: 'No funcionales', predicado: function (m) { return m.estado === 'no funcional'; } }
                ]
            });
        });
    }

    // ======================================================= /recompensas ====
    function recompensas(ctx) {
        var saldo = (ctx.saldo == null ? 0 : ctx.saldo);
        var elSaldo = document.getElementById('rec-saldo');
        var cat = document.getElementById('rec-catalogo');
        elSaldo.textContent = saldo + ' pts';

        var lista = [];
        function render() {
            limpiar(cat);
            lista.forEach(function (r) {
                var card = el('div', 'panel');
                card.appendChild(el('div', 'metrica__valor', r.valor + ' pts'));
                card.appendChild(el('div', 'principal', r.nombre));
                card.appendChild(el('div', 'secundario', r.descripcion || ''));
                var btn = document.createElement('button');
                btn.className = 'cta'; btn.style.marginTop = '14px';
                if (saldo >= r.valor) {
                    btn.textContent = 'Canjear';
                    btn.addEventListener('click', function () {
                        btn.disabled = true;
                        C.Usuarios.post('/canjes', { id_recompensa: r.id }).then(function (res) {
                            if (res.status === 200 && res.data && res.data.ok) {
                                saldo = res.data.saldo;
                                elSaldo.textContent = saldo + ' pts';
                                actualizarBadgeHeader(saldo);
                                UI.toast('Canjeaste «' + r.nombre + '».', 'ok');
                                render();
                            } else {
                                UI.toast((res.data && res.data.error) || 'No se pudo canjear.', 'error');
                                btn.disabled = false;
                            }
                        });
                    });
                } else {
                    btn.textContent = 'Faltan ' + (r.valor - saldo) + ' puntos';
                    btn.disabled = true;
                    btn.style.opacity = '0.5'; btn.style.cursor = 'default';
                }
                card.appendChild(btn);
                cat.appendChild(card);
            });
        }

        C.Usuarios.get('/recompensas').then(function (res) {
            lista = (res.data && res.data.items) || [];
            render();
        });
    }

    // =================================================== /recompensas/saldo ===
    function saldoMovimientos(ctx) {
        document.getElementById('s-num').textContent = (ctx.saldo == null ? 0 : ctx.saldo);
        var cont = document.getElementById('s-tabla');
        C.Usuarios.get('/movimientos').then(function (res) {
            var items = (res.data && res.data.items) || [];
            limpiar(cont);
            cont.appendChild(UI.tablaPaginada({
                porPagina: 10,
                columnas: [
                    { titulo: 'Fecha', render: function (f) { return fechaCorta(f.fecha); } },
                    { clave: 'concepto', titulo: 'Concepto' },
                    { titulo: 'Puntos', render: function (f) {
                        var s = el('span', null, (f.puntos > 0 ? '+' : '') + f.puntos);
                        s.style.color = f.puntos > 0 ? 'var(--verde-vivo)' : 'var(--rojo)';
                        s.style.fontFamily = 'var(--fuente-mono)';
                        return s;
                    } }
                ],
                filas: items,
                vacio: { titulo: 'Sin movimientos', texto: 'Todavía no tenés movimientos de puntos.' }
            }));
        });
    }

    // ============================================================ /perfil ====
    function perfil(ctx) {
        // Tabs
        var tabs = { datos: document.getElementById('p-tab-datos'), pers: document.getElementById('p-tab-pers') };
        var panels = { datos: document.getElementById('p-panel-datos'), pers: document.getElementById('p-panel-pers') };
        function activar(cual) {
            Object.keys(tabs).forEach(function (k) {
                tabs[k].classList.toggle('chip--activo', k === cual);
                panels[k].hidden = k !== cual;
            });
        }
        tabs.datos.addEventListener('click', function () { activar('datos'); });
        tabs.pers.addEventListener('click', function () { activar('pers'); });
        activar('datos');

        // --- Datos personales ---
        var telsCont = document.getElementById('p-telefonos');
        function filaTel(valor) {
            var row = el('div'); row.style.cssText = 'display:flex;gap:8px;margin-bottom:8px';
            var inp = document.createElement('input');
            inp.type = 'text'; inp.value = valor || ''; inp.placeholder = '09X XXX XXX';
            inp.className = 'p-tel';
            inp.style.cssText = 'flex:1;background:var(--superficie2);border:1px solid var(--borde);border-radius:10px;color:var(--texto);font:inherit;padding:10px 12px';
            var del = document.createElement('button');
            del.type = 'button'; del.className = 'chip'; del.textContent = '✕';
            del.addEventListener('click', function () { row.remove(); });
            row.appendChild(inp); row.appendChild(del);
            telsCont.appendChild(row);
        }

        C.Usuarios.get('/perfil').then(function (res) {
            var p = (res.data && res.data.perfil) || { nombre: '', email: '', telefonos: [] };
            document.getElementById('p-nombre').value = p.nombre || '';
            document.getElementById('p-email').value = p.email || '';
            limpiar(telsCont);
            (p.telefonos || []).forEach(function (t) { filaTel(t.telefono); });
            if (!(p.telefonos || []).length) filaTel('');
        });

        document.getElementById('p-add-tel').addEventListener('click', function () { filaTel(''); });

        document.getElementById('p-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var tels = Array.prototype.map.call(document.querySelectorAll('.p-tel'), function (i) { return i.value.trim(); }).filter(Boolean);
            var datos = { nombre: document.getElementById('p-nombre').value.trim(), email: document.getElementById('p-email').value.trim(), telefonos: tels };
            C.Usuarios.post('/perfil', datos).then(function (res) {
                if (res.status === 200 && res.data && res.data.ok) UI.toast('Perfil actualizado.', 'ok');
                else UI.toast((res.data && res.data.error) || 'No se pudo guardar.', 'error');
            });
        });

        // --- Personalización: marcos desbloqueados (canjes del vecino) ---
        var galeria = document.getElementById('p-marcos');
        C.Usuarios.get('/mis-canjes').then(function (res) {
            var items = (res.data && res.data.items) || [];
            limpiar(galeria);
            if (!items.length) { galeria.appendChild(UI.estadoVacio({ titulo: 'Sin marcos desbloqueados', texto: 'Canjeá recompensas para desbloquear marcos de perfil.' })); return; }
            var grid = el('div', 'catalogo');
            items.forEach(function (r) {
                var card = el('div', 'panel'); card.style.cursor = 'pointer';
                card.appendChild(el('div', 'principal', r.nombre));
                card.appendChild(el('div', 'secundario', 'Desbloqueado el ' + fechaCorta(r.fecha)));
                card.addEventListener('click', function () {
                    Array.prototype.forEach.call(grid.children, function (ch) { ch.style.borderColor = 'var(--borde)'; });
                    card.style.borderColor = 'var(--verde)';
                    UI.toast('Marco «' + r.nombre + '» seleccionado.', 'info');
                });
                grid.appendChild(card);
            });
            galeria.appendChild(grid);
        });
    }

    // -------------------------------------------------------- export ----
    C.Vecino = {
        inicio: inicio,
        reportar: reportar,
        misIncidencias: misIncidencias,
        detalle: detalle,
        mapa: mapa,
        recompensas: recompensas,
        saldo: saldoMovimientos,
        perfil: perfil
    };
})();
