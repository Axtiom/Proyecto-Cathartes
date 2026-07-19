<?php
/**
 * Botón flotante (FAB) — "Reportar incidencia".
 * Fijo abajo a la derecha, sigue el scroll. Solo NAVEGA al punto de
 * entrada de la app de reportes (no consume APIs).
 */
?>
<a class="fab" href="<?= View::e(Config::get('enlaces.reportar')) ?>" aria-label="Reportar incidencia">
    <svg class="fab__icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
        <path d="M12 5v14M5 12h14"/>
    </svg>
    <span class="fab__texto">Reportar incidencia</span>
</a>
