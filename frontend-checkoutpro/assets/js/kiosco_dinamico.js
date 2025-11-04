// kiosco_dinamico.js
// --- Cargar productos y categorías dinámicamente desde la API ---
const API_URL = '/Totem_Murialdo/backend-checkoutpro/api/api_kiosco.php?punto_venta=2'; // Filtrar por Kiosco
let productosPorCategoria = {};
let categorias = [];
let carrito = [];
let MAX_CANTIDAD = 10;
const PUNTO_VENTA_ID = 2; // Kiosco
const PUNTO_VENTA_NOMBRE = 'Kiosco';

async function cargarMaxCantidadCarrito() {
    try {
        const res = await fetch('/Totem_Murialdo/backend-checkoutpro/admin/api/configuracion_horarios.php');
        const data = await res.json();
        if (data.success && data.config && typeof data.config.max_unidades_carrito !== 'undefined') {
            MAX_CANTIDAD = parseInt(data.config.max_unidades_carrito) || 10;
        }
    } catch (e) {
        MAX_CANTIDAD = 10;
    }
}
let categoriaActual = '';

// Cargar productos y categorías al iniciar
document.addEventListener('DOMContentLoaded', async () => {
    // Establecer el punto de venta en localStorage
    localStorage.setItem('punto_venta_id', PUNTO_VENTA_ID);
    localStorage.setItem('punto_venta_nombre', PUNTO_VENTA_NOMBRE);
    
    await cargarMaxCantidadCarrito();
    try {
        await cargarProductosYCategorias();

        cargarCarritoDeLocalStorage();

        renderizarCategorias();

        if (categorias.length > 0) {
            mostrarCategoria(categorias[0].nombre);
        }

        asignarEventosFooter();
    } catch (error) {
        console.error('Error durante la inicialización:', error);
    } finally {
        // Ocultar loading
        const loading = document.getElementById('loading');
        if (loading) loading.style.display = 'none';
    }
});

async function cargarProductosYCategorias() {
    try {
        const res = await fetch(API_URL);

        if (!res.ok) {
            throw new Error(`Error HTTP: ${res.status}`);
        }

        // Primero obtener el texto de la respuesta para debugging
        const texto = await res.text();

        // Intentar parsear como JSON
        let data;
        try {
            data = JSON.parse(texto);

            if (data.success) {
                productosPorCategoria = data.productos;
                categorias = data.categorias;
                // Guardar productosPorCategoria en localStorage para acceso desde carrito.js
                localStorage.setItem('productosPorCategoria', JSON.stringify(productosPorCategoria));
            } else {
                console.error('Error en la respuesta:', data.error);
                alert('Error al cargar productos: ' + (data.error || 'Desconocido'));
            }
        } catch (parseError) {
            console.error('Error al parsear JSON:', parseError);
            console.error('Contenido recibido:', texto);
            throw new Error('La respuesta del servidor no es JSON válido');
        }
    } catch (e) {
        console.error('Error de conexión:', e);
        alert('Error de conexión al cargar productos. Por favor, intenta de nuevo.');
    }
}

function renderizarCategorias() {
    const contenedor = document.getElementById('Contenedor_Categorias');
    contenedor.innerHTML = '';
    categorias.forEach(cat => {
        const btn = document.createElement('button');
        btn.innerHTML = `<img src="../assets/images/Iconos/Icono_${cat.nombre}.svg" alt="" onerror="this.src='../assets/images/Iconos/Icono_Snacks.svg'"><p>${cat.nombre}</p>`;
        btn.onclick = () => {
            mostrarCategoria(cat.nombre);
            // Cerrar menú en móvil al seleccionar categoría
            cerrarMenuMovil();
        };
        contenedor.appendChild(btn);
    });
}

