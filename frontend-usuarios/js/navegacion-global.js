/**
 * Navegación Global y Control de Enlaces - Proyecto Cathartes (SiGeRU)
 */
const BASE_URL = "http://localhost/Proyecto-Cathartes";

document.addEventListener("DOMContentLoaded", () => {
    inicializarRedireccionesMarca();
    inicializarRedireccionesInicio();
});

/**
 * Redirige cualquier clic en logos/nombres "Cathartes" a la Landing Page pública
 */
function inicializarRedireccionesMarca() {
    const selectoresMarca = document.querySelectorAll('.brand-logo, .navbar-brand, .logo-cathartes, [data-brand="cathartes"]');
    
    selectoresMarca.forEach(elemento => {
        elemento.style.cursor = 'pointer';
        elemento.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = `${BASE_URL}/landing-page/`;
        });
    });
}

/**
 * Obtiene el rol del usuario desde la sesión/token y configura la redirección del botón "Inicio"
 */
function inicializarRedireccionesInicio() {
    const botonesInicio = document.querySelectorAll('.nav-link-inicio, #btn-dashboard, [data-nav="inicio"]');
    if (!botonesInicio.length) return;

    // Obtener sesión guardada (JWT decodificado o datos guardados en localStorage)
    const sesionUsuario = JSON.parse(localStorage.getItem('usuario_sigeru') || '{}');
    const rol = (sesionUsuario.rol || sesionUsuario.perfil || '').toLowerCase();

    let rutaDestino = `${BASE_URL}/frontend-usuarios/inicio.html`; // Ruta por defecto (Vecino/General)

    switch (rol) {
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
        // Renombra visualmente cualquier botón "Dashboard" residual a "Inicio"
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