/* ============================================================================
 * pantallas/mapa.js — Mapa de contenedores del vecino.
 * Pantalla: mapa.html. Datos: API Gestión (GET /contenedores/todos).
 * Usa el módulo reutilizable Cathartes.Mapa (js/mapa.js, Leaflet/OSM): este
 * archivo solo trae los datos, define los filtros y la acción del popup.
 * ==========================================================================*/
(function () {
    'use strict';
    var C = window.Cathartes, G = C.Gestion;

    function render(ctx) {
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
                        if (!navigator.geolocation) { C.UI.toast('Tu navegador no da ubicación.', 'error'); return; }
                        navigator.geolocation.getCurrentPosition(
                            function (pos) { api.centrar([pos.coords.latitude, pos.coords.longitude], 15); },
                            function () { C.UI.toast('No pudimos obtener tu ubicación.', 'info'); }
                        );
                    } },
                    { etiqueta: 'Desbordados',    predicado: function (m) { return m.estado === 'desbordado'; } },
                    { etiqueta: 'No funcionales', predicado: function (m) { return m.estado === 'no funcional'; } }
                ]
            });
        });
    }

    C.App.iniciar({ activo: 'mapa', rol: 'vecino' }, render);
})();