function mostrarCategoria(nombre) {
    categoriaActual = nombre;
    document.getElementById('Nombre_Categoria_Seleccionada').innerText = nombre;
    const contenedor = document.getElementById('Contenedor_Productos');
    contenedor.innerHTML = '';

    const categoriaDiv = document.createElement('div');
    categoriaDiv.className = 'Categoria';
    categoriaDiv.style.display = 'flex';

    const productos = productosPorCategoria[nombre] || [];
    if (productos.length === 0) {
        categoriaDiv.innerHTML = '<div style="width: 100%; text-align: center; padding: 2rem;">No hay productos en esta categoría</div>';
    } else {
        productos.forEach(prod => {
            const div = document.createElement('div');
            div.className = 'Producto';
            const imagenUrl = prod.imagen || `Images/Kiosco/${nombre}/${prod.id || prod.nombre}.png`;
            div.innerHTML = `
                <img src="${imagenUrl}" alt="${prod.nombre}" onerror="this.src='Images/Iconos/Icono_Snacks.png'">
                <p class="Nombre_Producto">${prod.nombre}</p>
                <p class="Precio_Producto">$${prod.precio.toLocaleString()}</p>
            `;
            div.style.cursor = 'pointer';
            div.onclick = () => agregarAlCarrito(prod.nombre, prod.precio, div);
            categoriaDiv.appendChild(div);
        });
    }

    contenedor.appendChild(categoriaDiv);

    // Marcar botón activo
    document.querySelectorAll('.Contenedor_Categorias button').forEach(btn => {
        btn.classList.toggle('boton-activo', btn.innerText.trim() === nombre);
    });
}

