// Sistema completo de gestión de usuarios
class UsuariosManager {
    constructor() {
        this.usuariosOriginales = [];
        this.usuariosFiltrados = [];
        this.init();
    }

    init() {
        this.reemplazarToolbar();
        this.bindEvents();
        this.cargarEstadisticasCards();
        this.cargarUsuarios();
        
        // Actualizar estadísticas cada 60 segundos
        this.intervaloEstadisticas = setInterval(() => {
            this.cargarEstadisticasCards();
        }, 60000);
    }

    reemplazarToolbar() {
        const toolbar = document.querySelector('.usuarios-toolbar');
        if (!toolbar) return;

        // Limpiar toolbar existente
        toolbar.innerHTML = '';

        // Crear input de búsqueda
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'input-search';
        searchInput.id = 'buscar-usuarios';
        searchInput.placeholder = 'Buscar por usuario, nombre...';
        toolbar.appendChild(searchInput);

        // Crear select de roles
        const selectRol = document.createElement('select');
        selectRol.className = 'filter-select';
        selectRol.id = 'filter-rol';
        selectRol.innerHTML = `
            <option value="">Todos los roles</option>
            <option value="administrador">Administrador</option>
            <option value="supervisor">Supervisor</option>
            <option value="empleado">Empleado</option>
        `;
        toolbar.appendChild(selectRol);

    }

    bindEvents() {
        // Eventos de filtrado
        const searchInput = document.getElementById('buscar-usuarios');
        const filterRol = document.getElementById('filter-rol');

        if (searchInput) {
            searchInput.addEventListener('input', () => this.aplicarFiltros());
        }
        if (filterRol) {
            filterRol.addEventListener('change', () => this.aplicarFiltros());
        }

        // Evento del botón agregar
        const btnAgregar = document.getElementById('btn-agregar-usuario');
        if (btnAgregar) {
            btnAgregar.addEventListener('click', () => this.mostrarModalAgregar());
        }

        // Bind eventos de la tabla
        this.bindTableEvents();
    }

