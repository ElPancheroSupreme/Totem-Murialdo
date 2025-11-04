// Gestión de Productos - Buffet Murialdino
class ProductosManager {
    constructor() {
        this.productos = [];
        this.categorias = [];
        this.puntosVenta = [];
        this.productoActual = null;
        this.urlAnterior = null;
        this.filtros = {
            busqueda: '',
            categoria: '',
            puntoVenta: '',
            estado: '',
            favoritos: '',
            orden: 'fecha'
        };
        this.paginacion = {
            paginaActual: 1,
            itemsPorPagina: 50,
            totalPaginas: 1,
            totalItems: 0
        };
        this.vistaActual = 'lista';
        this.datosImport = null;
        this.init();
    }

    async init() {
        await this.cargarCategorias();
        await this.cargarPuntosVenta();
        await this.cargarProductos();
        this.setupEventListeners();
        this.setupFiltros();
    }

    async cargarCategorias() {
        try {
            const response = await fetch(buildApiUrl(CONFIG.API.PRODUCTOS, { action: 'categorias' }));
            const data = await response.json();
            if (data.success) {
                this.categorias = data.data;
                this.actualizarSelectCategorias();
            }
        } catch (error) {
            console.error('Error cargando categorías:', error);
            this.mostrarAlerta('Error al cargar categorías', 'error');
        }
    }

    async cargarPuntosVenta() {
        try {
            const response = await fetch(buildApiUrl(CONFIG.API.PRODUCTOS, { action: 'puntos_venta' }));
            const data = await response.json();
            if (data.success) {
                this.puntosVenta = data.data;
                this.actualizarSelectPuntosVenta();
            }
        } catch (error) {
            console.error('Error cargando puntos de venta:', error);
            this.mostrarAlerta('Error al cargar puntos de venta', 'error');
        }
    }

    async cargarProductos() {
        try {
            const loader = document.getElementById('loader');
            loader.style.display = 'flex';
            loader.style.position = 'absolute';
            loader.style.zIndex = '999';
            loader.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
            const mostrarEliminados = document.getElementById('mostrar-eliminados')?.checked || false;
            const params = { action: 'listar' };
            if (mostrarEliminados) {
                params.mostrar_todos = '1';
            }
            const url = buildApiUrl(CONFIG.API.PRODUCTOS, params);
            const response = await fetch(url);
            const data = await response.json();
            if (data.success) {
                this.productos = data.data;
                this.renderizarProductos();
            }
        } catch (error) {
            console.error('Error cargando productos:', error);
            this.mostrarAlerta('Error al cargar productos', 'error');
        } finally {
            const loader = document.getElementById('loader');
            loader.style.display = 'none';
        }
    }

    async obtenerProducto(id) {
        try {
            const response = await fetch(`../../backend/admin/api/api_productos.php?action=obtener&id=${id}`);
            const data = await response.json();
            if (data.success) {
                return data.data;
            }
        } catch (error) {
            console.error('Error obteniendo producto:', error);
            this.mostrarAlerta('Error al obtener producto', 'error');
        }
        return null;
    }

    async crearProducto(formData) {
        try {
            this.mostrarLoader();
            const response = await fetch('../../backend/admin/api/api_productos.php?action=crear', {
                method: 'POST',
                body: formData
            });
            
            // Obtener el texto de la respuesta primero
            const responseText = await response.text();
            
            // Si hay error 500, mostrar información útil pero intentar continuar
            if (!response.ok) {
                console.error('Error del servidor:', response.status, response.statusText);
                console.error('Respuesta completa:', responseText);
                
                // Si el producto se está creando a pesar del error, mostrar mensaje mixto
                if (response.status === 500) {
                    this.mostrarAlerta('Producto creado con advertencias. Revisar logs del servidor.', 'warning');
                    await this.cargarProductos();
                    this.cerrarModal();
                    return true;
                }
                throw new Error(`Error del servidor (${response.status}): ${response.statusText}`);
            }
            
            // Intentar parsear como JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Error parseando JSON. Respuesta del servidor:', responseText);
                // Si contiene HTML, probablemente el producto se creó pero hubo un error después
                if (responseText.includes('<!DOCTYPE') || responseText.includes('<html>')) {
                    this.mostrarAlerta('Producto creado pero con errores en el servidor. Revisar logs.', 'warning');
                    await this.cargarProductos();
                    this.cerrarModal();
                    return true;
                }
                throw new Error('Respuesta inválida del servidor');
            }
            
            if (data.success) {
                this.mostrarAlerta('Producto creado exitosamente', 'success');
                await this.cargarProductos();
                this.cerrarModal();
                return true;
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error creando producto:', error);
            this.mostrarAlerta('Error al crear producto: ' + error.message, 'error');
            return false;
        } finally {
            this.ocultarLoader();
        }
    }