function agregarAlCarrito(nombre, precio, elemento) {
    // Buscar el producto original para obtener id y si es personalizable
    let prod = null;
    for (const cat in productosPorCategoria) {
        prod = productosPorCategoria[cat].find(p => p.nombre === nombre);
        if (prod) break;
    }
    if (!prod) return;
    // Mostrar overlay solo si es_personalizable == 1
    if (prod.es_personalizable == 1) {
        mostrarOverlayPersonalizacion(prod);
        return;
    }
    // Unificar productos iguales (incluyendo personalizaciones)
    let personalizaciones = [];
    // Buscar si ya existe un producto igual en el carrito
    const existente = carrito.find(p =>
        p.id_producto === prod.id &&
        JSON.stringify(p.personalizaciones || []) === JSON.stringify(personalizaciones)
    );
    if (existente) {
        if (existente.cantidad < MAX_CANTIDAD) {
            existente.cantidad += 1;
        } else {
            mostrarMensajeMaximo('Máximo ' + MAX_CANTIDAD + ' unidades por producto');
            return;
        }
    } else {
        carrito.push({
            id_producto: prod.id,
            nombre: prod.nombre,
            cantidad: 1,
            precio_unitario: Number(prod.precio_unitario ?? prod.precio ?? precio),
            es_personalizable: prod.es_personalizable,
            imagen: prod.imagen || `Images/Kiosco/${categoriaActual}/${prod.id || prod.nombre}.png`,
            personalizaciones: personalizaciones
        });
    }
    elemento.classList.add('added');
    setTimeout(() => elemento.classList.remove('added'), 500);
    actualizarCarrito();
// Muestra el overlay de personalización con las opciones del producto
function mostrarOverlayPersonalizacion(prod) {
    const overlay = document.getElementById('Overlay_Personalizacion');
    if (!overlay) return;
    // Mostrar overlay
    overlay.style.display = 'flex';
    // Actualizar nombre y precio
    overlay.querySelector('.OP_NombreProducto').textContent = prod.nombre;
    overlay.querySelector('.OP_ImagenProducto').src = prod.imagen || `../assets/images/Kiosco/${categoriaActual}/${prod.id || prod.nombre}.png`;
    overlay.querySelector('.OP_Precio').textContent = `$${prod.precio.toLocaleString()}`;
    // Contenedor de personalizaciones
    const contenedor = overlay.querySelector('.OP_Contenedor_Personalizaciones');
    contenedor.innerHTML = '';
    if (Array.isArray(prod.opciones_personalizacion) && prod.opciones_personalizacion.length > 0) {
        prod.opciones_personalizacion.forEach((opcion, idx) => {
            const div = document.createElement('div');
            div.className = 'OP_Personalizacion';
            div.style.display = 'flex';
            div.style.alignItems = 'center';
            div.style.justifyContent = 'flex-start';
            // Checkbox funcional
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'OP_Checkbox';
            checkbox.id = `personalizacion_${idx}`;
            checkbox.value = opcion.nombre_opcion;
            div.appendChild(checkbox);
            // Nombre de la opción y precio extra
            const nombre = document.createElement('label');
            nombre.className = 'OP_Personalizacion_Nombre';
            nombre.textContent = opcion.nombre_opcion;
            nombre.setAttribute('for', checkbox.id);
            div.appendChild(nombre);
            // Precio extra a la derecha, color #3A9A53
            let precioExtra = Number(opcion.precio_extra) || 0;
            if (precioExtra > 0) {
                const precioSpan = document.createElement('span');
                precioSpan.textContent = `$${precioExtra.toLocaleString()}`;
                precioSpan.style.color = '#3A9A53';
                precioSpan.style.fontWeight = 'bold';
                precioSpan.style.marginLeft = 'auto';
                precioSpan.style.fontSize = '2dvh';
                div.appendChild(precioSpan);
            }
            contenedor.appendChild(div);
        });
    } else {
        const sinOpciones = document.createElement('p');
        sinOpciones.textContent = 'No hay opciones personalizables.';
        contenedor.appendChild(sinOpciones);
    }

    // Botón Agregar funcional
    const btnAgregar = document.getElementById('Agregar');
    if (btnAgregar) {
        btnAgregar.onclick = () => {
            // Obtener personalizaciones elegidas como objetos
            const seleccionadas = [];
            contenedor.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                // Buscar la opción original para obtener precio_extra
                const opcion = prod.opciones_personalizacion.find(o => o.nombre_opcion === checkbox.value);
                if (opcion) {
                    seleccionadas.push({
                        id_opcion: opcion.id_opcion,
                        nombre_opcion: opcion.nombre_opcion,
                        precio_extra: Number(opcion.precio_extra) || 0
                    });
                }
            });
            // Agregar al carrito
            carrito.push({
                id_producto: prod.id,
                nombre: prod.nombre,
                cantidad: 1,
                precio_unitario: prod.precio,
                es_personalizable: prod.es_personalizable,
                imagen: prod.imagen || `Images/Kiosco/${categoriaActual}/${prod.id || prod.nombre}.png`,
                personalizaciones: seleccionadas
            });
            overlay.style.display = 'none';
            actualizarCarrito();
        };
    }
    // Botón Eliminar cierra el overlay
    const btnEliminar = document.getElementById('Eliminar');
    if (btnEliminar) {
        btnEliminar.onclick = () => {
            overlay.style.display = 'none';
        };
    }
}
    // ...existing code...
    // Notificación máximo igual a carrito.js
    function mostrarMensajeMaximo(msg) {
        const mensajeDiv = document.getElementById('mensaje-maximo');
        if (mensajeDiv) {
            mensajeDiv.textContent = msg;
            mensajeDiv.style.display = 'block';
            mensajeDiv.style.background = '#ffe0e0';
            mensajeDiv.style.color = '#b30000';
            mensajeDiv.style.textAlign = 'center';
            mensajeDiv.style.padding = '10px';
            mensajeDiv.style.margin = '0';
            mensajeDiv.style.borderRadius = '8px';
            mensajeDiv.style.fontWeight = 'bold';
            mensajeDiv.style.zIndex = '9999';
            mensajeDiv.style.position = 'fixed';
            mensajeDiv.style.left = '50%';
            mensajeDiv.style.top = '50%';
            mensajeDiv.style.transform = 'translate(-50%, -50%)';
            mensajeDiv.style.minWidth = '260px';
            mensajeDiv.style.maxWidth = '90vw';
            mensajeDiv.style.fontFamily = 'Arial, sans-serif';
            mensajeDiv.style.border = '2px solid #b30000';
            // Ocultar después de 1 segundo
            clearTimeout(mostrarMensajeMaximo.timeout);
            mostrarMensajeMaximo.timeout = setTimeout(() => {
                mensajeDiv.style.display = 'none';
            }, 1000);
        }
    }
}

