/* ============================================================================
 * mapa.js — Módulo de mapa interactivo REUTILIZABLE (Leaflet + OpenStreetMap).
 * ----------------------------------------------------------------------------
 * Un único módulo para los 7 puntos de mapa del sistema (landing, vecino /mapa y
 * /reportar, backoffice, cuadrilla). API declarativa:
 *
 *   var m = Cathartes.Mapa.crear({
 *     contenedor: '#id-del-div',
 *     centro: [lat, lng],          // por defecto Montevideo
 *     zoom: 14,
 *     marcadores: [ { id, latitud, longitud, tipo, estado, titulo, ubicacion } ],
 *     accion: { etiqueta: 'Reportar acá', href: fn(m) } | { etiqueta, onClick: fn(m) },
 *     alSeleccionar: fn(marcador),
 *     filtros: [ { etiqueta, predicado: fn(m)->bool, alActivar?: fn(mapa) } ]
 *   });
 *   m.setMarcadores(arr);  m.centrar([lat,lng], zoom);  m.destruir();
 *
 * Los COLORES de marcador por estado son los mismos que los badges de estado
 * (Fase 1): un contenedor desbordado se ve igual de desbordado en tabla y mapa.
 * El popup y su badge se pintan con estilos inline para que el módulo funcione
 * también en la landing (que no carga app.css). Todo dato va por textContent.
 *
 * Requiere Leaflet (window.L) cargado antes (CDN con respaldo local). Si falta,
 * muestra un aviso en el contenedor en vez de romper.
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};

    var MONTEVIDEO = [-34.9011, -56.1645];

    // Misma paleta que los badges de estado (Fase 1 / app.css).
    function colorEstado(estado) {
        return {
            'funcional':    '#38D07E',
            'desbordado':   '#F5B54A',
            'no funcional': '#F58A8A',
            'dado de baja': '#5E6690',
            'en stock':     '#6FB2FB'
        }[String(estado || '').toLowerCase()] || '#6E72A0';
    }

    function badgeInline(estado) {
        var c = colorEstado(estado);
        var b = document.createElement('span');
        b.textContent = estado || '—';
        b.style.cssText = 'display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:500;color:' +
            c + ';background:' + c + '22;border:1px solid ' + c + '55;';
        return b;
    }

    function popupDom(m, accion) {
        var wrap = document.createElement('div');
        wrap.style.cssText = 'min-width:160px;font-family:inherit';
        var t = document.createElement('div');
        t.style.cssText = 'font-weight:600;margin-bottom:2px';
        t.textContent = (m.tipo === 'instalacion' ? 'Instalación' : 'Contenedor') + ' #' + m.id;
        wrap.appendChild(t);
        if (m.titulo) { var n = document.createElement('div'); n.style.cssText = 'font-size:13px;margin-bottom:2px'; n.textContent = m.titulo; wrap.appendChild(n); }
        if (m.ubicacion) { var u = document.createElement('div'); u.style.cssText = 'font-size:12px;color:#666;margin-bottom:8px'; u.textContent = m.ubicacion; wrap.appendChild(u); }
        wrap.appendChild(badgeInline(m.estado));
        if (accion && accion.etiqueta) {
            var btn;
            if (accion.href) { btn = document.createElement('a'); btn.href = accion.href(m); }
            else { btn = document.createElement('button'); btn.type = 'button'; if (accion.onClick) btn.addEventListener('click', function () { accion.onClick(m); }); }
            btn.textContent = accion.etiqueta;
            btn.style.cssText = 'display:block;margin-top:10px;text-align:center;padding:7px 10px;border-radius:8px;background:#2FBF71;color:#06210F;font-weight:600;text-decoration:none;border:none;cursor:pointer';
            wrap.appendChild(btn);
        }
        return wrap;
    }

    Cathartes.Mapa = {
        crear: function (opts) {
            opts = opts || {};
            var host = typeof opts.contenedor === 'string' ? document.querySelector(opts.contenedor) : opts.contenedor;
            if (!host) { return null; }

            if (typeof window.L === 'undefined') {
                host.textContent = 'No se pudo cargar el mapa (Leaflet no disponible).';
                host.style.cssText += 'display:flex;align-items:center;justify-content:center;color:#9AA6C4;min-height:200px';
                return null;
            }

            // Estructura interna: [chips?] + [div del mapa] + [aviso de vacío]
            host.textContent = '';
            host.style.position = host.style.position || 'relative';

            var barra = null;
            if (opts.filtros && opts.filtros.length) {
                barra = document.createElement('div');
                barra.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px';
                host.appendChild(barra);
            }

            var divMapa = document.createElement('div');
            divMapa.style.cssText = 'width:100%;height:' + (opts.alto || '420px') + ';border-radius:14px;overflow:hidden;border:1px solid #263156';
            host.appendChild(divMapa);

            var aviso = document.createElement('div');
            aviso.style.cssText = 'position:absolute;left:50%;bottom:20px;transform:translateX(-50%);background:rgba(11,16,32,.85);color:#E6EAF5;padding:8px 14px;border-radius:10px;font-size:13px;z-index:500;display:none';
            host.appendChild(aviso);

            var map = L.map(divMapa, { scrollWheelZoom: true }).setView(opts.centro || MONTEVIDEO, opts.zoom || 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'  // atribución obligatoria (licencia OSM)
            }).addTo(map);

            var capa = L.layerGroup().addTo(map);
            var todos = [];

            function dibujar(lista) {
                capa.clearLayers();
                var validos = [];
                (lista || []).forEach(function (m) {
                    if (m.latitud == null || m.longitud == null || isNaN(m.latitud) || isNaN(m.longitud)) {
                        // Coordenadas nulas: se omite sin romper y se reporta en consola.
                        if (window.console) console.warn('[Mapa] marcador sin coordenadas, omitido:', m.id, m.tipo);
                        return;
                    }
                    validos.push(m);
                    var color = colorEstado(m.estado);
                    var marker = L.circleMarker([m.latitud, m.longitud], {
                        radius: m.tipo === 'instalacion' ? 10 : 8,
                        color: '#0B1020', weight: 2, fillColor: color, fillOpacity: 0.95
                    });
                    marker.bindPopup(popupDom(m, opts.accion));
                    marker.on('click', function () { if (typeof opts.alSeleccionar === 'function') opts.alSeleccionar(m); });
                    capa.addLayer(marker);
                });

                // Estado vacío: centrado en Montevideo con mensaje (no en blanco).
                if (!validos.length) {
                    aviso.textContent = 'No hay puntos para mostrar en el mapa.';
                    aviso.style.display = 'block';
                    map.setView(opts.centro || MONTEVIDEO, opts.zoom || 12);
                } else {
                    aviso.style.display = 'none';
                    if (validos.length > 1) {
                        map.fitBounds(validos.map(function (m) { return [m.latitud, m.longitud]; }), { padding: [40, 40], maxZoom: 16 });
                    } else {
                        map.setView([validos[0].latitud, validos[0].longitud], opts.zoom || 15);
                    }
                }
            }

            // Filtros opcionales (chips con predicado). El primero queda activo.
            if (barra) {
                opts.filtros.forEach(function (f, i) {
                    var chip = document.createElement('button');
                    chip.type = 'button';
                    chip.textContent = f.etiqueta;
                    chip.style.cssText = 'padding:7px 14px;border-radius:999px;border:1px solid #263156;background:' +
                        (i === 0 ? '#2FBF7120' : '#121A2E') + ';color:' + (i === 0 ? '#38D07E' : '#9AA6C4') + ';font:inherit;font-size:13px;cursor:pointer';
                    chip.addEventListener('click', function () {
                        Array.prototype.forEach.call(barra.children, function (c) {
                            c.style.background = '#121A2E'; c.style.color = '#9AA6C4';
                        });
                        chip.style.background = '#2FBF7120'; chip.style.color = '#38D07E';
                        var filtrados = typeof f.predicado === 'function' ? todos.filter(f.predicado) : todos;
                        dibujar(filtrados);
                        if (typeof f.alActivar === 'function') f.alActivar(api);
                    });
                    barra.appendChild(chip);
                });
            }

            var api = {
                mapa: map,
                setMarcadores: function (arr) { todos = arr || []; dibujar(todos); },
                centrar: function (centro, zoom) { map.setView(centro, zoom || map.getZoom()); },
                destruir: function () { map.remove(); }
            };

            todos = opts.marcadores || [];
            dibujar(todos);
            // Leaflet necesita recalcular tamaño si el contenedor se montó recién.
            setTimeout(function () { map.invalidateSize(); }, 60);
            return api;
        }
    };
})();
