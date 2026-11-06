<!-- Formulario de Alta de Contenedor / Instalación -->
<form id="form-alta" action="" method="POST">
    <div class="row">
        <!-- Campos de Texto para la Dirección -->
        <div class="col-md-4 mb-3">
            <label for="calle" class="form-label">Calle (*)</label>
            <input type="text" id="calle" name="calle" class="form-control" required placeholder="Ej: Felipe Sanguinetti">
        </div>
        <div class="col-md-2 mb-3">
            <label for="numero" class="form-label">Número (*)</label>
            <input type="text" id="numero" name="numero" class="form-control" required placeholder="Ej: 2683">
        </div>
        <div class="col-md-3 mb-3">
            <label for="esquina" class="form-label">Esquina</label>
            <input type="text" id="esquina" name="esquina" class="form-control" placeholder="Ej: Fray Bentos">
        </div>
        <div class="col-md-3 mb-3">
            <label for="ubicacion_formateada" class="form-label">Ubicación Completa</label>
            <input type="text" id="ubicacion_formateada" class="form-control bg-light" readonly placeholder="Formato automático">
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="latitud" class="form-label">Latitud (*)</label>
            <input type="text" id="latitud" name="latitud" class="form-control bg-light" readonly required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="longitud" class="form-label">Longitud (*)</label>
            <input type="text" id="longitud" name="longitud" class="form-control bg-light" readonly required>
        </div>
    </div>

    <!-- Contenedor del Mapa Leaflet -->
    <div class="mb-4">
        <label class="form-label fw-bold">Seleccionar punto en el mapa de Montevideo:</label>
        <div id="mapa-alta" style="height: 380px; width: 100%; border-radius: 8px; border: 1px solid #ced4da;"></div>
    </div>

    <button type="submit" class="btn btn-primary">Guardar Registro</button>
</form>

<!-- Estilos y Script de Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // 1. Inicializar mapa centrado en Montevideo (-34.9011, -56.1674)
    const mvdCoords = [-34.9011, -56.1674];
    const map = L.map('mapa-alta').setView(mvdCoords, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;

    // 2. Manejo de clic en el mapa
    map.on('click', async (e) => {
        const { lat, lng } = e.latlng;

        // Posicionar o mover marcador
        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng, { draggable: true }).addTo(map);
            marker.on('dragend', (event) => {
                const pos = event.target.getLatLng();
                procesarCoordenadas(pos.lat, pos.lng);
            });
        }

        await procesarCoordenadas(lat, lng);
    });

    /**
     * 3 y 4. Autocompletado de coordenadas y Geocodificación Inversa con OpenStreetMap Nominatim
     */
    async function procesarCoordenadas(lat, lng) {
        const latFixed = lat.toFixed(6);
        const lngFixed = lng.toFixed(6);

        // Asignar inmediatamente los campos de latitud y longitud
        document.getElementById('latitud').value = latFixed;
        document.getElementById('longitud').value = lngFixed;

        try {
            // Consulta geocodificación inversa
            const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latFixed}&lon=${lngFixed}`;
            const res = await fetch(url);
            const data = await res.json();

            let calle = '';
            let numero = '';
            let esquina = '';

            if (data && data.address) {
                calle = data.address.road || data.address.pedestrian || '';
                numero = data.address.house_number || '';
                esquina = data.address.neighbourhood || data.address.suburb || '';

                document.getElementById('calle').value = calle;
                document.getElementById('numero').value = numero;
                document.getElementById('esquina').value = esquina;
            }

            // Formato concatenado requerido: "Calle Número, esquina Esquina, Latitud, Longitud"
            const parteCalleNum = calle ? `${calle} ${numero}`.trim() : 'Ubicación sin nombre';
            const parteEsquina = esquina ? `, esquina ${esquina}` : '';
            
            document.getElementById('ubicacion_formateada').value = `${parteCalleNum}${parteEsquina}, ${latFixed}, ${lngFixed}`;

        } catch (error) {
            console.error("Error al geocodificar las coordenadas:", error);
            document.getElementById('ubicacion_formateada').value = `Ubicación seleccionada, ${latFixed}, ${lngFixed}`;
        }
    }
});
</script>