function actualizarCarrito() {
    const contenedor = document.querySelector('.Footer_Contenedor_Orden');
    if (carrito.length === 0) {
        contenedor.textContent = 'Tu orden está vacía';
        return;
    }
    let html = `
        <div style="display: flex; flex-direction: column; width: 100%; height: 100%;">
            <div style="flex: 1; overflow-y: auto;">
                <div style="display: flex; flex-direction: column; gap: 0.5dvh; padding: 0.78dvh;">
    `;
    // Agrupar productos iguales (nombre, id_producto, personalizaciones)
    function personalizacionesKey(persArr) {
        if (!Array.isArray(persArr)) return '';
        // Ordenar y serializar para comparación robusta
        return JSON.stringify(persArr.map(p => {
            if (typeof p === 'object') {
                return {
                    nombre_opcion: p.nombre_opcion || '',
                    precio_extra: Number(p.precio_extra) || 0
                };
            }
            return p;
        }).sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b))));
    }
    const agrupados = [];
    carrito.forEach(item => {
        const key = item.id_producto + '|' + personalizacionesKey(item.personalizaciones);
        const existente = agrupados.find(p => p.key === key);
        if (existente) {
            existente.cantidad += item.cantidad;
        } else {
            agrupados.push({ ...item, key });
        }
    });
    agrupados.forEach(item => {
        const precioUnit = Number(item.precio_unitario) || 0;
        const extras = Array.isArray(item.personalizaciones) ? item.personalizaciones.reduce((acc, p) => acc + (typeof p === 'object' ? (parseFloat(String(p.precio_extra).replace(',', '.')) || 0) : 0), 0) : 0;
        const subtotal = (precioUnit + extras) * item.cantidad;
        let persStr = '';
        if (Array.isArray(item.personalizaciones) && item.personalizaciones.length > 0) {
            persStr = ' (' + item.personalizaciones.map(p => {
                if (typeof p === 'string') return p;
                return p.nombre_opcion || '';
            }).join(' - ') + ')';
        }
        html += `
            <div style="display: flex; justify-content: space-between; font-size: 1.25dvh; font-family: sans-serif;">
                <span>${item.cantidad}x ${item.nombre}${persStr}</span>
                <span>$${isNaN(subtotal) ? '0' : subtotal.toLocaleString()}</span>
            </div>
        `;
    });
    const total = agrupados.reduce((sum, item) => {
        const precioUnit = Number(item.precio_unitario) || 0;
        const extras = Array.isArray(item.personalizaciones) ? item.personalizaciones.reduce((acc, p) => acc + (typeof p === 'object' ? (parseFloat(String(p.precio_extra).replace(',', '.')) || 0) : 0), 0) : 0;
        const subtotal = (precioUnit + extras) * item.cantidad;
        return sum + (isNaN(subtotal) ? 0 : subtotal);
    }, 0);
    html += `
                </div>
            </div>
            <div class="Carrito_Total" style="border-top: 0.1dvh solid #333; margin-top: 0.5dvh; padding: 0.78dvh; font-size: 1.5dvh; font-weight: bold; font-family: sans-serif; display: flex; justify-content: space-between;">
                <span>Total:</span>
                <span>$${total.toLocaleString()}</span>
            </div>
        </div>
    `;
    contenedor.innerHTML = html;
    localStorage.setItem('carrito', JSON.stringify(carrito));
    localStorage.setItem('carrito_total', total.toString());
    actualizarContadorCarrito(); // 👈 ACTUALIZAR CONTADOR
}

function actualizarContadorCarrito() {
    const contador = document.getElementById("Contador_Carrito");
    const totalProductos = carrito.reduce((sum, item) => sum + item.cantidad, 0);
    if (contador) contador.textContent = totalProductos;
}

function cargarCarritoDeLocalStorage() {
    const carritoGuardado = localStorage.getItem('carrito');
    if (carritoGuardado) {
        carrito = JSON.parse(carritoGuardado);
        actualizarCarrito();
    }
    actualizarContadorCarrito();
}

