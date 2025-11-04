// Auth Manager
const authManager = {
    empleado: null,

    async init() {
        await this.verificarSesion();
        this.setupEventListeners();
    },

    async verificarSesion() {
        try {
            const response = await fetch('../../backend/api/get_usuario_actual.php');
            const data = await response.json();

            if (data.success && data.autenticado) {
                this.empleado = data.empleado;
                this.actualizarUI();
                return true;
            } else {
                window.location.href = 'login.html';
                return false;
            }
        } catch (error) {
            console.error('Error al verificar sesión:', error);
            window.location.href = 'login.html';
            return false;
        }
    },

    setupEventListeners() {
        const logoutBtn = document.querySelector('#btn-logout');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                await this.logout();
            });
        } else {
            console.error('No se encontró el botón de logout en setupEventListeners');
        }
    },

    async login(usuario, password) {
        try {
            const response = await fetch('../../backend/api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ usuario, password })
            });

            const data = await response.json();

            if (data.success) {
                this.empleado = data.empleado;
                window.location.href = 'ConfigDash.html';
                return true;
            } else {
                throw new Error(data.error || 'Error al iniciar sesión');
            }
        } catch (error) {
            throw error;
        }
    },

    async logout() {
        try {
            const response = await fetch('../../backend/api/logout.php');
            const data = await response.json().catch(() => ({}));

            // Limpiar datos de sesión
            this.empleado = null;

            // Redirigir a login
            window.location.href = 'login.html';
        } catch (error) {
            console.error('Error al cerrar sesión:', error);
            alert('Error al cerrar sesión. Por favor, intente de nuevo.');
            window.location.href = 'login.html';
        }
    },

    actualizarUI() {
        if (!this.empleado) return;

        // Actualizar nombre de usuario
        const userNameElement = document.querySelector('.user-name');
        if (userNameElement) {
            userNameElement.textContent = this.empleado.nombre_completo;
        }

        // Actualizar avatar con iniciales
        const userAvatarElement = document.querySelector('.user-avatar');
        if (userAvatarElement) {
            const iniciales = this.empleado.nombre_completo
                .split(' ')
                .map(n => n[0])
                .join('')
                .toUpperCase();
            userAvatarElement.textContent = iniciales;
        }

        // Ocultar elementos según rol (con un pequeño retraso para asegurar que se ejecute después de otras inicializaciones)
        setTimeout(() => {
            this.actualizarVisibilidadSegunRol();
        }, 100);
    },

    actualizarVisibilidadSegunRol() {
        console.log('🔧 Actualizando visibilidad según rol:', this.empleado?.id_rol);
        
        const secciones = {
            'dashboard': [1, 2],    // Admin y Supervisor
            'productos': [1, 2],    // Admin y Supervisor
            'pedidos': [1, 2],      // Admin y Supervisor
            'comandas': [1, 2, 3],  // Todos los roles
            'estadisticas': [1, 2], // Admin y Supervisor
            'usuarios': [1],        // Solo Admin
            'proveedores': [1],     // Solo Admin
            'configuracion': [1]    // Solo Admin
        };

        // Ocultar/mostrar secciones según rol
        Object.entries(secciones).forEach(([seccion, rolesPermitidos]) => {
            const menuItem = document.querySelector(`[data-section="${seccion}"]`);
            const seccionElement = document.getElementById(`${seccion}-section`);

            const tienePermiso = rolesPermitidos.includes(this.empleado?.id_rol);

            // Actualizar visibilidad del menú
            if (menuItem) {
                menuItem.style.display = tienePermiso ? '' : 'none';
                console.log(`📋 Menú ${seccion}: ${tienePermiso ? 'visible' : 'oculto'}`);
            }

            // Actualizar visibilidad de la sección (inicialmente todas ocultas)
            if (seccionElement) {
                seccionElement.style.display = 'none';
                console.log(`📄 Sección ${seccion}: oculta`);
            }
        });

        // Limpiar todos los estados activos primero
        const menuItems = document.querySelectorAll('#sidebar-menu li');
        menuItems.forEach(item => item.classList.remove('active'));
        console.log('🧹 Estados activos limpiados');

        // Mostrar sección inicial según rol
        if (this.empleado?.id_rol === 3) {
            // Empleado: ir directamente a comandas
            const comandasSection = document.getElementById('comandas-section');
            const comandasMenuItem = document.querySelector('[data-section="comandas"]');

            if (comandasSection) {
                comandasSection.style.display = '';
                console.log('🛎️ Comandas: visible (empleado)');
            }
            if (comandasMenuItem) {
                comandasMenuItem.classList.add('active');
                console.log('🛎️ Comandas: marcado como activo (empleado)');
            }

            // Actualizar breadcrumb para empleados
            const breadcrumb = document.getElementById('breadcrumb');
            if (breadcrumb) {
                breadcrumb.innerHTML = 'Buffet Murialdino <span class="breadcrumb-sep">/</span> Comandas';
            }

            // Inicializar comandas automáticamente para empleados
            setTimeout(() => {
                if (typeof cargarPedidosComandas === 'function') {
                    cargarPedidosComandas();
                    console.log('🛎️ Comandas: datos cargados automáticamente');
                }
                
                // Iniciar actualización automática cada 15 segundos para empleados
                if (typeof window.intervalComandas !== 'undefined' && window.intervalComandas) {
                    clearInterval(window.intervalComandas);
                }
                window.intervalComandas = setInterval(() => {
                    if (typeof cargarPedidosComandas === 'function') {
                        cargarPedidosComandas();
                    }
                }, 15000);
                console.log('🛎️ Comandas: actualización automática iniciada');
            }, 200);
        } else {
            // Admin y Supervisor: mostrar dashboard por defecto
            const dashboardSection = document.getElementById('dashboard-section');
            const dashboardMenuItem = document.querySelector('[data-section="dashboard"]');

            if (dashboardSection) {
                dashboardSection.style.display = '';
                console.log('🏠 Dashboard: visible (admin/supervisor)');
            }
            if (dashboardMenuItem) {
                dashboardMenuItem.classList.add('active');
                console.log('🏠 Dashboard: marcado como activo (admin/supervisor)');
            }
        }
    },

    tienePermiso(rolesPermitidos) {
        return rolesPermitidos.includes(this.empleado?.id_rol);
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => authManager.init());

// Exportar para uso global
window.authManager = authManager;
