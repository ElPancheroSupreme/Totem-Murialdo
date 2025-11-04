// === GESTIÓN DE PROVEEDORES ===
console.log('Archivo proveedores.js cargado correctamente');

const proveedoresManager = {
    proveedores: [],
    productos: [],
    plantillas: [],
    proveedorAEliminar: null,
    
    // === PLANTILLAS ===
    async guardarPlantilla(e) {
        e.preventDefault();
        const contenido = document.getElementById('plantilla-mensaje').value.trim();
        let nombre = 'Plantilla';
        if (contenido.length > 0) {
            // Primeras palabras como nombre
            nombre = contenido.substring(0, 30).replace(/\n/g, ' ') + (contenido.length > 30 ? '...' : '');
        }
        if (!contenido) {
            window.notificacionesManager?.mostrar('El mensaje de la plantilla no puede estar vacío', 'warning');
            return;
        }
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores_simple.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'crear_plantilla', nombre, contenido })
            });
            const result = await response.json();
            if (result.success) {
                window.notificacionesManager?.mostrar('Plantilla guardada correctamente', 'success');
                document.getElementById('modal-plantilla-proveedor').style.display = 'none';
                document.getElementById('plantilla-mensaje').value = '';
                await this.cargarPlantillas();
            } else {
                window.notificacionesManager?.mostrar(result.message || 'Error al guardar plantilla', 'error');
            }
        } catch (error) {
            window.notificacionesManager?.mostrar('Error de conexión', 'error');
        }
    },
    
    async init() {
        await this.cargarProveedores();
        await this.cargarEstadisticas();
        this.configurarEventListeners();
    },
    
    configurarEventListeners() {
    // Guardar plantilla
    document.getElementById('form-plantilla-proveedor')?.addEventListener('submit', (e) => this.guardarPlantilla(e));
        // Botones principales
        document.getElementById('btn-nuevo-proveedor')?.addEventListener('click', () => this.abrirModal());
        document.getElementById('btn-asignar-productos')?.addEventListener('click', () => this.abrirModalAsignar());
        document.getElementById('btn-enviar-pedido')?.addEventListener('click', () => this.abrirModalPedido());
        
        // Filtros
        document.getElementById('buscar-proveedor')?.addEventListener('input', () => this.filtrarProveedores());
        document.getElementById('filtro-estado-proveedor')?.addEventListener('change', () => this.filtrarProveedores());
        document.getElementById('btn-limpiar-filtros')?.addEventListener('click', () => this.limpiarFiltros());
        
        // Formulario principal
        document.getElementById('form-proveedor')?.addEventListener('submit', (e) => this.guardarProveedor(e));
        
        // Modal de asignación
        document.getElementById('select-proveedor-productos')?.addEventListener('change', (e) => this.cargarProductosParaAsignar(e.target.value));
        document.getElementById('btn-guardar-asignacion')?.addEventListener('click', () => this.guardarAsignacion());
        document.getElementById('buscar-productos-asignar')?.addEventListener('input', () => this.filtrarProductosAsignar());
        document.getElementById('select-all-productos-asignar')?.addEventListener('change', (e) => this.seleccionarTodosProductos(e.target.checked));
        
        // Modal de pedido
        document.getElementById('select-proveedor-pedido')?.addEventListener('change', (e) => this.cargarProductosParaPedido(e.target.value));
        document.getElementById('plantilla-mensaje')?.addEventListener('change', (e) => this.aplicarPlantilla(e.target.value));
        document.getElementById('btn-enviar-pedido-final')?.addEventListener('click', () => this.enviarPedido());
        document.getElementById('select-all-productos-pedido')?.addEventListener('change', (e) => this.seleccionarTodosProductosPedido(e.target.checked));
        
        // Formateo de CUIT
        document.getElementById('cuit')?.addEventListener('input', (e) => this.formatearCuit(e));
    },
    
    async cargarProveedores() {
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores.php?action=get_proveedores');
            const data = await response.json();
            
            if (data.success) {
                this.proveedores = data.proveedores;
                this.renderProveedores();
                this.cargarSelectoresProveedores();
            } else {
                console.error('Error al cargar proveedores:', data.message);
            }
        } catch (error) {
            console.error('Error de conexión:', error);
        }
    },
    
    async cargarEstadisticas() {
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores_simple.php?action=get_estadisticas');
            const data = await response.json();
            
            if (data.success) {
                const stats = data.estadisticas;
                document.getElementById('total-proveedores-activos').textContent = stats.total_activos;
                document.getElementById('productos-sin-proveedor').textContent = stats.productos_sin_proveedor;
                document.getElementById('pedidos-mes').textContent = stats.pedidos_mes;
            }
        } catch (error) {
            console.error('Error al cargar estadísticas:', error);
        }
    },
    
    renderProveedores() {
        const tbody = document.getElementById('proveedores-tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        if (this.proveedores.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #666;">No hay proveedores registrados</td></tr>';
            return;
        }
        
        this.proveedores.forEach(proveedor => {
            const tr = document.createElement('tr');
            
            const ultimoPedido = proveedor.ultimo_pedido 
                ? new Date(proveedor.ultimo_pedido).toLocaleDateString('es-AR')
                : 'Nunca';
                
            const estadoBadge = proveedor.estado == 1 
                ? '<span class="estado-green">Activo</span>'
                : '<span class="estado-red">Inactivo</span>';
            
            tr.innerHTML = `
                <td><strong>${proveedor.nombre_empresa}</strong></td>
                <td>${proveedor.persona_contacto || '-'}</td>
                <td>${proveedor.cuit || '-'}</td>
                <td>${proveedor.telefono || '-'}</td>
                <td style="text-align: center;">${proveedor.total_productos}</td>
                <td>${ultimoPedido}</td>
                <td>${estadoBadge}</td>
                <td style="text-align: center;">
                    <button class="btn-icon" onclick="proveedoresManager.editarProveedor(${proveedor.id_proveedor})" title="Editar">✏️</button>
                    <button class="btn-icon" onclick="proveedoresManager.verHistorial(${proveedor.id_proveedor})" title="Historial">📋</button>
                    <button class="btn-icon" onclick="proveedoresManager.eliminarProveedor(${proveedor.id_proveedor})" title="Eliminar">🗑️</button>
                </td>
            `;
            
            tbody.appendChild(tr);
        });
    },
    
    filtrarProveedores() {
        const busqueda = document.getElementById('buscar-proveedor').value.toLowerCase();
        const estado = document.getElementById('filtro-estado-proveedor').value;
        
        const proveedoresFiltrados = this.proveedores.filter(proveedor => {
            const coincideBusqueda = !busqueda || 
                proveedor.nombre_empresa.toLowerCase().includes(busqueda) ||
                (proveedor.persona_contacto && proveedor.persona_contacto.toLowerCase().includes(busqueda)) ||
                (proveedor.cuit && proveedor.cuit.includes(busqueda));
                
            const coincideEstado = estado === 'all' || proveedor.estado == estado;
            
            return coincideBusqueda && coincideEstado;
        });
        
        // Temporalmente reemplazar el array y renderizar
        const originalProveedores = this.proveedores;
        this.proveedores = proveedoresFiltrados;
        this.renderProveedores();
        this.proveedores = originalProveedores;
    },
    
    limpiarFiltros() {
        document.getElementById('buscar-proveedor').value = '';
        document.getElementById('filtro-estado-proveedor').value = 'all';
        this.renderProveedores();
    },
    
    abrirModal(id = null) {
        const modal = document.getElementById('modal-proveedor');
        const titulo = document.getElementById('modal-proveedor-titulo');
        const form = document.getElementById('form-proveedor');
        
        if (id) {
            titulo.textContent = 'Editar Proveedor';
            this.cargarDatosProveedor(id);
        } else {
            titulo.textContent = 'Nuevo Proveedor';
            form.reset();
            document.getElementById('proveedor-id').value = '';
        }
        
        modal.style.display = 'flex';
    },
    
    cerrarModal() {
        document.getElementById('modal-proveedor').style.display = 'none';
    },
    
    async cargarDatosProveedor(id) {
        try {
            const response = await fetch(`/Totem_Murialdo/backend/admin/api/api_proveedores_simple.php?action=get_proveedor&id=${id}`);
            const data = await response.json();
            
            if (data.success) {
                const proveedor = data.proveedor;
                document.getElementById('proveedor-id').value = proveedor.id_proveedor;
                document.getElementById('nombre_empresa').value = proveedor.nombre_empresa;
                document.getElementById('persona_contacto').value = proveedor.persona_contacto || '';
                document.getElementById('cuit').value = proveedor.cuit || '';
                document.getElementById('telefono').value = proveedor.telefono || '';
                document.getElementById('email').value = proveedor.email || '';
                document.getElementById('direccion').value = proveedor.direccion || '';
                document.getElementById('notas').value = proveedor.notas || '';
                document.getElementById('estado').value = proveedor.estado;
            }
        } catch (error) {
            console.error('Error al cargar proveedor:', error);
        }
    },
    
    async guardarProveedor(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Debug: mostrar datos que se van a enviar
        console.log('Datos del formulario:', data);
        
        // Validar CUIT si existe
        if (data.cuit && !this.validarCuit(data.cuit)) {
            window.notificacionesManager?.mostrar('CUIT inválido', 'error');
            return;
        }
        
        try {
            const action = data.id_proveedor ? 'actualizar_proveedor' : 'crear_proveedor';
            const payload = {...data, action};
            
            // Debug: mostrar payload final
            console.log('Payload a enviar:', payload);
            
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            });
            
            // Debug: verificar el response completo
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Obtener el texto de la respuesta primero para debugging
            const responseText = await response.text();
            console.log('Response text:', responseText);
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Error parsing JSON:', parseError);
                console.error('Response text was:', responseText);
                window.notificacionesManager?.mostrar('Error: Respuesta inválida del servidor', 'error');
                return;
            }
            
            console.log('Parsed result:', result);
            
            if (result.success) {
                const mensaje = result.message || 'Operación completada exitosamente';
                window.notificacionesManager?.mostrar(mensaje, 'success');
                this.cerrarModal();
                await this.cargarProveedores();
                await this.cargarEstadisticas();
            } else {
                const mensajeError = result.message || 'Error desconocido';
                console.error('Error del servidor:', result);
                window.notificacionesManager?.mostrar(mensajeError, 'error');
            }
        } catch (error) {
            console.error('Error completo:', error);
            window.notificacionesManager?.mostrar('Error de conexión: ' + error.message, 'error');
        }
    },
    
    async eliminarProveedor(id) {
        const proveedor = this.proveedores.find(p => p.id_proveedor == id);
        if (!proveedor) return;
        
        // Intentar eliminación inicial para verificar si tiene productos
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores_simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=eliminar_proveedor&id=${id}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Eliminación exitosa sin productos
                window.notificacionesManager?.mostrar(result.message, 'success');
                await this.cargarProveedores();
                await this.cargarEstadisticas();
            } else if (result.requires_confirmation) {
                // Mostrar modal de confirmación
                this.mostrarModalConfirmarEliminacion(id, proveedor, result);
            } else {
                // Error normal
                window.notificacionesManager?.mostrar(result.message, 'error');
            }
        } catch (error) {
            window.notificacionesManager?.mostrar('Error de conexión', 'error');
        }
    },

    mostrarModalConfirmarEliminacion(id, proveedor, resultado) {
        // Configurar el modal con la información
        document.getElementById('mensaje-productos-asignados').textContent = 
            `Este proveedor tiene ${resultado.count_productos} productos asignados.`;
        
        // Almacenar el ID para uso posterior
        this.proveedorAEliminar = { id, proveedor };
        
        // Mostrar el modal
        document.getElementById('modal-confirmar-eliminar-proveedor').style.display = 'flex';
    },

    cerrarModalConfirmar() {
        document.getElementById('modal-confirmar-eliminar-proveedor').style.display = 'none';
        this.proveedorAEliminar = null;
    },

    async confirmarEliminarProveedor() {
        if (!this.proveedorAEliminar) return;
        
        const { id } = this.proveedorAEliminar;
        
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=eliminar_proveedor&id=${id}&forzar=true`
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.notificacionesManager?.mostrar(result.message, 'success');
                this.cerrarModalConfirmar();
                await this.cargarProveedores();
                await this.cargarEstadisticas();
            } else {
                window.notificacionesManager?.mostrar(result.message, 'error');
            }
        } catch (error) {
            window.notificacionesManager?.mostrar('Error de conexión', 'error');
        }
    },
    
    editarProveedor(id) {
        this.abrirModal(id);
    },
    
    async verHistorial(id) {
        const proveedor = this.proveedores.find(p => p.id_proveedor == id);
        if (!proveedor) {
            window.notificacionesManager?.mostrar('Proveedor no encontrado', 'error');
            return;
        }
        
        const modal = document.getElementById('modal-historial-pedidos');
        const titulo = document.getElementById('titulo-historial-proveedor');
        const lista = document.getElementById('historial-pedidos-lista');
        
        titulo.textContent = `Historial de Pedidos - ${proveedor.nombre_empresa}`;
        lista.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">Cargando historial...</div>';
        
        modal.style.display = 'flex';
        
        try {
            const url = `/Totem_Murialdo/backend/admin/api/api_proveedores_simple.php?action=get_historial_pedidos&id_proveedor=${id}&limit=20`;
            console.log('Consultando historial:', url);
            
            const response = await fetch(url);
            const data = await response.json();
            
            console.log('Respuesta del historial:', data);
            
            if (data.success) {
                this.renderHistorialPedidos(data.pedidos);
            } else {
                console.error('Error en respuesta:', data.message);
                lista.innerHTML = `<div style="text-align: center; padding: 2rem; color: #666;">Error: ${data.message || 'No se pudo cargar el historial'}</div>`;
            }
        } catch (error) {
            console.error('Error al cargar historial:', error);
            lista.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">Error de conexión</div>';
        }
    },
    
    renderHistorialPedidos(pedidos) {
        const lista = document.getElementById('historial-pedidos-lista');
        
        console.log('Renderizando pedidos:', pedidos);
        
        if (!pedidos || pedidos.length === 0) {
            lista.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">No hay pedidos registrados para este proveedor</div>';
            return;
        }
        
        const historialHTML = pedidos.map(pedido => {
            const fecha = new Date(pedido.creado_en).toLocaleString('es-AR');
            const viaIcon = pedido.via_envio === 'whatsapp' ? '📱 WhatsApp' : '✉️ Email';
            
            return `
                <div class="historial-pedido-card">
                    <div class="historial-pedido-header">
                        <div>
                            <div class="historial-pedido-title">Pedido #${pedido.id_pedido_proveedor}</div>
                            <div class="historial-pedido-fecha">${fecha}</div>
                        </div>
                        <div class="historial-via-envio">
                            ${viaIcon}
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <div class="historial-seccion-label">Productos Solicitados:</div>
                        <div class="historial-productos">${pedido.productos_solicitados || 'No especificado'}</div>
                    </div>
                    
                    <div>
                        <div class="historial-seccion-label">Mensaje Enviado:</div>
                        <div class="historial-mensaje">${pedido.mensaje_enviado || 'No especificado'}</div>
                    </div>
                </div>
            `;
        }).join('');
        
        lista.innerHTML = historialHTML;
    },
    
    cerrarModalHistorial() {
        document.getElementById('modal-historial-pedidos').style.display = 'none';
    },
    
    // === ASIGNACIÓN DE PRODUCTOS ===
    
    async abrirModalAsignar() {
        const modal = document.getElementById('modal-asignar-productos');
        await this.cargarSelectoresProveedores();
        await this.cargarTodosLosProductos();
        modal.style.display = 'flex';
    },
    
    cerrarModalAsignar() {
        document.getElementById('modal-asignar-productos').style.display = 'none';
        document.getElementById('productos-asignacion').style.display = 'none';
        document.getElementById('select-proveedor-productos').value = '';
    },
    
    async cargarSelectoresProveedores() {
        const selectAsignar = document.getElementById('select-proveedor-productos');
        const selectPedido = document.getElementById('select-proveedor-pedido');
        
        const proveedoresActivos = this.proveedores.filter(p => p.estado == 1);
        
        [selectAsignar, selectPedido].forEach(select => {
            if (select) {
                select.innerHTML = '<option value="">Seleccione un proveedor...</option>';
                proveedoresActivos.forEach(proveedor => {
                    const option = document.createElement('option');
                    option.value = proveedor.id_proveedor;
                    option.textContent = proveedor.nombre_empresa;
                    select.appendChild(option);
                });
            }
        });
    },
    
    async cargarTodosLosProductos() {
        try {
            const response = await fetch('/Totem_Murialdo/backend/api/api_kiosco.php?action=get_productos');
            const data = await response.json();
            
            if (data.success) {
                this.productos = data.productos;
            }
        } catch (error) {
            console.error('Error al cargar productos:', error);
        }
    },
    
    async cargarProductosParaAsignar(idProveedor) {
        if (!idProveedor) {
            document.getElementById('productos-asignacion').style.display = 'none';
            return;
        }
        
        // Cargar productos ya asignados al proveedor
        try {
            const response = await fetch(`/Totem_Murialdo/backend/admin/api/api_proveedores_simple.php?action=get_productos_proveedor&id_proveedor=${idProveedor}`);
            const data = await response.json();
            
            const productosAsignados = data.success ? data.productos : [];
            this.renderProductosParaAsignar(productosAsignados);
            document.getElementById('productos-asignacion').style.display = 'block';
            document.getElementById('btn-guardar-asignacion').disabled = false;
            
        } catch (error) {
            console.error('Error al cargar productos del proveedor:', error);
        }
    },
    
    renderProductosParaAsignar(productosAsignados = []) {
        const tbody = document.getElementById('lista-productos-asignar');
        tbody.innerHTML = '';
        
        this.productos.forEach(producto => {
            const asignado = productosAsignados.find(pa => pa.id_producto == producto.id_producto);
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding: 8px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" class="producto-checkbox" value="${producto.id_producto}" ${asignado ? 'checked' : ''}>
                        <span style="margin-left: 8px;">${producto.nombre}</span>
                    </label>
                </td>
                <td style="padding: 8px;">${producto.categoria || 'Sin categoría'}</td>
                <td style="padding: 8px;">$${parseFloat(producto.precio_lista || 0).toLocaleString()}</td>
            `;
            tbody.appendChild(tr);
        });
    },
    
    filtrarProductosAsignar() {
        const busqueda = document.getElementById('buscar-productos-asignar').value.toLowerCase();
        const filas = document.querySelectorAll('#lista-productos-asignar tr');
        
        filas.forEach(fila => {
            const texto = fila.textContent.toLowerCase();
            fila.style.display = texto.includes(busqueda) ? '' : 'none';
        });
    },
    
    seleccionarTodosProductos(seleccionar) {
        const checkboxes = document.querySelectorAll('#lista-productos-asignar .producto-checkbox');
        checkboxes.forEach(cb => {
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = seleccionar;
            }
        });
    },
    
    async guardarAsignacion() {
        const idProveedor = document.getElementById('select-proveedor-productos').value;
        if (!idProveedor) return;
        
        const checkboxes = document.querySelectorAll('#lista-productos-asignar .producto-checkbox:checked');
        const productos = Array.from(checkboxes).map(cb => ({
            id_producto: cb.value
        }));
        
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'asignar_productos',
                    id_proveedor: idProveedor,
                    productos: productos
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.notificacionesManager?.mostrar(result.message, 'success');
                this.cerrarModalAsignar();
                await this.cargarProveedores();
            } else {
                window.notificacionesManager?.mostrar(result.message, 'error');
            }
        } catch (error) {
            window.notificacionesManager?.mostrar('Error de conexión', 'error');
            console.error('Error:', error);
        }
    },
    
    // === ENVÍO DE PEDIDOS ===
    
    async abrirModalPedido() {
        const modal = document.getElementById('modal-enviar-pedido');
        await this.cargarSelectoresProveedores();
        await this.cargarPlantillas();
        modal.style.display = 'flex';
    },
    
    cerrarModalPedido() {
        document.getElementById('modal-enviar-pedido').style.display = 'none';
        document.getElementById('productos-pedido').style.display = 'none';
        document.getElementById('select-proveedor-pedido').value = '';
        document.getElementById('mensaje-pedido').value = '';
    },
    
    async cargarPlantillas() {
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores_simple.php?action=get_plantillas');
            const data = await response.json();
            if (data.success) {
                // Eliminar duplicados por id_plantilla
                const unicas = [];
                const ids = new Set();
                for (const plantilla of data.plantillas) {
                    if (!ids.has(plantilla.id_plantilla)) {
                        unicas.push(plantilla);
                        ids.add(plantilla.id_plantilla);
                    }
                }
                this.plantillas = unicas;
                const select = document.getElementById('plantilla-mensaje');
                select.innerHTML = '<option value="">Seleccione una plantilla...</option>';
                this.plantillas.forEach(plantilla => {
                    const option = document.createElement('option');
                    option.value = plantilla.id_plantilla;
                    option.textContent = plantilla.nombre;
                    option.dataset.contenido = plantilla.contenido;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error al cargar plantillas:', error);
        }
    },
    
    async cargarProductosParaPedido(idProveedor) {
        if (!idProveedor) {
            document.getElementById('productos-pedido').style.display = 'none';
            return;
        }
        
        try {
            const response = await fetch(`/Totem_Murialdo/backend/admin/api/api_proveedores.php?action=get_productos_proveedor&id_proveedor=${idProveedor}`);
            const data = await response.json();
            if (data.success && data.productos.length > 0) {
                // Guardar productos actuales para referencia en el mensaje
                this.productosPedidoActual = data.productos;
                this.renderProductosParaPedido(data.productos);
                document.getElementById('productos-pedido').style.display = 'block';
                document.getElementById('btn-enviar-pedido-final').disabled = false;
            } else {
                window.notificacionesManager?.mostrar('Este proveedor no tiene productos asignados', 'warning');
            }
        } catch (error) {
            console.error('Error al cargar productos del proveedor:', error);
        }
    },
    
    renderProductosParaPedido(productos) {
        const tbody = document.getElementById('lista-productos-pedido');
        tbody.innerHTML = '';
        
        productos.forEach(producto => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding: 8px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" class="producto-pedido-checkbox" value="${producto.id_producto}" onchange="proveedoresManager.actualizarMensajePedido()">
                        <span style="margin-left: 8px;">${producto.nombre}</span>
                    </label>
                </td>
                <td style="padding: 8px;">${producto.categoria}</td>
                <td style="padding: 8px; text-align: center;">
                    <input type="number" class="cantidad-producto" data-producto="${producto.id_producto}" min="1" value="1" style="width: 60px; padding: 4px; border: 1px solid #ddd; border-radius: 4px; text-align: center;" onchange="proveedoresManager.actualizarMensajePedido()">
                </td>
            `;
            tbody.appendChild(tr);
        });
    },
    
    seleccionarTodosProductosPedido(seleccionar) {
        const checkboxes = document.querySelectorAll('.producto-pedido-checkbox');
        checkboxes.forEach(cb => cb.checked = seleccionar);
        this.actualizarMensajePedido();
    },
    
    aplicarPlantilla(idPlantilla) {
        if (!idPlantilla) return;
        
        const option = document.querySelector(`#plantilla-mensaje option[value="${idPlantilla}"]`);
        if (option) {
            document.getElementById('mensaje-pedido').value = option.dataset.contenido;
            this.actualizarMensajePedido();
        }
    },
    
    actualizarMensajePedido() {
        const checkboxes = document.querySelectorAll('.producto-pedido-checkbox:checked');
        // Usar productosPedidoActual para obtener los nombres correctos con cantidades
        const productos = Array.from(checkboxes).map(cb => {
            const id = cb.value;
            const prod = (this.productosPedidoActual || []).find(p => p.id_producto == id);
            const cantidadInput = document.querySelector(`.cantidad-producto[data-producto="${id}"]`);
            const cantidad = cantidadInput ? cantidadInput.value : 1;
            return prod ? `- ${prod.nombre} x${cantidad}` : `- Producto x${cantidad}`;
        });

        const mensaje = document.getElementById('mensaje-pedido').value;
        // Solo reemplazar [PRODUCTOS] si existe en el mensaje
        if (mensaje.includes('[PRODUCTOS]')) {
            const mensajeActualizado = mensaje.replace('[PRODUCTOS]', productos.join('\n'));
            document.getElementById('mensaje-pedido').value = mensajeActualizado;
        }
    },
    
    async enviarPedido() {
        const idProveedor = document.getElementById('select-proveedor-pedido').value;
        const viaEnvio = document.getElementById('via-envio').value;
        const mensaje = document.getElementById('mensaje-pedido').value;
        
        const checkboxes = document.querySelectorAll('.producto-pedido-checkbox:checked');
        const productos = Array.from(checkboxes).map(cb => {
            const id = cb.value;
            const cantidadInput = document.querySelector(`.cantidad-producto[data-producto="${id}"]`);
            const cantidad = cantidadInput ? cantidadInput.value : 1;
            return {
                id_producto: id,
                cantidad: parseInt(cantidad)
            };
        });
        
        if (!idProveedor || productos.length === 0 || !mensaje.trim()) {
            window.notificacionesManager?.mostrar('Complete todos los campos requeridos', 'warning');
            return;
        }

        // Log detallado para debugging
        console.log('=== ENVÍO DE PEDIDO - DEBUG ===');
        console.log('ID Proveedor:', idProveedor);
        console.log('Vía de envío:', viaEnvio);
        console.log('Mensaje (longitud):', mensaje.length);
        console.log('Mensaje completo:', mensaje);
        console.log('Productos seleccionados:', productos);
        console.log('Número de productos:', productos.length);
        
        const pedidoData = {
            action: 'enviar_pedido',
            id_proveedor: idProveedor,
            productos: productos,
            mensaje: mensaje,
            via_envio: viaEnvio
        };
        
        console.log('Datos completos a enviar:', pedidoData);
        console.log('JSON a enviar:', JSON.stringify(pedidoData));

        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(pedidoData)
            });
            
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            
            const result = await response.json();
            console.log('Response completa del servidor:', result);
            console.log('WhatsApp URL recibida:', result.whatsapp_url);
            console.log('Email URL recibida:', result.email_url);
            console.log('Vía de envío seleccionada:', viaEnvio);
            
            if (result.success) {
                window.notificacionesManager?.mostrar(result.message, 'success');
                
                // Abrir la aplicación correspondiente según la vía de envío
                if (viaEnvio === 'whatsapp' && result.whatsapp_url) {
                    console.log('Abriendo WhatsApp:', result.whatsapp_url);
                    window.open(result.whatsapp_url, '_blank');
                } else if (viaEnvio === 'email' && result.email_url) {
                    console.log('Abriendo Gmail:', result.email_url);
                    window.open(result.email_url, '_blank');
                } else {
                    // Fallback: abrir lo que esté disponible
                    if (result.whatsapp_url) {
                        console.log('Fallback - Abriendo WhatsApp:', result.whatsapp_url);
                        window.open(result.whatsapp_url, '_blank');
                    } else if (result.email_url) {
                        console.log('Fallback - Abriendo Email:', result.email_url);
                        window.open(result.email_url, '_blank');
                    }
                }
                
                this.cerrarModalPedido();
                await this.cargarEstadisticas();
            } else {
                console.log('ERROR del servidor:', result.message);
                window.notificacionesManager?.mostrar(result.message, 'error');
            }
        } catch (error) {
            console.log('ERROR de conexión completo:', error);
            console.log('ERROR stack:', error.stack);
            window.notificacionesManager?.mostrar('Error de conexión', 'error');
            console.error('Error:', error);
        }
    },
    
    // === UTILIDADES ===
    
    formatearCuit(e) {
        let valor = e.target.value.replace(/\D/g, '');
        if (valor.length >= 2) {
            valor = valor.substring(0, 2) + '-' + valor.substring(2);
        }
        if (valor.length >= 11) {
            valor = valor.substring(0, 11) + '-' + valor.substring(11, 12);
        }
        e.target.value = valor;
    },
    
    validarCuit(cuit) {
        const cuitLimpio = cuit.replace(/\D/g, '');
        if (cuitLimpio.length !== 11) return false;
        
        const verificadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        let suma = 0;
        
        for (let i = 0; i < 10; i++) {
            suma += parseInt(cuitLimpio[i]) * verificadores[i];
        }
        
        const resto = suma % 11;
        const digitoVerificador = resto < 2 ? resto : 11 - resto;
        
        return parseInt(cuitLimpio[10]) === digitoVerificador;
    }
};

// Inicializar cuando se navegue a la sección de proveedores
document.addEventListener('DOMContentLoaded', function() {
    // Hacer disponible globalmente
    window.proveedoresManager = proveedoresManager;
    
    // Observer para detectar cuando se muestra la sección de proveedores
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                const section = document.getElementById('proveedores-section');
                if (section && section.style.display !== 'none' && !section.dataset.initialized) {
                    section.dataset.initialized = 'true';
                    proveedoresManager.init();
                }
            }
        });
    });
    
    const section = document.getElementById('proveedores-section');
    if (section) {
        observer.observe(section, { attributes: true });
    }
});
