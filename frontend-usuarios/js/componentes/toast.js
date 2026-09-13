/* ============================================================================
 * componentes/toast.js — Toast de feedback efímero.
 * ----------------------------------------------------------------------------
 * UI.toast(mensaje, tipo, ms)
 *   mensaje : texto (textContent)
 *   tipo    : 'ok' | 'error' | 'info'  (default 'info')
 *   ms      : duración en ms (default 3200)
 * Crea la zona contenedora la primera vez y apila toasts abajo a la derecha.
 * ==========================================================================*/
(function () {
    'use strict';
    window.Cathartes = window.Cathartes || {};
    var UI = window.Cathartes.UI = window.Cathartes.UI || {};

    function zona() {
        var z = document.querySelector('.toast-zona');
        if (!z) {
            z = document.createElement('div');
            z.className = 'toast-zona';
            document.body.appendChild(z);
        }
        return z;
    }

    UI.toast = function (mensaje, tipo, ms) {
        var t = document.createElement('div');
        t.className = 'toast toast--' + (tipo || 'info');
        t.setAttribute('role', 'status');
        t.textContent = String(mensaje == null ? '' : mensaje);
        zona().appendChild(t);

        var dur = typeof ms === 'number' ? ms : 3200;
        setTimeout(function () {
            t.style.transition = 'opacity 0.25s';
            t.style.opacity = '0';
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 260);
        }, dur);
        return t;
    };
})();
