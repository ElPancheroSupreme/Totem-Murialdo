// Configuración de prueba con datos mock
const CONFIG_TEST = {
    BASE_URL: '../../',
    API: {
        PRODUCTOS: 'backend/admin/api/api_productos.php',
        USUARIOS: 'backend/admin/api/api_usuarios.php',
        PROVEEDORES: 'backend/admin/api/api_proveedores.php'
    }
};

// Mock data para pruebas
const MOCK_DATA = {
    categorias: {
        success: true,
        data: [
            { id: 1, nombre: 'Bebidas', activo: 1 },
            { id: 2, nombre: 'Snacks', activo: 1 },
            { id: 3, nombre: 'Comidas', activo: 1 }
        ]
    },
    puntos_venta: {
        success: true,
        data: [
            { id: 1, nombre: 'Kiosco', activo: 1 },
            { id: 2, nombre: 'Buffet', activo: 1 }
        ]
    },
    productos: {
        success: true,
        data: [
            {
                id: 1,
                nombre: 'Coca Cola',
                precio: 500,
                categoria_id: 1,
                punto_venta_id: 1,
                stock: 50,
                activo: 1
            },
            {
                id: 2,
                nombre: 'Sandwich',
                precio: 800,
                categoria_id: 3,
                punto_venta_id: 2,
                stock: 20,
                activo: 1
            }
        ]
    }
};

// Override fetch para usar datos mock
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    console.log('Fetch interceptado:', url);
    
    // Determinar qué datos mock devolver basándose en la URL
    if (url.includes('action=categorias')) {
        return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(MOCK_DATA.categorias)
        });
    } else if (url.includes('action=puntos_venta')) {
        return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(MOCK_DATA.puntos_venta)
        });
    } else if (url.includes('action=listar')) {
        return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(MOCK_DATA.productos)
        });
    }
    
    // Para otras URLs, usar fetch original
    return originalFetch(url, options);
};

console.log('Mock de datos configurado para testing');