function asignarEventosFooter() {
    const botonContinuar = document.getElementById('Boton_Continuar');
    if (botonContinuar) {
        botonContinuar.addEventListener('click', () => {
            const total = carrito.reduce((sum, item) => sum + ((Number(item.precio_unitario) + (Array.isArray(item.personalizaciones) ? item.personalizaciones.reduce((acc, p) => acc + (typeof p === 'object' ? (parseFloat(String(p.precio_extra).replace(',', '.')) || 0) : 0), 0) : 0)) * item.cantidad), 0);
            if (total > 0) {
                window.location.href = 'carrito.html';
            } else {
                alert('Agrega productos al carrito antes de continuar.');
            }
        });
    }

    const botonCancelar = document.getElementById('Boton_Cancelar');
    if (botonCancelar) {
        botonCancelar.addEventListener('click', () => {
            carrito = [];
            actualizarCarrito();
            localStorage.removeItem('carrito');
            localStorage.removeItem('carrito_total');
            actualizarContadorCarrito();
        });
    }
}

// Funcionalidad del menú móvil
function esMobil() {
    return window.innerWidth <= 600;
}

function cerrarMenuMovil() {
    if (esMobil()) {
        const columnaCategorias = document.querySelector('.Columna_Categorias');
        const menuOverlay = document.getElementById('menu-overlay');
        
        if (columnaCategorias && menuOverlay) {
            columnaCategorias.classList.remove('menu-activo');
            menuOverlay.classList.remove('activo');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menu-toggle');
    const menuOverlay = document.getElementById('menu-overlay');
    const columnaCategorias = document.querySelector('.Columna_Categorias');
    
    if (menuToggle && menuOverlay && columnaCategorias) {
        // Abrir menú
        menuToggle.addEventListener('click', function() {
            if (esMobil()) {
                columnaCategorias.classList.add('menu-activo');
                menuOverlay.classList.add('activo');
            }
        });
        
        // Cerrar menú al tocar el overlay
        menuOverlay.addEventListener('click', function() {
            cerrarMenuMovil();
        });
        
        // Cerrar menú al redimensionar a desktop
        window.addEventListener('resize', function() {
            if (!esMobil()) {
                cerrarMenuMovil();
            }
        });
    }
    
    // Hacer que Footer_Contenedor_Orden sea clickeable para ir al carrito
    const footerOrden = document.querySelector('.Footer_Contenedor_Orden');
    if (footerOrden) {
        footerOrden.style.cursor = 'pointer';
        footerOrden.addEventListener('click', function() {
            window.location.href = 'carrito.html';
        });
    }

    // Funcionalidad para mostrar/ocultar footer en móviles
    function isMobile() {
        return window.innerWidth <= 600;
    }
    
    if (isMobile()) {
        const botonToggleFooter = document.querySelector('.boton-toggle-footer');
        const footerContenedorOrden = document.querySelector('.Footer_Contenedor_Orden');
        const footer = document.querySelector('footer');
        
        if (botonToggleFooter && footerContenedorOrden && footer) {
            botonToggleFooter.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevenir conflictos con otros eventos
                
                // Toggle la clase hidden en el contenedor de orden
                footerContenedorOrden.classList.toggle('hidden');
                
                // Toggle la clase collapsed en el footer
                footer.classList.toggle('footer-collapsed');
                
                // Cambiar la flecha según el estado
                if (footerContenedorOrden.classList.contains('hidden')) {
                    botonToggleFooter.textContent = '↑'; // Footer colapsado, mostrar flecha hacia arriba para expandir
                } else {
                    botonToggleFooter.textContent = '↓'; // Footer expandido, mostrar flecha hacia abajo para colapsar
                }
            });
        }
    }
    
    // Reajustar en caso de redimensionar ventana
    window.addEventListener('resize', function() {
        if (!isMobile()) {
            // Si ya no es móvil, remover todas las clases
            const footerContenedorOrden = document.querySelector('.Footer_Contenedor_Orden');
            const footer = document.querySelector('footer');
            
            footerContenedorOrden?.classList.remove('hidden');
            footer?.classList.remove('footer-collapsed');
        }
    });
});

