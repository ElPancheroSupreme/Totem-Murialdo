// Cargar carrito desde localStorage o iniciar vacío
let carrito = JSON.parse(localStorage.getItem('carrito')) || [];

const contenedorProductos = document.querySelector('.Scroll_Orden');
const totalDiv = document.querySelector('.Total');
let MAX_CANTIDAD = 10;

async function cargarMaxCantidadCarrito() {
    try {
        const res = await fetch('/Totem_Murialdo/backend/admin/api/configuracion_horarios.php');
        const data = await res.json();
        if (data.success && data.config && typeof data.config.max_unidades_carrito !== 'undefined') {
            MAX_CANTIDAD = parseInt(data.config.max_unidades_carrito) || 10;
        }
    } catch (e) {
        MAX_CANTIDAD = 10;
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    await cargarMaxCantidadCarrito();
    renderCarrito();
});

// Función para mostrar mensaje máximo
function mostrarMensajeMaximo(msg) {
    const mensajeDiv = document.getElementById('mensaje-maximo');
    if (mensajeDiv) {
        mensajeDiv.textContent = msg;
        mensajeDiv.style.display = 'block';
        mensajeDiv.style.background = '#ffe0e0';
        mensajeDiv.style.color = '#b30000';
        mensajeDiv.style.textAlign = 'center';
        mensajeDiv.style.padding = '10px';
        mensajeDiv.style.margin = '10px 0';
        mensajeDiv.style.borderRadius = '8px';
        mensajeDiv.style.fontWeight = 'bold';
        mensajeDiv.style.zIndex = '10';
        // Ocultar después de 2.5 segundos
        clearTimeout(mostrarMensajeMaximo.timeout);
        mostrarMensajeMaximo.timeout = setTimeout(() => {
            mensajeDiv.style.display = 'none';
        }, 1000);
    }
}

// Función para renderizar el carrito en pantalla
function renderCarrito() {
    contenedorProductos.innerHTML = '';
    // Agrupar productos iguales (nombre, id_producto, personalizaciones)
    const agrupados = [];
    carrito.forEach(item => {
        const key = item.id_producto + '|' + JSON.stringify(item.personalizaciones || []);
        const existente = agrupados.find(p => p.key === key);
        if (existente) {
            existente.cantidad += item.cantidad;
        } else {
            agrupados.push({ ...item, key });
        }
    });

    agrupados.forEach((item, index) => {
        const productoDiv = document.createElement('div');
        productoDiv.classList.add('Producto');
        // Mostrar personalizaciones como string separado por guiones
        let personalizacionesStr = '';
        if (Array.isArray(item.personalizaciones) && item.personalizaciones.length > 0) {
            personalizacionesStr = item.personalizaciones
                .map(pers => {
                    if (typeof pers === 'string') return pers;
                    let texto = pers.nombre_opcion || '';
                    const precioExtra = Number(pers.precio_extra) || 0;
                    if (precioExtra > 0) {
                        texto += ` (+$${precioExtra})`;
                    }
                    return texto;
                })
                .join(' - ');
        } else if (item.personalizacion) {
            personalizacionesStr = item.personalizacion;
        }
        // Aseguramos que el valor es un número
        const esPersonalizable = Number(item.es_personalizable) === 1;
        productoDiv.innerHTML = `
            <div class="Info">
                <div class="Imagen_Producto">
                    <img src="${item.imagen || ''}" alt="Imagen del producto">
                </div>
                <div class="Texto">
                    <p class="Nombre">${item.nombre}</p>
                    <p class="Personalizacion">${personalizacionesStr}</p>
                </div>
            </div>
            <div class="Acciones">
                <div class="Seccion_Cantidad">
                    <button class="Menos">-</button>
                    <div class="Cantidad">${item.cantidad}</div>
                    <button class="Mas">+</button>
                </div>
                <div class="Seccion_PrecioYEditar">
                    <div class="Precio">$${(
                        (Number(item.precio_unitario) + (Array.isArray(item.personalizaciones) ? item.personalizaciones.reduce((acc, p) => acc + (typeof p === 'object' ? (parseFloat(String(p.precio_extra).replace(',', '.')) || 0) : 0), 0) : 0))
                        * item.cantidad
                    ).toLocaleString('es-AR')}</div>
                    ${esPersonalizable ? `
                    <div class="Editar">
                        <div class="Imagen">
                            <img src="../assets/images/Iconos/editar.png" alt="">
                        </div>
                        <p>Editar</p>
                    </div>
                    ` : ''}
                </div>
                <div class="Eliminar">
                    <img src="../assets/images/Iconos/Eliminar.png" alt="">
                </div>
            </div>
        `;

        // Botón Menos
        productoDiv.querySelector('.Menos').addEventListener('click', () => {
            if (carrito[index].cantidad > 1) {
                carrito[index].cantidad--;
            } else {
                // Si es 1 y se presiona menos, eliminar producto
                carrito.splice(index, 1);
            }
            guardarYRenderizar();
        });

        // Botón Mas
        productoDiv.querySelector('.Mas').addEventListener('click', () => {
            if (carrito[index].cantidad < MAX_CANTIDAD) {
                carrito[index].cantidad++;
                guardarYRenderizar();
            } else {
                mostrarMensajeMaximo('Máximo 10 unidades por producto');
            }
        });

        // Botón Eliminar
        productoDiv.querySelector('.Eliminar').addEventListener('click', () => {
            carrito.splice(index, 1);
            guardarYRenderizar();
        });

        // Botón Editar: solo si es personalizable
        if (esPersonalizable) {
            const editarBtn = productoDiv.querySelector('.Editar');
            if (editarBtn) {
                editarBtn.addEventListener('click', () => {
                    abrirOverlayPersonalizacionCarrito(item, index);
                });
            }
        }
        contenedorProductos.appendChild(productoDiv);
    });

    actualizarTotal();

    // Ajustar tamaño de letra de .Nombre si ocupa más de 2 líneas
    document.querySelectorAll('.Nombre').forEach(nombreDiv => {
        const lineHeight = parseFloat(getComputedStyle(nombreDiv).lineHeight);
        const maxHeight = lineHeight * 2; // máximo 2 líneas

        // Resetear tamaño inicial (por si se renderiza de nuevo)
        nombreDiv.style.fontSize = '';

        while (nombreDiv.scrollHeight > maxHeight) {
            let currentSize = parseFloat(getComputedStyle(nombreDiv).fontSize);
            if (currentSize <= 10) break; // límite para no hacerla ilegible
            nombreDiv.style.fontSize = (currentSize - 0.5) + 'px';
        }
    });

}

