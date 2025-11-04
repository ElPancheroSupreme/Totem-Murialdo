// Gestor de categorías
class CategoriasManager {
    constructor() {
        this.categorias = [];
        this.currentCategoria = null;
        this.sortable = null;
        this.polling = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.startPolling();
    }

    bindEvents() {
        // Event listener para mostrar/ocultar categorías inactivas
        const showInactiveCheckbox = document.getElementById('mostrar-categorias-inactivas');
        if (showInactiveCheckbox) {
            showInactiveCheckbox.addEventListener('change', () => {
                this.cargarCategorias();
                this.cargarEstadisticas();
            });
        }

        // Event listener para filtro de punto de venta
        const filtroPuntoVenta = document.getElementById('filtro-punto-venta');
        if (filtroPuntoVenta) {
            filtroPuntoVenta.addEventListener('change', () => {
                this.cargarCategorias();
                this.cargarEstadisticas();
            });
        }

        // Event listener para el formulario de categorías
        const categoriaForm = document.getElementById('categoria-form');
        if (categoriaForm) {
            categoriaForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.guardarCategoria();
            });
        }

        // Event listener para subida de archivo SVG
        const iconoFileInput = document.getElementById('categoria-icono-file');
        if (iconoFileInput) {
            iconoFileInput.addEventListener('change', (e) => {
                this.handleIconoFileSelect(e);
            });
        }
    }

    // ========== MODAL PRINCIPAL ==========
    abrirModal() {
        const modal = document.getElementById('categorias-modal');
        if (modal) {
            modal.style.display = 'block';
            this.cargarEstadisticas();
            this.cargarCategorias();
        }
    }

    cerrarModal() {
        const modal = document.getElementById('categorias-modal');
        if (modal) {
            modal.style.display = 'none';
        }
        this.cerrarModalForm();
    }

    // ========== MODAL DE FORMULARIO ==========
    abrirModalCrear() {
        this.currentCategoria = null;
        this.resetForm();
        document.getElementById('categoria-form-title').textContent = 'Nueva Categoría';
        document.getElementById('categoria-form-modal').style.display = 'block';
    }

    abrirModalEditar(categoria) {
        this.currentCategoria = categoria;
        this.llenarForm(categoria);
        document.getElementById('categoria-form-title').textContent = 'Editar Categoría';
        document.getElementById('categoria-form-modal').style.display = 'block';
    }

    cerrarModalForm() {
        const modal = document.getElementById('categoria-form-modal');
        if (modal) {
            modal.style.display = 'none';
        }
        this.resetForm();
    }

    // ========== GESTIÓN DEL FORMULARIO ==========
    resetForm() {
        const form = document.getElementById('categoria-form');
        if (form) {
            form.reset();
            document.getElementById('categoria-id').value = '';
            document.getElementById('categoria-icono').value = 'Icono_Snacks.svg';
            document.getElementById('categoria-visible').checked = true;
            this.actualizarPreviewIcono('Icono_Snacks.svg');
            document.getElementById('icono-file-name').textContent = '';
        }
    }

    llenarForm(categoria) {
        document.getElementById('categoria-id').value = categoria.id_categoria;
        document.getElementById('categoria-nombre').value = categoria.nombre;
        document.getElementById('categoria-descripcion').value = categoria.descripcion || '';
        document.getElementById('categoria-icono').value = categoria.icono;
        document.getElementById('categoria-visible').checked = categoria.visible == 1;
        document.getElementById('categoria-punto-venta').value = categoria.id_punto_venta || '';
        this.actualizarPreviewIcono(categoria.icono);
        document.getElementById('icono-file-name').textContent = '';
    }

    actualizarPreviewIcono(nombreArchivo) {
        const previewImg = document.getElementById('icono-preview-img');
        if (previewImg && nombreArchivo) {
            previewImg.src = `../assets/images/Iconos/${nombreArchivo}`;
            previewImg.onerror = () => {
                previewImg.src = '../assets/images/Iconos/Icono_Snacks.svg';
            };
        }
    }

    async handleIconoFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Validar tipo de archivo
        if (!file.name.toLowerCase().endsWith('.svg')) {
            this.mostrarError('Solo se permiten archivos SVG');
            event.target.value = '';
            return;
        }

        // Validar tamaño (máximo 500KB)
        if (file.size > 500 * 1024) {
            this.mostrarError('El archivo es demasiado grande. Máximo 500KB');
            event.target.value = '';
            return;
        }

        try {
            // Mostrar preview del archivo
            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('icono-preview-img').src = e.target.result;
            };
            reader.readAsDataURL(file);

            // Subir archivo al servidor
            const formData = new FormData();
            formData.append('icono', file);

            const response = await fetch('/Totem_Murialdo/backend/admin/api/upload_icono_categoria.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Actualizar el campo oculto con el nombre del archivo
                document.getElementById('categoria-icono').value = data.icono;
                document.getElementById('icono-file-name').textContent = `✓ ${data.original_name}`;
                this.mostrarExito('Icono subido exitosamente');
            } else {
                throw new Error(data.error || 'Error al subir el icono');
            }
        } catch (error) {
            console.error('Error subiendo icono:', error);
            this.mostrarError('Error al subir el icono: ' + error.message);
            event.target.value = '';
        }
    }

    // ========== OPERACIONES CRUD ==========
    async cargarEstadisticas() {
        try {
            const filtroPuntoVenta = document.getElementById('filtro-punto-venta')?.value || '';
            const url = `/Totem_Murialdo/backend/admin/api/api_categorias.php?action=stats${filtroPuntoVenta ? '&punto_venta=' + filtroPuntoVenta : ''}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                document.getElementById('total-categorias').textContent = data.stats.total;
                document.getElementById('categorias-activas').textContent = data.stats.activas;
                document.getElementById('categorias-inactivas').textContent = data.stats.inactivas;
            }
        } catch (error) {
            console.error('Error cargando estadísticas:', error);
        }
    }

    async cargarCategorias() {
        try {
            const includeInactive = document.getElementById('mostrar-categorias-inactivas')?.checked || false;
            const filtroPuntoVenta = document.getElementById('filtro-punto-venta')?.value || '';
            
            let url = `/Totem_Murialdo/backend/admin/api/api_categorias.php?action=list&include_inactive=${includeInactive}`;
            if (filtroPuntoVenta) {
                url += `&punto_venta=${filtroPuntoVenta}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                this.categorias = data.categorias;
                this.renderCategorias();
                this.initSortable();
            } else {
                console.error('Error cargando categorías:', data.error);
                this.mostrarError('Error al cargar categorías: ' + data.error);
            }
        } catch (error) {
            console.error('Error cargando categorías:', error);
            this.mostrarError('Error de conexión al cargar categorías');
        }
    }

    async guardarCategoria() {
        const form = document.getElementById('categoria-form');
        const formData = new FormData(form);

        const data = {
            id_categoria: formData.get('id_categoria') || null,
            nombre: formData.get('nombre').trim(),
            descripcion: formData.get('descripcion').trim(),
            icono: formData.get('icono'),
            visible: formData.has('visible'),
            id_punto_venta: parseInt(formData.get('id_punto_venta')) || null
        };

        if (!data.nombre) {
            this.mostrarError('El nombre es requerido');
            return;
        }

        if (!data.id_punto_venta) {
            this.mostrarError('El punto de venta es requerido');
            return;
        }

        try {
            const method = this.currentCategoria ? 'PUT' : 'POST';
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_categorias.php', {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.mostrarExito(result.message || 'Categoría guardada exitosamente');
                this.cerrarModalForm();
                this.cargarEstadisticas();
                this.cargarCategorias();
                // También actualizar el filtro de productos si está visible
                if (typeof productosManager !== 'undefined' && productosManager.cargarCategorias) {
                    productosManager.cargarCategorias();
                }
            } else {
                this.mostrarError(result.error || 'Error al guardar la categoría');
            }
        } catch (error) {
            console.error('Error guardando categoría:', error);
            this.mostrarError('Error de conexión al guardar');
        }
    }

    async eliminarCategoria(categoria) {
        if (!confirm(`¿Estás seguro de que quieres eliminar la categoría "${categoria.nombre}"?\n\nEsta acción no se puede deshacer.`)) {
            return;
        }

        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_categorias.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id_categoria: categoria.id_categoria })
            });

            const result = await response.json();

            if (result.success) {
                this.mostrarExito('Categoría eliminada exitosamente');
                this.cargarEstadisticas();
                this.cargarCategorias();
                // También actualizar el filtro de productos si está visible
                if (typeof productosManager !== 'undefined' && productosManager.cargarCategorias) {
                    productosManager.cargarCategorias();
                }
            } else {
                this.mostrarError(result.error || 'Error al eliminar la categoría');
            }
        } catch (error) {
            console.error('Error eliminando categoría:', error);
            this.mostrarError('Error de conexión al eliminar');
        }
    }

    async toggleVisibilidad(categoria) {
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_categorias.php', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'toggle_visibility',
                    id_categoria: categoria.id_categoria
                })
            });

            const result = await response.json();

            if (result.success) {
                this.mostrarExito(`Visibilidad ${categoria.visible == 1 ? 'activada' : 'desactivada'} correctamente`);
                this.cargarEstadisticas();
                this.cargarCategorias();
            } else {
                this.mostrarError(result.error || 'Error al cambiar visibilidad');
            }
        } catch (error) {
            console.error('Error cambiando visibilidad:', error);
            this.mostrarError('Error de conexión');
        }
    }

    // ========== RENDERIZADO ==========
    renderCategorias() {
        const container = document.getElementById('categorias-lista-body');
        if (!container) return;

        if (this.categorias.length === 0) {
            container.innerHTML = `
                <div style="padding: 40px; text-align: center; color: #6b7280;">
                    <div style="font-size: 48px; margin-bottom: 16px;">📂</div>
                    <div style="font-size: 16px; margin-bottom: 8px;">No hay categorías</div>
                    <div style="font-size: 14px;">Crea tu primera categoría para organizar los productos</div>
                </div>
            `;
            return;
        }

        // Renderizar cada categoría como una fila con columnas
        container.innerHTML = this.categorias.map(categoria => this.renderCategoriaItem(categoria)).join('');
    }

    renderCategoriaItem(categoria) {
        const esAdmin = window.authManager?.empleado?.id_rol === 1;
        const claseInvisible = categoria.visible == 0 ? 'invisible' : '';
        const iconoSrc = categoria.icono ? `../assets/images/Iconos/${categoria.icono}` : '../assets/images/Iconos/Icono_Snacks.svg';
        const puntoVentaNombre = categoria.punto_venta_nombre || (categoria.id_punto_venta == 1 ? 'Buffet' : 'Kiosco');
        const puntoVentaColor = categoria.id_punto_venta == 1 ? '#10b981' : '#3b82f6';
        
        return `
            <div class="categoria-item ${claseInvisible}" data-id="${categoria.id_categoria}" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1.2fr; column-gap: 24px; align-items: center; padding: 12px 32px; border-bottom: 1px solid #f3f4f6; background: #fff;">
                <div class="col-nombre" style="font-weight:600; color:#222; display:flex; align-items:center;">
                  <span style="background-color: #f3f4f6; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; width:32px; height:32px; margin-right:8px; padding:4px;">
                    <img src="${iconoSrc}" alt="${categoria.nombre}" style="max-width: 100%; max-height: 100%; object-fit: contain;" onerror="this.src='../assets/images/Iconos/Icono_Snacks.svg'">
                  </span>
                  ${categoria.nombre}
                </div>
                <div class="col-punto-venta" style="text-align:center;">
                    <span style="background-color: ${puntoVentaColor}22; color: ${puntoVentaColor}; padding: 4px 12px; border-radius: 12px; font-size: 0.875rem; font-weight: 500;">
                        ${puntoVentaNombre}
                    </span>
                </div>
                <div class="col-productos" style="text-align:center; font-size:1.1rem;">${categoria.total_productos}</div>
                <div class="col-estado" style="text-align:center;">
                    <span class="estado-badge ${categoria.visible == 1 ? 'activo' : 'inactivo'}" style="font-size:1rem;">
                        ${categoria.visible == 1 ? '●' : '○'} ${categoria.visible == 1 ? 'Activo' : 'Inactivo'}
                    </span>
                </div>
                <div class="col-acciones" style="display:flex; justify-content:center; gap:4px;">
                    <button class="btn-icon btn-editar" onclick="categoriasManager.abrirModalEditar(${JSON.stringify(categoria).replace(/"/g, '&quot;')})" title="Editar">✏️</button>
                    <button class="btn-icon btn-toggle" onclick="categoriasManager.toggleVisibilidad(${JSON.stringify(categoria).replace(/"/g, '&quot;')})" title="${categoria.visible == 1 ? 'Ocultar' : 'Mostrar'}">${categoria.visible == 1 ? '👁️' : '👁️‍🗨️'}</button>
                    ${esAdmin ? `<button class="btn-icon btn-eliminar" onclick="categoriasManager.eliminarCategoria(${JSON.stringify(categoria).replace(/"/g, '&quot;')})" title="Eliminar">🗑️</button>` : ''}
                </div>
            </div>
        `;
    }

    // ========== DRAG & DROP ==========
    initSortable() {
        const container = document.getElementById('categorias-lista-body');
        if (!container || this.categorias.length === 0) return;

        // Destruir sortable anterior si existe
        if (this.sortable) {
            this.sortable.destroy();
        }

        // Crear nuevo sortable usando HTML5 Drag & Drop API nativo
        this.setupDragAndDrop(container);
    }

    setupDragAndDrop(container) {
        const items = container.querySelectorAll('.categoria-item');

        items.forEach(item => {
            item.draggable = true;

            item.addEventListener('dragstart', (e) => {
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                // Solo usar text/plain para identificar el elemento, no outerHTML
                e.dataTransfer.setData('text/plain', item.dataset.id);
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                const dragging = container.querySelector('.dragging');
                const afterElement = this.getDragAfterElement(container, e.clientY);

                if (dragging && dragging !== item) {
                    if (afterElement == null) {
                        container.appendChild(dragging);
                    } else {
                        container.insertBefore(dragging, afterElement);
                    }
                }
            });

            item.addEventListener('drop', (e) => {
                e.preventDefault();
                this.actualizarOrden();
            });
        });
    }

    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.categoria-item:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    async actualizarOrden() {
        const items = document.querySelectorAll('.categoria-item');
        const categorias = Array.from(items).map((item, index) => ({
            id_categoria: parseInt(item.dataset.id),
            orden: index + 1
        }));

        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_categorias.php', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reorder',
                    categorias: categorias
                })
            });

            const result = await response.json();

            if (result.success) {
                this.mostrarExito('Orden actualizado exitosamente');
                // Recargar para obtener el orden actualizado
                this.cargarCategorias();
            } else {
                this.mostrarError(result.error || 'Error al actualizar el orden');
                // Recargar para restaurar el orden original
                this.cargarCategorias();
            }
        } catch (error) {
            console.error('Error actualizando orden:', error);
            this.mostrarError('Error de conexión al actualizar orden');
            this.cargarCategorias();
        }
    }

    // ========== POLLING PARA SINCRONIZACIÓN ==========
    startPolling() {
        // Actualizar cada 30 segundos si el modal está abierto
        this.polling = setInterval(() => {
            const modal = document.getElementById('categorias-modal');
            if (modal && modal.style.display === 'block') {
                this.cargarEstadisticas();
                this.cargarCategorias();
            }
        }, 30000);
    }

    stopPolling() {
        if (this.polling) {
            clearInterval(this.polling);
            this.polling = null;
        }
    }

    // ========== UTILIDADES ==========
    mostrarExito(mensaje) {
        if (window.notificacionesManager) {
            window.notificacionesManager.exito(mensaje);
        } else {
            this.mostrarNotificacionFallback(mensaje, 'success');
        }
    }

    mostrarError(mensaje) {
        if (window.notificacionesManager) {
            window.notificacionesManager.error(mensaje);
        } else {
            this.mostrarNotificacionFallback(mensaje, 'error');
        }
    }

    mostrarNotificacion(mensaje, tipo) {
        if (window.notificacionesManager) {
            window.notificacionesManager.mostrar(mensaje, tipo);
        } else {
            this.mostrarNotificacionFallback(mensaje, tipo);
        }
    }

    // Método de fallback para compatibilidad
    mostrarNotificacionFallback(mensaje, tipo) {
        // Crear contenedor de alertas si no existe
        let container = document.getElementById('alertas-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'alertas-container';
            container.className = 'alertas-container';
            document.body.appendChild(container);
        }

        // Crear alerta
        const alerta = document.createElement('div');
        alerta.className = `alerta ${tipo === 'success' ? 'success' : 'error'}`;
        let icon = '';
        if (tipo === 'success') icon = '✅';
        else if (tipo === 'error') icon = '❌';
        alerta.innerHTML = `
            <div class="alerta-content">
                <div class="alerta-icon">${icon}</div>
                <div class="alerta-message">${mensaje}</div>
                <button class="alerta-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        // Agregar al contenedor
        container.appendChild(alerta);

        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (alerta.parentElement) {
                alerta.remove();
            }
        }, 5000);
    }

    // ========== CLEANUP ==========
    destroy() {
        this.stopPolling();
        if (this.sortable) {
            this.sortable.destroy();
        }
    }
}

// Inicializar el gestor de categorías cuando se carga la página
document.addEventListener('DOMContentLoaded', () => {
    window.categoriasManager = new CategoriasManager();
});

// Cleanup al cambiar de página
window.addEventListener('beforeunload', () => {
    if (window.categoriasManager) {
        window.categoriasManager.destroy();
    }
});