    async cargarUsuarios() {
        try {
            console.log('Cargando usuarios desde API...');
            const response = await fetch('../../backend/admin/api/api_usuarios.php?action=listar');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const text = await response.text();
            console.log('Respuesta cruda:', text);
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('Error parsing JSON:', parseError);
                console.error('Respuesta recibida:', text);
                throw new Error('Respuesta inválida del servidor');
            }
            
            if (data.success) {
                this.usuariosOriginales = data.data || [];
                this.usuariosFiltrados = [...this.usuariosOriginales];
                console.log('Usuarios cargados:', this.usuariosOriginales.length);
                this.renderizarUsuarios();
            } else {
                console.error('Error al cargar usuarios:', data.error);
                this.mostrarError('Error al cargar usuarios: ' + (data.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error en cargarUsuarios:', error);
            this.mostrarError('Error de conexión al cargar usuarios: ' + error.message);
        }
    }

    async cargarEstadisticasCards() {
        try {
            console.log('Cargando estadísticas desde API...');
            const response = await fetch('../../backend/admin/api/api_usuarios.php?action=estadisticas');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const text = await response.text();
            console.log('Respuesta estadísticas:', text);
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('Error parsing JSON estadísticas:', parseError);
                console.error('Respuesta recibida:', text);
                throw new Error('Respuesta inválida del servidor');
            }
            
            if (data.success) {
                const stats = data.data;
                
                // Actualizar tarjetas de estadísticas
                const totalUsuarios = document.getElementById('total-usuarios');
                const usuariosActivos = document.getElementById('usuarios-activos');
                const usuariosRegistrados = document.getElementById('usuarios-registrados');
                
                if (totalUsuarios) totalUsuarios.textContent = stats.total || 0;
                if (usuariosActivos) usuariosActivos.textContent = stats.activos || 0;
                if (usuariosRegistrados) usuariosRegistrados.textContent = `${stats.nuevos_mes || 0} este mes`;
                
                console.log('Estadísticas actualizadas:', stats);
            }
        } catch (error) {
            console.error('Error al cargar estadísticas:', error);
            // No mostrar error al usuario para estadísticas, solo loggearlo
        }
    }

    aplicarFiltros() {
        const busqueda = document.getElementById('buscar-usuarios')?.value.toLowerCase() || '';
        const rol = document.getElementById('filter-rol')?.value || '';

        this.usuariosFiltrados = this.usuariosOriginales.filter(usuario => {
            // Filtro de búsqueda
            const matchBusqueda = !busqueda || 
                usuario.usuario.toLowerCase().includes(busqueda) ||
                usuario.nombre_completo.toLowerCase().includes(busqueda);

            // Filtro de rol
            const matchRol = !rol || usuario.rol === rol;

            return matchBusqueda && matchRol;
        });

        this.renderizarUsuarios();
    }

    renderizarUsuarios() {
        const tbody = document.getElementById('usuarios-tbody');
        const listCount = document.querySelector('.usuarios-lista-count');
        
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!this.usuariosFiltrados || this.usuariosFiltrados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: #666;">No hay usuarios que coincidan con los filtros</td></tr>';
            if (listCount) listCount.textContent = '0 usuarios encontrados';
            return;
        }

        this.usuariosFiltrados.forEach(usuario => {
            const tr = document.createElement('tr');
            
            // Generar badge basado en el rol
            const rolBadge = this.generarRolBadge(usuario.rol);

            tr.innerHTML = `
                <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div>
                            <div class="usuario-nombre" style="font-weight: 600;">${usuario.usuario}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="usuario-nombre-completo" style="font-weight: 500;">
                        ${usuario.nombre_completo}
                    </div>
                </td>
                <td>${rolBadge}</td>
                <td>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn-icon btn-edit-usuario" data-id="${usuario.id_empleado}" title="Editar usuario">✏️</button>
                        <button class="btn-icon btn-delete-usuario" data-id="${usuario.id_empleado}" title="Eliminar usuario">🗑️</button>
                        <button class="btn-icon btn-view-usuario" data-id="${usuario.id_empleado}" title="Ver detalles">👁️</button>
                    </div>
                </td>
            `;

            tbody.appendChild(tr);
        });

        // Actualizar contador
        if (listCount) {
            listCount.textContent = `${this.usuariosFiltrados.length} usuario${this.usuariosFiltrados.length !== 1 ? 's' : ''} encontrado${this.usuariosFiltrados.length !== 1 ? 's' : ''}`;
        }

        // Rebind events para los nuevos elementos
        this.bindTableEvents();
    }

    generarRolBadge(rol) {
        const roles = {
            'administrador': { color: '#dc2626', background: '#fef2f2', icon: '👑', text: 'Administrador' },
            'supervisor': { color: '#ea580c', background: '#fff7ed', icon: '👨‍💼', text: 'Supervisor' },
            'empleado': { color: '#059669', background: '#f0fdf4', icon: '👤', text: 'Empleado' }
        };

        const rolInfo = roles[rol] || roles['empleado'];
        const { color, background, icon, text } = rolInfo;

        return `<span style="display:inline-flex;align-items:center;gap:6px;background:${background};color:${color};font-weight:600;padding:4px 12px;border-radius:16px;font-size:14px;">${icon} ${text}</span>`;
    }