// Calcula el total y lo muestra
function actualizarTotal() {
    const total = carrito.reduce((acc, item) => {
        const precioExtras = Array.isArray(item.personalizaciones)
            ? item.personalizaciones.reduce((sum, p) => sum + (typeof p === 'object' ? (parseFloat(String(p.precio_extra).replace(',', '.')) || 0) : 0), 0)
            : 0;
        return acc + ((Number(item.precio_unitario) + precioExtras) * Number(item.cantidad));
    }, 0);
    totalDiv.textContent = `Total $${Number(total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}`;
}

// Guarda el carrito en localStorage y vuelve a renderizar
// Overlay de personalización funcional igual a kiosco_dinamico.js
function abrirOverlayPersonalizacionCarrito(item, index) {
    const overlay = document.getElementById('Overlay_Personalizacion');
    if (!overlay) return;
    // --- Buscar opciones de personalización si no están ---
    if (!Array.isArray(item.opciones_personalizacion) || item.opciones_personalizacion.length === 0) {
        // Buscar en productos guardados en localStorage
        const productosPorCategoria = JSON.parse(localStorage.getItem('productosPorCategoria') || '{}');
        let prodOriginal = null;
        for (const cat in productosPorCategoria) {
            prodOriginal = (productosPorCategoria[cat] || []).find(p => p.id === item.id_producto);
            if (prodOriginal) break;
        }
        if (prodOriginal && Array.isArray(prodOriginal.opciones_personalizacion)) {
            item.opciones_personalizacion = prodOriginal.opciones_personalizacion;
        }
    }
    overlay.style.display = 'flex';
    // Info producto
    overlay.querySelector('.OP_InfoProducto').innerHTML = `
        <img src="${item.imagen || ''}" alt="" class="OP_ImagenProducto">
        <p class="OP_NombreProducto">${item.nombre}</p>
    `;
    // Precio
    const precioBase = Number(item.precio_unitario) || 0;
    const extras = Array.isArray(item.personalizaciones) ? item.personalizaciones.reduce((acc, p) => acc + (typeof p === 'object' ? (parseFloat(String(p.precio_extra).replace(',', '.')) || 0) : 0), 0) : 0;
    overlay.querySelector('.OP_ContenedorPrecio').innerHTML = `<p class="OP_Precio">$${(precioBase + extras).toLocaleString('es-AR')}</p>`;
    // Opciones de personalización
    const contenedor = overlay.querySelector('.OP_Contenedor_Personalizaciones');
    contenedor.innerHTML = '';
    if (Array.isArray(item.opciones_personalizacion) && item.opciones_personalizacion.length > 0) {
        item.opciones_personalizacion.forEach((opcion, idx) => {
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
            // Marcar si ya está seleccionada
            if (Array.isArray(item.personalizaciones) && item.personalizaciones.some(p => p.nombre_opcion === opcion.nombre_opcion)) {
                checkbox.checked = true;
            }
            div.appendChild(checkbox);
            // Nombre de la opción y precio extra
            const nombre = document.createElement('label');
            nombre.className = 'OP_Personalizacion_Nombre';
            nombre.textContent = opcion.nombre_opcion;
            nombre.setAttribute('for', checkbox.id);
            div.appendChild(nombre);
            // Precio extra a la derecha
            let precioExtra = Number(opcion.precio_extra) || 0;
            if (precioExtra > 0) {
                const precioSpan = document.createElement('span');
                precioSpan.textContent = `$${precioExtra.toLocaleString()}`;
                precioSpan.style.color = '#3A9A53';
                precioSpan.style.fontWeight = 'bold';
                precioSpan.style.marginLeft = 'auto';
                precioSpan.style.fontSize = '1.3em';
                div.appendChild(precioSpan);
            }
            contenedor.appendChild(div);
        });
    } else {
        const sinOpciones = document.createElement('p');
        sinOpciones.textContent = 'No hay opciones personalizables.';
        contenedor.appendChild(sinOpciones);
    }
    // Botón Guardar
    overlay.querySelector('#Agregar').onclick = function() {
        // Obtener personalizaciones elegidas como objetos
        const seleccionadas = [];
        contenedor.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
            const opcion = item.opciones_personalizacion.find(o => o.nombre_opcion === checkbox.value);
            if (opcion) {
                seleccionadas.push({
                    id_opcion: opcion.id_opcion,
                    nombre_opcion: opcion.nombre_opcion,
                    precio_extra: Number(opcion.precio_extra) || 0
                });
            }
        });
        // Si la personalización no cambió, solo actualiza
        const originalPersonalizaciones = JSON.stringify(carrito[index].personalizaciones || []);
        const nuevaPersonalizaciones = JSON.stringify(seleccionadas);
        if (originalPersonalizaciones === nuevaPersonalizaciones) {
            overlay.style.display = 'none';
            return;
        }
        // Si hay más de 1 cantidad, divide el grupo
        if (carrito[index].cantidad > 1) {
            // Resta 1 a la cantidad original
            carrito[index].cantidad--;
            // Crea un nuevo producto con la nueva personalización y cantidad 1
            const nuevoItem = { ...carrito[index], personalizaciones: seleccionadas, cantidad: 1 };
            // Elimina la key para que se reagrupe correctamente
            delete nuevoItem.key;
            carrito.push(nuevoItem);
        } else {
            // Si solo hay 1, simplemente actualiza
            carrito[index].personalizaciones = seleccionadas;
        }
        overlay.style.display = 'none';
        guardarYRenderizar();
    };
    // Botón Eliminar
    overlay.querySelector('#Eliminar').onclick = function() {
        carrito.splice(index, 1);
        overlay.style.display = 'none';
        guardarYRenderizar();
    };
}
function guardarYRenderizar() {
    localStorage.setItem('carrito', JSON.stringify(carrito));
    renderCarrito();
}

