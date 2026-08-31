/* ============================================================================
 * acceso.js — Controladores de las pantallas de acceso (Cathartes)
 * ----------------------------------------------------------------------------
 * Reemplaza a app/Controllers/AccesoController.php. El "MVC" del frontend vive
 * en JS: el HTML es la vista, este módulo es el controlador y api.js el modelo.
 *
 * Enrutado por <body data-page="...">. Cada POST llama a la API y interpreta el
 * JSON, igual que hacía el controlador PHP.
 *
 * Login de un solo paso: usuario y contraseña alcanzan para abrir sesión (sin
 * verificación en dos pasos por correo).
 *
 * REGLA ANTI-XSS (crítica): todo texto que provenga de la API se escribe con
 * textContent, JAMÁS con innerHTML. Ver la decisión de seguridad en api.js.
 * ==========================================================================*/
(function () {
    'use strict';

    var Api  = window.Cathartes.Api;
    var Auth = window.Cathartes.Auth;

    // ------------------------------------------------------------ helpers ----
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }

    function mostrarError(msg) {
        var box = $('#error-general');
        if (!box) return;
        var p = box.querySelector('p');
        if (p) p.textContent = msg;          // textContent: sin sink de HTML
        box.hidden = false;
    }
    function ocultarError() {
        var box = $('#error-general');
        if (box) box.hidden = true;
    }
    function mostrarFlash(msg) {
        var box = $('#flash');
        if (!box || !msg) return;
        var p = box.querySelector('p');
        if (p) p.textContent = msg;
        box.hidden = false;
    }
    function setFieldError(campo, msg) {
        var el = document.querySelector('[data-error="' + campo + '"]');
        if (el) { el.textContent = msg || ''; el.hidden = !msg; }
    }
    /** Muestra el panel de éxito y oculta el formulario. */
    function mostrarPanelOk() {
        var form  = $('#form-principal');
        var panel = $('#panel-ok');
        if (form)  form.hidden = true;
        if (panel) panel.hidden = false;
    }

    // ---------------------------------------------------- controladores ----
    var Pages = {

        login: function () {
            mostrarFlash(Auth.tomarFlash()); // p. ej. "Tu sesión fue cerrada."
            var form = $('#form-principal');
            if (!form) return;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                ocultarError();
                var email    = $('#email').value.trim();
                var password = $('#password').value;
                var recordar = $('#recordar').checked;

                // Login de un solo paso: si las credenciales son válidas, la API
                // ya deja la cookie de sesión puesta y devuelve a dónde ir según el rol.
                Api.post('/login', { email: email, password: password, recordar: recordar })
                    .then(function (res) {
                        var d = res.data || {};
                        if (res.status === 200 && d.ok && d.destino) {
                            window.location.href = d.destino;
                            return;
                        }
                        mostrarError(d.error || 'No pudimos iniciar sesión.');
                    });
            });
        },

        registro: function () {
            var form = $('#form-principal');
            if (!form) return;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                ocultarError();
                setFieldError('email', '');
                var datos = {
                    nombre:           $('#nombre').value.trim(),
                    apellido:         $('#apellido').value.trim(),
                    telefono:         $('#telefono').value.trim(),
                    email:            $('#email').value.trim(),
                    password:         $('#password').value,
                    password_confirm: $('#password_confirm').value
                };
                Api.post('/registro', datos).then(function (res) {
                    var d = res.data || {};
                    if (res.status === 201 && d.ok) { mostrarPanelOk(); return; }
                    mostrarError(d.error || 'No pudimos crear la cuenta.');
                    if (d.campo) setFieldError(String(d.campo), String(d.error || ''));
                });
            });
        },

        recuperar: function () {
            var form = $('#form-principal');
            if (!form) return;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                // Respuesta SIEMPRE genérica: no revela si el correo existe.
                Api.post('/recuperar', { email: $('#email').value.trim() })
                    .then(function () { mostrarPanelOk(); });
            });
        },

        restablecer: function () {
            var params = new URLSearchParams(window.location.search);
            var token  = params.get('token') || '';
            var form   = $('#form-principal');
            if (!form) return;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                ocultarError();
                Api.post('/restablecer', {
                    token:            token,
                    password:         $('#password').value,
                    password_confirm: $('#password_confirm').value
                }).then(function (res) {
                    var d = res.data || {};
                    if (res.status === 200 && d.ok) { mostrarPanelOk(); return; }
                    mostrarError(d.error || 'No pudimos actualizar la contraseña.');
                });
            });
        }
        // (La antigua página 'inicio' se sirve ahora con guardia.js + pantallas/,
        //  no con acceso.js: se quitó de acá para no duplicar el arranque.)
    };

    // -------------------------------------------------------- arranque ----
    document.addEventListener('DOMContentLoaded', function () {
        var page = document.body.getAttribute('data-page');
        if (page && typeof Pages[page] === 'function') Pages[page]();
    });
})();