    async actualizarProducto(id, formData) {
        try {
            this.mostrarLoader();
            const response = await fetch(`../../backend/admin/api/api_productos.php?action=actualizar&id=${id}`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                this.mostrarAlerta('Producto actualizado exitosamente', 'success');
                await this.cargarProductos();
                this.cerrarModal();
                return true;
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error actualizando producto:', error);
            this.mostrarAlerta('Error al actualizar producto: ' + error.message, 'error');
            return false;
        } finally {
            this.ocultarLoader();
        }
    }

    async eliminarProducto(id, omitirConfirm = false, omitirAlerta = false) {
        if (!omitirConfirm) {
            if (!confirm('¿Está seguro de que desea eliminar este producto?')) {
                return false;
            }
        }
        try {
            this.mostrarLoader();
            const response = await fetch(`../../backend/admin/api/api_productos.php?action=eliminar&id=${id}`, {
                method: 'DELETE'
            });
            const data = await response.json();
            if (data.success) {
                // Detectar tipo de eliminación
                let tipo = 'eliminado';
                if (data.message && data.message.includes('desactivado')) tipo = 'desactivado';
                if (!omitirAlerta) {
                    const mensaje = data.message || 'Producto eliminado exitosamente';
                    this.mostrarAlerta(mensaje, 'success');
                    await this.cargarProductos();
                }
                return tipo;
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error eliminando producto:', error);
            if (!omitirAlerta) this.mostrarAlerta('Error al eliminar producto: ' + error.message, 'error');
            return false;
        } finally {
            this.ocultarLoader();
        }
    }

    async restaurarProducto(id) {
        if (!confirm('¿Está seguro de que desea restaurar este producto?')) {
            return false;
        }

        try {
            this.mostrarLoader();
            const response = await fetch(`../../backend/admin/api/api_productos.php?action=restaurar&id=${id}`, {
                method: 'POST'
            });
            const data = await response.json();
            if (data.success) {
                const mensaje = data.message || 'Producto restaurado exitosamente';
                this.mostrarAlerta(mensaje, 'success');
                await this.cargarProductos();
                return true;
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error restaurando producto:', error);
            this.mostrarAlerta('Error al restaurar producto: ' + error.message, 'error');
            return false;
        } finally {
            this.ocultarLoader();
        }
    }

    async subirImagen(file, puntoVenta = null, categoria = null, nombreProducto = null) {
        const formData = new FormData();
        formData.append('imagen', file);
        if (puntoVenta) {
            formData.append('punto_venta', puntoVenta);
        }
        if (categoria) {
            formData.append('categoria', categoria);
        }
        if (nombreProducto) {
            formData.append('nombre_producto', nombreProducto);
        }

        try {
            const response = await fetch('../../backend/admin/api/upload_image.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                return data.url;
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error subiendo imagen:', error);
            this.mostrarAlerta('Error al subir imagen: ' + error.message, 'error');
            return null;
        }
    }

    renderizarProductos(productos = null) {
        this.aplicarFiltros();
    }

    renderizarProductosLista(productos) {
        const tbody = document.getElementById('productos-tbody');
        if (!tbody) return;

        let html = '';
        productos.forEach(producto => {
            const estadoClass = producto.estado ? 'estado-green' : 'estado-red';
            const estadoTexto = producto.estado ? 'Activo' : 'Inactivo';
            const imgSrc = producto.url_imagen || '';
            const imagenHtml = imgSrc
                ? `<img src="${imgSrc}" alt="${producto.nombre}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; margin-right: 12px; ${!producto.estado ? 'opacity: 0.5;' : ''}">`
                : '<div style="width: 40px; height: 40px; background: #f3f4f6; border-radius: 6px; margin-right: 12px; display: flex; align-items: center; justify-content: center; color: #9ca3af;">📷</div>';

            // Clase adicional para productos eliminados/desactivados
            const rowClass = !producto.estado ? 'producto-desactivado' : '';
            const nombreTexto = producto.nombre.includes('(ELIMINADO)') ?
                producto.nombre : producto.nombre;

            // Lógica de botones
            let botones = `<button class="btn-icon" onclick="productosManager.editarProducto(${producto.id_producto || producto.id})" title="Editar">✏️</button>`;
            if (!producto.estado && producto.nombre.includes('(ELIMINADO)')) {
                // Solo restaurar
                botones += `<button class="btn-icon" onclick="productosManager.restaurarProducto(${producto.id_producto || producto.id})" title="Restaurar" style="color: green;">🔄</button>`;
            } else {
                // Activar/desactivar y eliminar
                botones += `<button class="btn-icon" onclick="productosManager.cambiarEstadoProducto(${producto.id_producto || producto.id})" title="Estado">${producto.estado ? '🔴' : '🟢'}</button>`;
                botones += `<button class="btn-icon" onclick="productosManager.eliminarProducto(${producto.id_producto || producto.id})" title="Eliminar">🗑️</button>`;
            }

            html += `
                <tr class="${rowClass}">
                    <td style="text-align:center;vertical-align:middle;">
                        <input type="checkbox" class="producto-checkbox" data-producto-id="${producto.id_producto || producto.id}">
                    </td>
                    <td>
                        <div class="prod-nombre" style="display: flex; align-items: center;">
                            ${imagenHtml}
                            <div>
                                <span class="prod-nombre-main ${!producto.estado ? 'texto-desactivado' : ''}" ondblclick="productosManager.iniciarEdicionInline(this, 'nombre', ${producto.id_producto || producto.id}, '${producto.nombre}')">${nombreTexto}</span>
                                <span class="prod-nombre-desc">${producto.punto_venta_nombre || 'Sin punto de venta'}</span>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-blue">${producto.categoria_nombre || 'Sin categoría'}</span></td>
                    <td ondblclick="productosManager.iniciarEdicionInline(this, 'precio_venta', ${producto.id_producto || producto.id}, '${producto.precio_venta}')">$${parseFloat(producto.precio_venta).toFixed(2)}</td>
                    <td><span class="${estadoClass}">${estadoTexto}</span></td>
                    <td>
                        ${botones}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // Lógica para seleccionar todos y manejar selección múltiple
        const selectAll = document.getElementById('select-all-productos');
        const checkboxes = tbody.querySelectorAll('.producto-checkbox');
        if (selectAll) {
            selectAll.checked = false;
            selectAll.onclick = function() {
                checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
            };
        }
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                if (!this.checked && selectAll) selectAll.checked = false;
                else if ([...checkboxes].every(c => c.checked) && selectAll) selectAll.checked = true;
            });
        });
    }

    renderizarProductosGrilla(productos) {
        const container = document.getElementById('productos-grid');
        if (!container) return;

        let html = '';
        productos.forEach(producto => {
            const estadoClass = producto.estado ? 'estado-green' : 'estado-red';
            const estadoTexto = producto.estado ? 'Activo' : 'Inactivo';
            const imagenUrl = producto.url_imagen || 'Images/placeholder-producto.png';

            // Clase adicional para productos desactivados
            const cardClass = !producto.estado ? 'producto-card producto-card-desactivado' : 'producto-card';
            const nombreTexto = producto.nombre.includes('(ELIMINADO)') ?
                producto.nombre : producto.nombre;

            // Lógica de botones
            let botones = `<button class="btn-icon" onclick="productosManager.editarProducto(${producto.id_producto || producto.id})" title="Editar">✏️</button>`;
            if (!producto.estado && producto.nombre.includes('(ELIMINADO)')) {
                // Solo restaurar
                botones += `<button class="btn-icon" onclick="productosManager.restaurarProducto(${producto.id_producto || producto.id})" title="Restaurar" style="color: green;">🔄</button>`;
            } else {
                // Activar/desactivar y eliminar
                botones += `<button class="btn-icon" onclick="productosManager.cambiarEstadoProducto(${producto.id_producto || producto.id})" title="${producto.estado ? 'Desactivar' : 'Activar'}">${producto.estado ? '🔴' : '🟢'}</button>`;
                botones += `<button class="btn-icon" onclick="productosManager.eliminarProducto(${producto.id_producto || producto.id})" title="Eliminar">🗑️</button>`;
            }

            html += `
                <div class="${cardClass}">
                    <div class="producto-imagen">
                        <img src="${imagenUrl}" alt="${producto.nombre}" onerror="this.src='Images/placeholder-producto.png'" style="${!producto.estado ? 'opacity: 0.5;' : ''}">
                    </div>
                    <div class="producto-info">
                        <h4 class="producto-nombre ${!producto.estado ? 'texto-desactivado' : ''}" ondblclick="productosManager.iniciarEdicionInline(this, 'nombre', ${producto.id_producto || producto.id}, '${producto.nombre}')">${nombreTexto}</h4>
                        <p class="producto-categoria">${producto.categoria_nombre || 'Sin categoría'}</p>
                        <div class="producto-precio" ondblclick="productosManager.iniciarEdicionInline(this, 'precio_venta', ${producto.id_producto || producto.id}, '${producto.precio_venta}')">$${parseFloat(producto.precio_venta).toFixed(2)}</div>
                        <div class="producto-estado">
                            <span class="${estadoClass}">${estadoTexto}</span>
                        </div>
                    </div>
                    <div class="producto-acciones">
                        ${botones}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    actualizarSelectCategorias(puntoVentaId = null) {
        const select = document.getElementById('categoria-select');
        const filter = document.getElementById('categoria-filter');

        if (select) {
            select.innerHTML = '<option value="">Seleccionar categoría</option>';
            
            // Solo mostrar categorías si hay un punto de venta seleccionado
            if (puntoVentaId !== null && puntoVentaId !== '') {
                let categoriasFiltradas;
                
                if (puntoVentaId == 3) {
                    // Si es "Ambos", solo mostrar categorías "Ambos"
                    categoriasFiltradas = this.categorias.filter(cat => 
                        cat.id_punto_venta == 3
                    );
                } else {
                    // Si es Buffet (1) o Kiosco (2), mostrar categorías específicas + categorías "Ambos"
                    categoriasFiltradas = this.categorias.filter(cat => 
                        cat.id_punto_venta == puntoVentaId || cat.id_punto_venta == 3
                    );
                }
                
                categoriasFiltradas.forEach(categoria => {
                    const option = document.createElement('option');
                    option.value = categoria.id_categoria;
                    option.textContent = categoria.nombre;
                    select.appendChild(option);
                });
                
                // Si no hay categorías para el punto de venta seleccionado, mostrar mensaje
                if (categoriasFiltradas.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No hay categorías para este punto de venta';
                    option.disabled = true;
                    select.appendChild(option);
                }
            }
            // Si no hay punto de venta seleccionado, no mostrar categorías (solo el placeholder)
        }

        if (filter) {
            filter.innerHTML = '<option value="">Todas las categorías</option>';
            this.categorias.forEach(categoria => {
                const option = document.createElement('option');
                option.value = categoria.id_categoria;
                option.textContent = categoria.nombre;
                filter.appendChild(option);
            });
        }
    }

    actualizarSelectPuntosVenta() {
        const select = document.getElementById('punto-venta-select');
        const filter = document.getElementById('punto-venta-filter');

        if (select) {
            select.innerHTML = '<option value="">Seleccionar punto de venta</option>';
            this.puntosVenta.forEach(punto => {
                const option = document.createElement('option');
                option.value = punto.id_punto_venta;
                option.textContent = punto.nombre;
                select.appendChild(option);
            });
        }

        if (filter) {
            filter.innerHTML = '<option value="">Todos los puntos de venta</option>';
            this.puntosVenta.forEach(punto => {
                const option = document.createElement('option');
                option.value = punto.id_punto_venta;
                option.textContent = punto.nombre;
                filter.appendChild(option);
            });
        }
    }

    async editarProducto(id) {
        this.productoActual = await this.obtenerProducto(id);
        if (this.productoActual) {
            this.abrirModal('editar');
            this.cargarDatosEnFormulario();
        }
    }

    nuevoProducto() {
        this.limpiarFormulario();
        this.productoActual = null;
        this.abrirModal('crear');
    }

    cargarDatosEnFormulario() {
        if (!this.productoActual) return;

        document.getElementById('nombre-input').value = this.productoActual.nombre;
        document.getElementById('precio-venta-input').value = this.productoActual.precio_venta;
        document.getElementById('precio-lista-input').value = this.productoActual.precio_lista || '';
        document.getElementById('categoria-select').value = this.productoActual.id_categoria;
        document.getElementById('punto-venta-select').value = this.productoActual.id_punto_venta;
        document.getElementById('personalizable-checkbox').checked = this.productoActual.es_personalizable == 1;

        // Mostrar imagen actual si existe
        const imagenPreview = document.getElementById('imagen-preview');
        const imgSrc = this.productoActual.url_imagen || '';
        if (imgSrc) {
            imagenPreview.src = imgSrc;
            imagenPreview.style.display = 'block';
        } else {
            imagenPreview.style.display = 'none';
        }

        // Cargar opciones de personalización
        this.cargarOpcionesPersonalizacion();
    }

    limpiarFormulario() {
        document.getElementById('producto-form').reset();
        document.getElementById('opciones-container').innerHTML = '';
        document.getElementById('imagen-preview').style.display = 'none';
        document.getElementById('imagen-preview').src = '';
    }

    cargarOpcionesPersonalizacion() {
        const container = document.getElementById('opciones-container');
        container.innerHTML = '';

        if (this.productoActual && this.productoActual.opciones_personalizacion) {
            this.productoActual.opciones_personalizacion.forEach(opcion => {
                this.agregarOpcionPersonalizacion(opcion);
            });
        }
    }

    agregarOpcionPersonalizacion(opcion = null) {
        const container = document.getElementById('opciones-container');
        const opcionDiv = document.createElement('div');
        opcionDiv.className = 'opcion-personalizacion';
        opcionDiv.innerHTML = `
            <input type="text" placeholder="Nombre de la opción" value="${opcion ? opcion.nombre_opcion : ''}" class="opcion-nombre">
            <input type="number" placeholder="Precio extra" value="${opcion ? opcion.precio_extra : ''}" class="opcion-precio" step="0.01" min="0">
            <button type="button" onclick="this.parentElement.remove()" class="btn-eliminar-opcion">🗑️</button>
        `;
        container.appendChild(opcionDiv);
    }

    async guardarProducto() {
        // Sincroniza los valores del formulario con los datos actuales
        document.getElementById('nombre-input').value = document.getElementById('nombre-input').value.trim();
        document.getElementById('precio-venta-input').value = document.getElementById('precio-venta-input').value;
        document.getElementById('precio-lista-input').value = document.getElementById('precio-lista-input').value;
        document.getElementById('categoria-select').value = document.getElementById('categoria-select').value;
        document.getElementById('punto-venta-select').value = document.getElementById('punto-venta-select').value;

        const form = document.getElementById('producto-form');
        const formData = new FormData(form);

        // Agregar opciones de personalización
        const opciones = [];
        document.querySelectorAll('.opcion-personalizacion').forEach(opcionDiv => {
            const nombre = opcionDiv.querySelector('.opcion-nombre').value;
            const precio = opcionDiv.querySelector('.opcion-precio').value;
            if (nombre.trim()) {
                opciones.push({
                    nombre_opcion: nombre.trim(),
                    precio_extra: parseFloat(precio) || 0
                });
            }
        });

        formData.append('opciones_personalizacion', JSON.stringify(opciones));

        // Validar selección de punto de venta y categoría
        const puntoVentaSelect = document.getElementById('punto-venta-select');
        const categoriaSelect = document.getElementById('categoria-select');
        const puntoVentaText = puntoVentaSelect.options[puntoVentaSelect.selectedIndex]?.text?.trim() || '';
        const categoriaText = categoriaSelect.options[categoriaSelect.selectedIndex]?.text?.trim() || '';

        if (!puntoVentaText || puntoVentaText === 'Seleccionar punto de venta' || !categoriaText || categoriaText === 'Seleccionar categoría') {
            this.mostrarAlerta('Debes seleccionar punto de venta y categoría', 'error');
            return;
        }

        // No procesamos la imagen aquí - la deja que el API se encargue de todo
        // Esto evita el problema de doble guardado

        let success = false;
        if (this.productoActual) {
            success = await this.actualizarProducto(this.productoActual.id_producto, formData);
        } else {
            success = await this.crearProducto(formData);
        }

        if (success) {
            this.limpiarFormulario();
        }
    }

    abrirModal(tipo) {
        const modal = document.getElementById('producto-modal');
        const titulo = document.getElementById('modal-titulo');

        if (tipo === 'crear') {
            titulo.textContent = 'Nuevo Producto';
        } else if (tipo === 'editar') {
            titulo.textContent = 'Editar Producto';
        }

        modal.style.display = 'flex';
    }

    cerrarModal() {
        const modal = document.getElementById('producto-modal');
        modal.style.display = 'none';
        this.limpiarFormulario();
        this.productoActual = null;
    }

    setupEventListeners() {
        // Botón agregar producto
        const btnAgregar = document.getElementById('btn-agregar-producto');
        if (btnAgregar) {
            btnAgregar.addEventListener('click', () => this.nuevoProducto());
        }

        // Formulario
        const form = document.getElementById('producto-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.guardarProducto();
            });
        }

        // Botón cerrar modal
        const btnCerrar = document.querySelector('.modal-close');
        if (btnCerrar) {
            btnCerrar.addEventListener('click', () => this.cerrarModal());
        }

        // Botón agregar opción de personalización
        const btnAgregarOpcion = document.getElementById('agregar-opcion-btn');
        if (btnAgregarOpcion) {
            btnAgregarOpcion.addEventListener('click', () => this.agregarOpcionPersonalizacion());
        }

        // Preview de imagen
        const imagenInput = document.getElementById('imagen-input');
        if (imagenInput) {
            imagenInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const preview = document.getElementById('imagen-preview');
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Búsqueda
        const searchInput = document.querySelector('.input-search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                this.aplicarFiltros();
            });
        }

        // Checkbox personalizable
        const checkboxPersonalizable = document.getElementById('personalizable-checkbox');
        if (checkboxPersonalizable) {
            checkboxPersonalizable.addEventListener('change', (e) => {
                const opcionesDiv = document.getElementById('opciones-personalizacion');
                if (e.target.checked) {
                    opcionesDiv.style.display = 'block';
                    // Agregar una opción por defecto si no hay ninguna
                    if (document.querySelectorAll('.opcion-personalizacion').length === 0) {
                        this.agregarOpcionPersonalizacion();
                    }
                } else {
                    opcionesDiv.style.display = 'none';
                }
            });
        }

        // Listener para cambio de punto de venta - filtrar categorías
        const puntoVentaSelect = document.getElementById('punto-venta-select');
        if (puntoVentaSelect) {
            puntoVentaSelect.addEventListener('change', (e) => {
                const puntoVentaId = e.target.value;
                // Actualizar las categorías según el punto de venta seleccionado
                this.actualizarSelectCategorias(puntoVentaId);
                
                // Resetear la categoría seleccionada si ya no está disponible
                const categoriaSelect = document.getElementById('categoria-select');
                if (categoriaSelect && categoriaSelect.value) {
                    const categoriaActual = this.categorias.find(cat => 
                        cat.id_categoria == categoriaSelect.value
                    );
                    
                    if (categoriaActual && puntoVentaId) {
                        let categoriaEsValida = false;
                        
                        if (puntoVentaId == 3) {
                            // Si selecciono "Ambos", solo acepto categorías "Ambos"
                            categoriaEsValida = categoriaActual.id_punto_venta == 3;
                        } else {
                            // Si selecciono Buffet (1) o Kiosco (2), acepto categorías específicas + "Ambos"
                            categoriaEsValida = categoriaActual.id_punto_venta == puntoVentaId || categoriaActual.id_punto_venta == 3;
                        }
                        
                        if (!categoriaEsValida) {
                            categoriaSelect.value = '';
                        }
                    }
                }
            });
        }
    }

    setupFiltros() {
        // Búsqueda
        const inputBusqueda = document.querySelector('.input-search');
        if (inputBusqueda) {
            inputBusqueda.addEventListener('input', (e) => {
                this.filtros.busqueda = e.target.value;
                this.paginacion.paginaActual = 1;
                this.aplicarFiltros();
            });
        }

        // Filtros de selección
        const filtros = ['categoria-filter', 'punto-venta-filter', 'estado-filter', 'orden-filter'];
        filtros.forEach(filtroId => {
            const filtroElement = document.getElementById(filtroId);
            if (filtroElement) {
                filtroElement.addEventListener('change', (e) => {
                    let nombreFiltro;
                    if (filtroId === 'punto-venta-filter') {
                        nombreFiltro = 'puntoVenta';
                    } else {
                        nombreFiltro = filtroId.replace('-filter', '');
                    }
                    this.filtros[nombreFiltro] = e.target.value;
                    this.paginacion.paginaActual = 1;
                    this.aplicarFiltros();
                });
            }
        });

        // Items por página
        const itemsPorPagina = document.getElementById('items-por-pagina');
        if (itemsPorPagina) {
            itemsPorPagina.addEventListener('change', (e) => {
                this.paginacion.itemsPorPagina = parseInt(e.target.value);
                this.paginacion.paginaActual = 1;
                this.aplicarFiltros();
            });
        }

        // Botones de vista
        document.getElementById('vista-lista')?.addEventListener('click', () => this.cambiarVista('lista'));
        document.getElementById('vista-grilla')?.addEventListener('click', () => this.cambiarVista('grilla'));

        // Paginación
        document.getElementById('prev-page')?.addEventListener('click', () => this.cambiarPagina(this.paginacion.paginaActual - 1));
        document.getElementById('next-page')?.addEventListener('click', () => this.cambiarPagina(this.paginacion.paginaActual + 1));
        document.getElementById('prev-page-grilla')?.addEventListener('click', () => this.cambiarPagina(this.paginacion.paginaActual - 1));
        document.getElementById('next-page-grilla')?.addEventListener('click', () => this.cambiarPagina(this.paginacion.paginaActual + 1));

        // Delegación de eventos para el checkbox (funciona sin importar cuándo se crea)
        document.addEventListener('change', (e) => {
            if (e.target && e.target.id === 'mostrar-eliminados') {
                this.aplicarFiltros();
            }
        });
    }

    async aplicarFiltros() {
        // Si cambió el estado del checkbox de mostrar eliminados, recargar productos
        const mostrarEliminadosCheckbox = document.getElementById('mostrar-eliminados');
        const mostrarEliminados = mostrarEliminadosCheckbox?.checked || false;

        const urlActual = mostrarEliminados ?
            '../../backend/admin/api/api_productos.php?action=listar&mostrar_todos=1' :
            '../../backend/admin/api/api_productos.php?action=listar';

        // Verificar si necesitamos recargar los productos
        if (!this.urlAnterior || this.urlAnterior !== urlActual) {
            this.urlAnterior = urlActual;
            await this.cargarProductos();
            return;
        }

        let productosFiltrados = [...this.productos];

        // Aplicar filtro de búsqueda
        if (this.filtros.busqueda) {
            const busqueda = this.filtros.busqueda.toLowerCase();
            productosFiltrados = productosFiltrados.filter(producto =>
                producto.nombre.toLowerCase().includes(busqueda) ||
                producto.categoria_nombre?.toLowerCase().includes(busqueda)
            );
        }

        // Aplicar filtro de categoría
        if (this.filtros.categoria) {
            productosFiltrados = productosFiltrados.filter(producto =>
                producto.id_categoria == this.filtros.categoria
            );
        }

        // Aplicar filtro de punto de venta
        if (this.filtros.puntoVenta) {
            productosFiltrados = productosFiltrados.filter(producto =>
                producto.id_punto_venta == this.filtros.puntoVenta
            );
        }

        // Aplicar filtro de estado
        if (this.filtros.estado !== '') {
            productosFiltrados = productosFiltrados.filter(producto =>
                producto.estado == this.filtros.estado
            );
        }

        // Aplicar ordenamiento
        this.ordenarProductos(productosFiltrados);

        // Aplicar paginación
        this.paginacion.totalItems = productosFiltrados.length;
        this.paginacion.totalPaginas = Math.ceil(this.paginacion.totalItems / this.paginacion.itemsPorPagina);

        const inicio = (this.paginacion.paginaActual - 1) * this.paginacion.itemsPorPagina;
        const fin = inicio + this.paginacion.itemsPorPagina;
        const productosPaginados = productosFiltrados.slice(inicio, fin);

        // Renderizar según la vista actual
        if (this.vistaActual === 'lista') {
            this.renderizarProductosLista(productosPaginados);
        } else {
            this.renderizarProductosGrilla(productosPaginados);
        }

        this.actualizarInfoPaginacion();
    }

    ordenarProductos(productos) {
        switch (this.filtros.orden) {
            case 'alfabetico':
                productos.sort((a, b) => a.nombre.localeCompare(b.nombre));
                break;
            case 'alfabetico-desc':
                productos.sort((a, b) => b.nombre.localeCompare(a.nombre));
                break;
            case 'precio-asc':
                productos.sort((a, b) => parseFloat(a.precio_venta) - parseFloat(b.precio_venta));
                break;
            case 'precio-desc':
                productos.sort((a, b) => parseFloat(b.precio_venta) - parseFloat(a.precio_venta));
                break;
            case 'fecha':
            default:
                productos.sort((a, b) => new Date(b.fecha_creacion || 0) - new Date(a.fecha_creacion || 0));
                break;
        }
    }

    cambiarPagina(nuevaPagina) {
        if (nuevaPagina >= 1 && nuevaPagina <= this.paginacion.totalPaginas) {
            this.paginacion.paginaActual = nuevaPagina;
            this.aplicarFiltros();
        }
    }

    actualizarInfoPaginacion() {
        const inicio = (this.paginacion.paginaActual - 1) * this.paginacion.itemsPorPagina + 1;
        const fin = Math.min(inicio + this.paginacion.itemsPorPagina - 1, this.paginacion.totalItems);

        // Actualizar info para vista lista
        const pageInfo = document.getElementById('page-info');
        const itemsInfo = document.getElementById('items-info');
        const prevBtn = document.getElementById('prev-page');
        const nextBtn = document.getElementById('next-page');

        if (pageInfo) pageInfo.textContent = `Página ${this.paginacion.paginaActual} de ${this.paginacion.totalPaginas}`;
        if (itemsInfo) itemsInfo.textContent = `Mostrando ${inicio}-${fin} de ${this.paginacion.totalItems} productos`;
        if (prevBtn) prevBtn.disabled = this.paginacion.paginaActual === 1;
        if (nextBtn) nextBtn.disabled = this.paginacion.paginaActual === this.paginacion.totalPaginas;

        // Actualizar info para vista grilla
        const pageInfoGrilla = document.getElementById('page-info-grilla');
        const itemsInfoGrilla = document.getElementById('items-info-grilla');
        const prevBtnGrilla = document.getElementById('prev-page-grilla');
        const nextBtnGrilla = document.getElementById('next-page-grilla');

        if (pageInfoGrilla) pageInfoGrilla.textContent = `Página ${this.paginacion.paginaActual} de ${this.paginacion.totalPaginas}`;
        if (itemsInfoGrilla) itemsInfoGrilla.textContent = `Mostrando ${inicio}-${fin} de ${this.paginacion.totalItems} productos`;
        if (prevBtnGrilla) prevBtnGrilla.disabled = this.paginacion.paginaActual === 1;
        if (nextBtnGrilla) nextBtnGrilla.disabled = this.paginacion.paginaActual === this.paginacion.totalPaginas;

        // Actualizar contador general
        const contadores = document.querySelectorAll('.productos-lista-count');
        contadores.forEach(contador => {
            contador.textContent = `${this.paginacion.totalItems} productos encontrados`;
        });
    }

    cambiarVista(vista) {
        this.vistaActual = vista;

        const btnLista = document.getElementById('vista-lista');
        const btnGrilla = document.getElementById('vista-grilla');
        const containerLista = document.getElementById('productos-lista');
        const containerGrilla = document.getElementById('productos-grilla');

        if (vista === 'lista') {
            btnLista?.classList.add('active');
            btnGrilla?.classList.remove('active');
            if (containerLista) containerLista.style.display = '';
            if (containerGrilla) containerGrilla.style.display = 'none';
        } else {
            btnGrilla?.classList.add('active');
            btnLista?.classList.remove('active');
            if (containerGrilla) containerGrilla.style.display = '';
            if (containerLista) containerLista.style.display = 'none';
        }

        this.aplicarFiltros();
    }

    // Funciones de exportación
    exportarCSV() {
        const datosExport = this.productos.map(producto => ({
            'ID': producto.id_producto || producto.id || '',
            'Nombre': producto.nombre || '',
            'Categoría': producto.categoria_nombre || '',
            'Punto de Venta': producto.punto_venta_nombre || '',
            'Precio de Venta': this.formatearPrecio(producto.precio_venta),
            'Precio de Lista': producto.precio_lista ? this.formatearPrecio(producto.precio_lista) : '',
            'Estado': producto.estado ? 'Activo' : 'Inactivo'
        }));

        this.descargarCSV(datosExport, 'productos_buffet');
    }

    formatearPrecio(precio) {
        if (!precio) return '';
        return parseFloat(precio).toFixed(2);
    }

    descargarCSV(datos, nombreArchivo) {
        const csv = this.convertirACSV(datos);
        const BOM = '\uFEFF'; // Byte Order Mark para UTF-8
        const blob = new Blob([BOM + csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${nombreArchivo}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    convertirACSV(datos) {
        if (!datos.length) return '';

        const headers = Object.keys(datos[0]);
        const csvRows = [];

        // Añadir headers
        csvRows.push(headers.map(header => `"${header}"`).join(','));

        // Añadir datos
        datos.forEach(fila => {
            const valores = headers.map(header => {
                let valor = String(fila[header] || '');
                // Escapar comillas dobles
                valor = valor.replace(/"/g, '""');
                return `"${valor}"`;
            });
            csvRows.push(valores.join(','));
        });

        return csvRows.join('\r\n');
    }

    // Funciones de importación
    mostrarImportar() {
        document.getElementById('importar-modal').style.display = 'flex';
    }

    cerrarModalImportar() {
        document.getElementById('importar-modal').style.display = 'none';
        document.getElementById('archivo-importar').value = '';
        document.getElementById('preview-import').style.display = 'none';
        document.getElementById('btn-confirmar-import').disabled = true;
        this.datosImport = null;
    }

    previsualizarImport(input) {
        const archivo = input.files[0];
        if (!archivo) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            const contenido = e.target.result;
            let datos;

            if (archivo.name.endsWith('.csv')) {
                datos = this.parsearCSV(contenido);
            } else {
                this.mostrarAlerta('Formato no soportado. Use CSV por ahora.', 'error');
                return;
            }

            this.datosImport = datos;
            this.mostrarPreviewImport(datos);
        };

        reader.readAsText(archivo);
    }

    parsearCSV(contenido) {
        const lineas = contenido.split('\n');
        const headers = lineas[0].split(',').map(h => h.trim().replace(/"/g, ''));
        const datos = [];

        for (let i = 1; i < lineas.length; i++) {
            if (lineas[i].trim()) {
                const valores = lineas[i].split(',').map(v => v.trim().replace(/"/g, ''));
                const fila = {};
                headers.forEach((header, index) => {
                    fila[header] = valores[index] || '';
                });
                datos.push(fila);
            }
        }

        return datos;
    }

    mostrarPreviewImport(datos) {
        const previewDiv = document.getElementById('preview-import');
        const tableDiv = document.getElementById('preview-table');
        const countSpan = document.getElementById('import-count');

        if (datos.length === 0) {
            previewDiv.style.display = 'none';
            return;
        }

        // Mostrar primeras 5 filas
        const preview = datos.slice(0, 5);
        const headers = Object.keys(preview[0]);

        let html = '<table><thead><tr>';
        headers.forEach(header => {
            html += `<th>${header}</th>`;
        });
        html += '</tr></thead><tbody>';

        preview.forEach(fila => {
            html += '<tr>';
            headers.forEach(header => {
                html += `<td>${fila[header]}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table>';

        tableDiv.innerHTML = html;
        countSpan.textContent = `${datos.length} productos detectados`;
        previewDiv.style.display = 'block';
        document.getElementById('btn-confirmar-import').disabled = false;
    }

    async confirmarImport() {
        if (!this.datosImport) return;

        try {
            this.mostrarLoader();
            const response = await fetch('../../backend/admin/api/api_productos.php?action=importar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ productos: this.datosImport })
            });

            const data = await response.json();
            if (data.success) {
                this.mostrarAlerta(`${data.importados} productos importados correctamente`, 'success');
                await this.cargarProductos();
                this.cerrarModalImportar();
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error importando productos:', error);
            this.mostrarAlerta('Error al importar productos: ' + error.message, 'error');
        } finally {
            this.ocultarLoader();
        }
    }

    descargarPlantilla() {
        const plantilla = [
            {
                'nombre': 'Coca Cola 500ml',
                'precio_venta': '2.50',
                'precio_lista': '3.00',
                'categoria': 'Bebidas',
                'punto_venta': 'Kiosco',
                'es_personalizable': 'No'
            },
            {
                'nombre': 'Sandwich Jamón y Queso',
                'precio_venta': '4.50',
                'precio_lista': '5.00',
                'categoria': 'Comidas',
                'punto_venta': 'Buffet',
                'es_personalizable': 'Sí'
            }
        ];

        this.descargarCSV(plantilla, 'plantilla_productos');
    }

    // Edición en línea
    iniciarEdicionInline(elemento, campo, id, valorActual) {
        const input = document.createElement('input');
        input.type = campo === 'precio_venta' || campo === 'precio_lista' ? 'number' : 'text';
        input.className = 'editable-input';
        input.value = valorActual;
        input.setAttribute('data-campo', campo);
        input.setAttribute('data-id', id);

        const controles = document.createElement('div');
        controles.className = 'edit-controls';
        controles.innerHTML = `
            <button class="edit-save" onclick="productosManager.guardarEdicionInline(this)">✓</button>
            <button class="edit-cancel" onclick="productosManager.cancelarEdicionInline(this)">✕</button>
        `;

        elemento.classList.add('editable-cell');
        elemento.innerHTML = '';
        elemento.appendChild(input);
        elemento.appendChild(controles);
        input.focus();
        input.select();
    }

    async guardarEdicionInline(boton) {
        const controles = boton.parentElement;
        const input = controles.previousElementSibling;
        const celda = controles.parentElement;
        const campo = input.getAttribute('data-campo');
        const id = input.getAttribute('data-id');
        const nuevoValor = input.value;

        try {
            const response = await fetch('../../backend/admin/api/api_productos.php?action=editar_campo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id,
                    campo,
                    valor: nuevoValor
                })
            });

            const data = await response.json();
            if (data.success) {
                // Actualizar en el array local
                const producto = this.productos.find(p => p.id == id);
                if (producto) {
                    const valorAnterior = producto[campo];
                    producto[campo] = nuevoValor;
                }

                celda.classList.remove('editable-cell');
                celda.innerHTML = nuevoValor;
                this.mostrarAlerta('Campo actualizado', 'success');
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error actualizando campo:', error);
            this.mostrarAlerta('Error al actualizar: ' + error.message, 'error');
            this.cancelarEdicionInline(boton);
        }
    }

    cancelarEdicionInline(boton) {
        const controles = boton.parentElement;
        const input = controles.previousElementSibling;
        const celda = controles.parentElement;
        const valorOriginal = input.defaultValue || input.value;

        celda.classList.remove('editable-cell');
        celda.innerHTML = valorOriginal;
    }

    mostrarAlerta(mensaje, tipo = 'info') {
        if (window.notificacionesManager) {
            window.notificacionesManager.mostrar(mensaje, tipo);
        } else {
            // Fallback para compatibilidad
            this.mostrarAlertaFallback(mensaje, tipo);
        }
    }

    // Método de fallback para compatibilidad
    mostrarAlertaFallback(mensaje, tipo = 'info') {
        // Crear alerta temporal
        const alerta = document.createElement('div');
        alerta.className = `alerta alerta-${tipo}`;
        alerta.textContent = mensaje;

        if (tipo === 'success') {
            alerta.style.backgroundColor = '#28a745';
        } else if (tipo === 'error') {
            alerta.style.backgroundColor = '#dc3545';
        } else {
            alerta.style.backgroundColor = '#17a2b8';
        }

        let container = document.getElementById('alertas-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'alertas-container';
            container.id = 'alertas-container';
            document.body.appendChild(container);
        }
        container.appendChild(alerta);

        setTimeout(() => {
            alerta.remove();
        }, 3000);
    }

    mostrarLoader() {
        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = 'flex';
        }
    }

    ocultarLoader() {
        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    async cambiarEstadoProducto(id, activar = null, omitirAlerta = false) {
        try {
            this.mostrarLoader();
            let action = 'toggle_activo';
            let body = { id };
            if (activar !== null) {
                action = activar ? 'activar' : 'desactivar';
            }
            const response = await fetch(`../../backend/admin/api/api_productos.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await response.json();
            if (data.success) {
                if (!omitirAlerta) {
                    this.mostrarAlerta('Estado de producto actualizado', 'success');
                    await this.cargarProductos();
                }
                return true;
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            console.error('Error cambiando estado:', error);
            if (!omitirAlerta) this.mostrarAlerta('Error al cambiar estado: ' + error.message, 'error');
            return false;
        } finally {
            this.ocultarLoader();
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    if (!window.productosManager) {
        window.productosManager = new ProductosManager();
    }
    initProductosEventListeners();
});

// También inicializar si el DOM ya está listo (para carga dinámica)
if (document.readyState === 'loading') {
    // El DOM aún se está cargando, usar el listener normal
} else {
    // El DOM ya está listo, inicializar inmediatamente
    if (!window.productosManager) {
        window.productosManager = new ProductosManager();
    }
    initProductosEventListeners();
}

function initProductosEventListeners() {
    // Acciones masivas
    document.getElementById('btn-activar-masivo')?.addEventListener('click', async function() {
        const ids = getSelectedProductoIds();
        if (ids.length === 0) return window.productosManager.mostrarAlerta('Selecciona productos para activar', 'info');
        let activados = 0;
        for (const id of ids) {
            try {
                await window.productosManager.cambiarEstadoProducto(id, true, true); // true extra: omitir alerta individual
                activados++;
            } catch {}
        }
        if (activados > 0) window.productosManager.mostrarAlerta(`Se han activado ${activados} productos.`, 'success');
        await window.productosManager.cargarProductos();
    });
    document.getElementById('btn-desactivar-masivo')?.addEventListener('click', async function() {
        const ids = getSelectedProductoIds();
        if (ids.length === 0) return window.productosManager.mostrarAlerta('Selecciona productos para desactivar', 'info');
        let desactivados = 0;
        for (const id of ids) {
            try {
                await window.productosManager.cambiarEstadoProducto(id, false, true); // true extra: omitir alerta individual
                desactivados++;
            } catch {}
        }
    if (desactivados > 0) window.productosManager.mostrarAlerta(`Se han desactivado ${desactivados} productos.`, 'success');
        await window.productosManager.cargarProductos();
    });
    document.getElementById('btn-eliminar-masivo')?.addEventListener('click', async function() {
        const ids = getSelectedProductoIds();
        if (ids.length === 0) return window.productosManager.mostrarAlerta('Selecciona productos para eliminar', 'info');
        if (!confirm('¿Seguro que deseas eliminar los productos seleccionados?')) return;
        let eliminados = 0;
        let desactivados = 0;
        for (const id of ids) {
            try {
                const res = await window.productosManager.eliminarProducto(id, true, true); // true extra: no alerta individual
                if (res === 'desactivado') desactivados++;
                else if (res === 'eliminado') eliminados++;
            } catch {}
        }
        // Mostrar siempre al menos una alerta resumen
        if (eliminados > 0 && desactivados === 0) {
            window.productosManager.mostrarAlerta(`Se han eliminado ${eliminados} productos.`, 'success');
        } else if (desactivados > 0 && eliminados === 0) {
            window.productosManager.mostrarAlerta(`${desactivados} productos desactivados. No se pueden eliminar completamente porque tienen pedidos asociados.`, 'info');
        } else if (eliminados > 0 && desactivados > 0) {
            window.productosManager.mostrarAlerta(`Se han eliminado ${eliminados} productos.`, 'success');
            window.productosManager.mostrarAlerta(`${desactivados} productos desactivados. No se pueden eliminar completamente porque tienen pedidos asociados.`, 'info');
        }
        window.productosManager.cargarProductos();
    });
}

function getSelectedProductoIds() {
    return Array.from(document.querySelectorAll('.producto-checkbox:checked')).map(cb => cb.dataset.productoId);
}