// Inicializa
// Eliminado, ahora se llama en el nuevo DOMContentLoaded async

// Función para obtener la página de productos según el punto de venta
function getPaginaProductos() {
    const puntoVentaNombre = localStorage.getItem('punto_venta_nombre');
    if (puntoVentaNombre && puntoVentaNombre.toLowerCase() === 'buffet') {
        return 'buffet.html';
    }
    return 'kiosco_dinamico.html'; // Por defecto Kiosco
}

// Redirigir al tocar el botón atrás
document.querySelector('.Boton_Atras').addEventListener('click', () => {
    window.location.href = getPaginaProductos();
});

// Botones Footer

document.getElementById('Cancelar_Orden').addEventListener('click', function () {
    localStorage.removeItem('carrito');
    window.location.href = getPaginaProductos();
});

document.getElementById('Continuar').addEventListener('click', function () {
    // Calcular el total actualizado antes de continuar
    const total = carrito.reduce((acc, item) => {
        const precioExtras = Array.isArray(item.personalizaciones)
            ? item.personalizaciones.reduce((sum, p) => sum + (typeof p === 'object' ? (parseFloat(String(p.precio_extra).replace(',', '.')) || 0) : 0), 0)
            : 0;
        return acc + ((Number(item.precio_unitario) + precioExtras) * Number(item.cantidad));
    }, 0);
    localStorage.setItem('carrito_total', total);
    window.location.href = 'metodo_pago.html';
});
