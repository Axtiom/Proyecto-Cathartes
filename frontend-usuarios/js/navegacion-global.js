/**
 * Script Global de Navegación y Control de Accesos - SiGeRU
 */
const BASE_URL = "http://localhost/Proyecto-Cathartes-main";

document.addEventListener("DOMContentLoaded", () => {
    configurarBotonesMarca();
    configurarBotonInicioYDashboard();
});

/**
 * 1. Redirige todos los elementos de marca/logo "Cathartes" a la Landing Page pública
 */
function configurarBotonesMarca() {
    const selectoresMarca = document.querySelectorAll('.brand-logo, .navbar-brand, .logo-cathartes, [data-brand="cathartes"]');
    
    selectoresMarca.forEach(elemento => {
        elemento.style.cursor = 'pointer';
        elemento.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = `${BASE_URL}/landing-page/index.html`;
        });
    });
}

/**
 * 2. Configura el botón "Inicio / Dashboard" según la sesión activa del usuario
 */
function configurarBotonInicioYDashboard() {
    const botonesInicio = document.querySelectorAll('.nav-link-inicio, #btn-dashboard, [data-nav="inicio"]');
    if (!botonesInicio.length) return;

    // Recuperar datos de sesión guardados en localStorage al hacer login
    const sesionUsuario = JSON.parse(localStorage.getItem('usuario_sigeru') || '{}');
    const rolUsuario = (sesionUsuario.rol || sesionUsuario.perfil || '').toLowerCase();

    let rutaDestino = `${BASE_URL}/frontend-usuarios/inicio.html`; // Por defecto (Vecino/General)

    switch (rolUsuario) {
        case 'vecino':
        case 'usuario':
            rutaDestino = `${BASE_URL}/frontend-usuarios/inicio.html`;
            break;
        case 'operario':
            rutaDestino = `${BASE_URL}/frontend-usuarios/operario.html`;
            break;
        case 'cuadrilla':
            rutaDestino = `${BASE_URL}/frontend-usuarios/cuadrilla.html`;
            break;
        case 'administrador':
        case 'admin':
            rutaDestino = `${BASE_URL}/backoffice/public/usuarios`;
            break;
    }

    botonesInicio.forEach(boton => {
        // Asegurar que el texto visual sea "Inicio" si decía "Dashboard"
        if (boton.innerText.trim().toLowerCase() === 'dashboard') {
            boton.innerText = 'Inicio';
        }
        
        boton.setAttribute('href', rutaDestino);
        boton.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = rutaDestino;
        });
    });
}