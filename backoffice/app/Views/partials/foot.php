<?php
/** Chrome del Backoffice (parte inferior): cierra <main> y el shell. */
?>
    </main>
</div>

<!-- Aviso flotante (esquina inferior izquierda). Lo dispara cualquier control con
     el atributo `data-aviso`: el texto del mensaje está escrito en el HTML de la
     pantalla que lo usa, no acá ni en el JS. Sirve para explicar en el momento por
     qué una acción no está disponible, sin sacar al admin de la pantalla. -->
<div class="bo-toast" id="bo-toast" role="status" aria-live="polite" hidden>
    <span class="bo-toast__icono" aria-hidden="true">⛔</span>
    <span class="bo-toast__txt" data-campo="texto"></span>
    <button type="button" class="bo-toast__x" aria-label="Cerrar aviso">×</button>
</div>

<script>
// Único JS del chrome: muestra y esconde el aviso flotante. No arma estructura ni
// texto fijo — el mensaje viene del `data-aviso` del control que lo dispara, y
// entra por textContent (nunca innerHTML).
(function () {
    var toast = document.getElementById('bo-toast');
    if (!toast) return;
    var txt   = toast.querySelector('[data-campo="texto"]');
    var reloj = null;

    function mostrar(mensaje) {
        txt.textContent = mensaje;
        toast.hidden = false;
        clearTimeout(reloj);
        reloj = setTimeout(ocultar, 6000);
    }
    function ocultar() {
        toast.hidden = true;
        clearTimeout(reloj);
    }

    toast.querySelector('.bo-toast__x').addEventListener('click', ocultar);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') ocultar();
    });
    document.querySelectorAll('[data-aviso]').forEach(function (el) {
        el.addEventListener('click', function () { mostrar(el.getAttribute('data-aviso')); });
    });
})();
</script>
</body>
</html>
