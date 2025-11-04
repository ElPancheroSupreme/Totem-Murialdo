// Configuración global del dashboard
const CONFIG = {
    // URL base del proyecto - usar rutas relativas desde la carpeta frontend/views
    BASE_URL: '../../',
    
    // URLs de la API
    API: {
        PRODUCTOS: 'backend/admin/api/api_productos.php',
        USUARIOS: 'backend/admin/api/api_usuarios.php',
        PROVEEDORES: 'backend/admin/api/api_proveedores.php'
    },
    
    // Configuración de paginación
    PAGINATION: {
        ITEMS_PER_PAGE: 50,
        DEFAULT_PAGE: 1
    },
    
    // Configuración de filtros
    FILTERS: {
        DEFAULT_ORDER: 'fecha'
    }
};

// Función helper para construir URLs completas
function buildApiUrl(endpoint, params = {}) {
    let url = CONFIG.BASE_URL + endpoint;
    
    const urlParams = new URLSearchParams(params);
    if (urlParams.toString()) {
        url += '?' + urlParams.toString();
    }
    
    return url;
}

console.log('Configuración cargada:', CONFIG);