    bindTableEvents() {
        // Eventos para botones de editar
        document.querySelectorAll('.btn-edit-usuario').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const userId = e.target.getAttribute('data-id');
                this.editarUsuario(userId);
            });
        });

        // Eventos para botones de eliminar
        document.querySelectorAll('.btn-delete-usuario').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const userId = e.target.getAttribute('data-id');
                this.eliminarUsuario(userId);
            });
        });

        // Eventos para botones de ver
        document.querySelectorAll('.btn-view-usuario').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const userId = e.target.getAttribute('data-id');
                this.verUsuario(userId);
            });
        });
    }

    mostrarModalAgregar() {
        console.log('Abrir modal agregar usuario');
        const modal = document.getElementById('modal-agregar-usuario');
        if (modal) {
            // Asegurar que el título sea correcto para agregar
            const titulo = modal.querySelector('h2');
            if (titulo) titulo.textContent = 'Agregar Usuario';
            
            // Limpiar formulario
            const form = document.getElementById('form-agregar-usuario');
            if (form) {
                form.reset();
                
                // Remover cualquier campo oculto de ID que pueda existir
                const hiddenId = form.querySelector('input[name="id_empleado"]');
                if (hiddenId) {
                    hiddenId.remove();
                }
                
                // Asegurar que todos los campos estén habilitados
                const campos = form.querySelectorAll('input, select, textarea');
                campos.forEach(campo => {
                    campo.disabled = false;
                });
                
                // Mostrar botón de guardar
                const btnGuardar = form.querySelector('button[type="submit"]');
                if (btnGuardar) {
                    btnGuardar.style.display = 'inline-block';
                    btnGuardar.textContent = 'Agregar';
                }
                
                // Restaurar texto del botón cancelar
                const btnCancelar = document.getElementById('close-modal-agregar');
                if (btnCancelar) btnCancelar.textContent = 'Cancelar';
            }
            
            // Mostrar modal con centrado
            modal.style.display = 'flex';
            
            // Configurar eventos del modal si no están ya configurados
            this.configurarEventosModal();
        } else {
            console.error('Modal agregar usuario no encontrado');
        }
    }

    configurarEventosModal() {
        const modal = document.getElementById('modal-agregar-usuario');
        const form = document.getElementById('form-agregar-usuario');
        const closeBtn = document.getElementById('close-modal-x');
        const cancelBtn = document.getElementById('close-modal-agregar');
        
        // Cerrar con X
        if (closeBtn) {
            closeBtn.onclick = () => {
                modal.style.display = 'none';
            };
        }
        
        // Cerrar con cancelar
        if (cancelBtn) {
            cancelBtn.onclick = () => {
                modal.style.display = 'none';
            };
        }
        
        // Manejar envío del formulario
        if (form) {
            form.onsubmit = async (e) => {
                e.preventDefault();
                await this.agregarUsuario();
            };
        }
    }

    async agregarUsuario() {
        const form = document.getElementById('form-agregar-usuario');
        const formData = new FormData(form);
        
        // Determinar si es edición o creación
        const isEdit = formData.get('id_empleado');
        
        // Preparar datos en formato JSON como espera la API
        const userData = {
            usuario: formData.get('usuario'),
            nombre_completo: formData.get('nombre_completo'),
        };

        // Para crear, se requiere contraseña
        if (!isEdit) {
            userData.password = formData.get('contraseña');
        }

        // Enviar id_rol como número (la API ahora espera id_rol numérico)
        userData.id_rol = parseInt(formData.get('id_rol')) || 2; // Empleado por defecto
        
        // Si es edición, agregar el ID
        if (isEdit) {
            userData.id = isEdit;
        }
        
        console.log('Enviando datos:', userData); // Para debug
        
        try {
            // Enviar la acción como parámetro GET y los datos como JSON
            const action = isEdit ? 'editar' : 'crear';
            const response = await fetch(`../../backend/admin/api/api_usuarios.php?action=${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Cerrar modal
                document.getElementById('modal-agregar-usuario').style.display = 'none';
                
                // Recargar usuarios
                await this.cargarUsuarios();
                
                // Mostrar mensaje de éxito
                const mensaje = isEdit ? 'Usuario actualizado exitosamente' : 'Usuario agregado exitosamente';
                alert(mensaje);
                
                // Limpiar formulario y restaurar estado
                form.reset();
                const hiddenId = form.querySelector('input[name="id_empleado"]');
                if (hiddenId) {
                    hiddenId.remove();
                }
                
                // Restaurar título
                const titulo = document.querySelector('#modal-agregar-usuario h2');
                if (titulo) titulo.textContent = 'Agregar Usuario';
                
            } else {
                alert('Error: ' + (data.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error al guardar usuario:', error);
            alert('Error de conexión: ' + error.message);
        }
    }

    editarUsuario(userId) {
        console.log('Editar usuario:', userId);
        
        // Buscar el usuario en los datos
        const usuario = this.usuariosOriginales.find(u => u.id_empleado == userId);
        if (!usuario) {
            alert('Usuario no encontrado');
            return;
        }
        
        // Llenar el formulario con los datos del usuario
        const modal = document.getElementById('modal-agregar-usuario');
        const form = document.getElementById('form-agregar-usuario');
        
        if (modal && form) {
            // Cambiar título del modal
            const titulo = modal.querySelector('h2');
            if (titulo) titulo.textContent = 'Editar Usuario';
            
            // Cambiar texto del botón a "Guardar"
            const btnGuardar = form.querySelector('button[type="submit"]');
            if (btnGuardar) {
                btnGuardar.textContent = 'Guardar';
            }
            
            // Llenar campos
            document.getElementById('usuario').value = usuario.usuario || '';
            document.getElementById('nombre_completo').value = usuario.nombre_completo || '';
            document.getElementById('contraseña').value = ''; // No mostrar contraseña
            document.getElementById('id_rol').value = this.convertirRolAId(usuario.rol);
            
            // Agregar campo oculto para el ID
            let hiddenId = form.querySelector('input[name="id_empleado"]');
            if (!hiddenId) {
                hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'id_empleado';
                form.appendChild(hiddenId);
            }
            hiddenId.value = userId;
            
            // Mostrar modal con centrado
            modal.style.display = 'flex';
            this.configurarEventosModal();
        }
    }

    convertirRolAId(rol) {
        const roles = {
            'administrador': '1',
            'supervisor': '2', 
            'empleado': '3'
        };
        return roles[rol] || '3';
    }

    async eliminarUsuario(userId) {
        if (confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
            try {
                const response = await fetch(`../../backend/admin/api/api_usuarios.php?action=eliminar&id=${encodeURIComponent(userId)}`, {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Recargar usuarios
                    await this.cargarUsuarios();
                    alert('Usuario eliminado exitosamente');
                } else {
                    alert('Error: ' + (data.error || 'Error desconocido'));
                }
            } catch (error) {
                console.error('Error al eliminar usuario:', error);
                alert('Error de conexión: ' + error.message);
            }
        }
    }

    verUsuario(userId) {
        console.log('Ver usuario:', userId);
        
        // Buscar el usuario en los datos
        const usuario = this.usuariosOriginales.find(u => u.id_empleado == userId);
        if (!usuario) {
            alert('Usuario no encontrado');
            return;
        }
        
        // Crear modal de vista (reutilizando el modal existente pero en modo solo lectura)
        const modal = document.getElementById('modal-agregar-usuario');
        const form = document.getElementById('form-agregar-usuario');
        
        if (modal && form) {
            // Cambiar título del modal
            const titulo = modal.querySelector('h2');
            if (titulo) titulo.textContent = 'Información del Usuario';
            
            // Llenar campos y deshabilitar
            const campos = form.querySelectorAll('input, select, textarea');
            campos.forEach(campo => {
                campo.disabled = true;
            });
            
            // Llenar con datos
            document.getElementById('usuario').value = usuario.usuario || '';
            document.getElementById('nombre_completo').value = usuario.nombre_completo || '';
            document.getElementById('contraseña').value = '••••••••'; // Mostrar contraseña oculta
            document.getElementById('id_rol').value = this.convertirRolAId(usuario.rol);
            
            // Ocultar botón de guardar y cambiar el de cancelar
            const btnGuardar = form.querySelector('button[type="submit"]');
            const btnCancelar = document.getElementById('close-modal-agregar');
            
            if (btnGuardar) btnGuardar.style.display = 'none';
            if (btnCancelar) btnCancelar.textContent = 'Cerrar';
            
            // Mostrar modal con centrado
            modal.style.display = 'flex';
            
            // Configurar evento para cerrar y restaurar estado
            this.configurarEventosModalVista();
        }
    }

    configurarEventosModalVista() {
        const modal = document.getElementById('modal-agregar-usuario');
        const form = document.getElementById('form-agregar-usuario');
        const closeBtn = document.getElementById('close-modal-x');
        const cancelBtn = document.getElementById('close-modal-agregar');
        
        const restaurarModal = () => {
            // Restaurar estado original del modal
            const titulo = modal.querySelector('h2');
            if (titulo) titulo.textContent = 'Agregar Usuario';
            
            // Rehabilitar campos
            const campos = form.querySelectorAll('input, select, textarea');
            campos.forEach(campo => {
                campo.disabled = false;
            });
            
            // Mostrar botón guardar
            const btnGuardar = form.querySelector('button[type="submit"]');
            if (btnGuardar) btnGuardar.style.display = 'inline-block';
            
            // Restaurar texto del botón cancelar
            if (cancelBtn) cancelBtn.textContent = 'Cancelar';
            
            // Limpiar formulario
            form.reset();
            
            // Cerrar modal
            modal.style.display = 'none';
        };
        
        // Cerrar con X
        if (closeBtn) {
            closeBtn.onclick = restaurarModal;
        }
        
        // Cerrar con cancelar
        if (cancelBtn) {
            cancelBtn.onclick = restaurarModal;
        }
    }

    mostrarError(mensaje) {
        console.error(mensaje);
        
        // Mostrar el error en la tabla
        const tbody = document.getElementById('usuarios-tbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: #dc2626; background: #fef2f2;">
                        <div style="margin-bottom: 8px;">❌ ${mensaje}</div>
                        <button onclick="window.usuariosManager?.cargarUsuarios()" style="background: #dc2626; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                            Reintentar
                        </button>
                    </td>
                </tr>
            `;
        }
        
        // También mostrar en las estadísticas
        const totalUsuarios = document.getElementById('total-usuarios');
        const usuariosActivos = document.getElementById('usuarios-activos');
        const usuariosRegistrados = document.getElementById('usuarios-registrados');
        
        if (totalUsuarios) totalUsuarios.textContent = '!';
        if (usuariosActivos) usuariosActivos.textContent = '!';
        if (usuariosRegistrados) usuariosRegistrados.textContent = 'Error';
    }

    destruir() {
        if (this.intervaloEstadisticas) {
            clearInterval(this.intervaloEstadisticas);
        }
        console.log('UsuariosManager destruido');
    }
}

// Inicializar cuando el documento esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Solo verificar que existe la sección, pero no crear instancia todavía
    if (document.getElementById('usuarios-section')) {
        console.log('Sección de usuarios detectada, esperando activación...');
    }
});

// Crear función para inicializar el manager cuando se necesite
function inicializarUsuariosManager() {
    if (!window.usuariosManager && document.getElementById('usuarios-section')) {
        console.log('Inicializando UsuariosManager...');
        window.usuariosManager = new UsuariosManager();
    }
    return window.usuariosManager;
}

// Limpiar cuando se cambie de sección
document.addEventListener('sectionChange', function(e) {
    if (e.detail.previousSection === 'usuarios' && window.usuariosManager) {
        window.usuariosManager.destruir();
        window.usuariosManager = null;
    } else if (e.detail.currentSection === 'usuarios') {
        inicializarUsuariosManager();
    }
});

// Asegurar que el manager esté disponible globalmente
window.usuariosManager = window.usuariosManager || null;

// Función helper para verificar y crear manager si no existe
window.getUsuariosManager = function() {
    if (!window.usuariosManager) {
        inicializarUsuariosManager();
    }
    return window.usuariosManager;
};
