// Sistema completo de filtros para la sección de pedidos
class PedidosManager {
    constructor() {
        this.pedidosOriginales = [];
        this.pedidosFiltrados = [];
        this.init();
    }

    init() {
        this.reemplazarToolbar();
        this.bindEvents();
        // Cargar estadísticas inicial
        this.cargarEstadisticasCards();
        // Actualizar estadísticas cada 30 segundos
        this.intervaloEstadisticas = setInterval(() => {
            this.cargarEstadisticasCards();
        }, 30000);
    }

    async cargarEstadisticasCards() {
        try {
            // Obtener todos los pedidos para hacer cálculos precisos
            const resPedidos = await fetch('/Totem_Murialdo/backend/admin/api/api_pedidos.php');
            const dataPedidos = await resPedidos.json();

            if (dataPedidos.success && Array.isArray(dataPedidos.pedidos)) {
                const todosLosPedidos = dataPedidos.pedidos;
                
                // Filtrar pedidos de hoy
                const pedidosHoy = todosLosPedidos.filter(pedido => {
                    const fechaPedido = new Date(pedido.creado_en);
                    const hoy = new Date();
                    return fechaPedido.toDateString() === hoy.toDateString();
                });

                // Filtrar pedidos de ayer para comparación
                const pedidosAyer = todosLosPedidos.filter(pedido => {
                    const fechaPedido = new Date(pedido.creado_en);
                    const ayer = new Date();
                    ayer.setDate(ayer.getDate() - 1);
                    return fechaPedido.toDateString() === ayer.toDateString();
                });

                // Calcular estadísticas
                const totalHoy = pedidosHoy.length;
                const totalAyer = pedidosAyer.length;

                // Contar pedidos en preparación (estados que indican que están siendo preparados)
                const enPreparacion = pedidosHoy.filter(p => {
                    const estado = p.estado.toLowerCase();
                    return estado.includes('preparacion') || 
                           estado.includes('preparando') ||
                           estado.includes('pendiente') ||
                           estado === 'pendiente' ||
                           estado === 'en_preparacion' ||
                           estado === 'preparacion';
                }).length;

                // Contar pedidos entregados/completados
                const entregados = pedidosHoy.filter(p => {
                    const estado = p.estado.toLowerCase();
                    return estado.includes('entregado') || 
                           estado.includes('completado') ||
                           estado.includes('listo') ||
                           estado.includes('pago') ||
                           estado === 'entregado' ||
                           estado === 'completado' ||
                           estado === 'listo';
                }).length;

                // Actualizar Card Azul - Pedidos Hoy
                const totalPedidosElement = document.getElementById('total-pedidos-hoy');
                if (totalPedidosElement) totalPedidosElement.textContent = totalHoy;

                // Calcular y mostrar comparativo con ayer
                const porcentajeCambio = totalAyer > 0 ? Math.round(((totalHoy - totalAyer) / totalAyer) * 100) : 0;
                const signo = porcentajeCambio >= 0 ? '+' : '';
                const comparativoTexto = totalAyer === 0 && totalHoy > 0 ? 'Nuevos pedidos hoy' : 
                                        totalAyer === 0 && totalHoy === 0 ? 'Sin pedidos' :
                                        `${signo}${porcentajeCambio}% vs ayer`;
                const comparativoElement = document.getElementById('comparativo-pedidos');
                if (comparativoElement) comparativoElement.textContent = comparativoTexto;

                // Actualizar Card Amarilla - En Preparación
                const enPreparacionElement = document.getElementById('total-en-preparacion');
                if (enPreparacionElement) enPreparacionElement.textContent = enPreparacion;

                // Calcular tiempo promedio estimado (simulado inteligentemente)
                let tiempoTexto;
                if (enPreparacion === 0) {
                    tiempoTexto = 'No hay pedidos en preparación';
                } else if (enPreparacion <= 3) {
                    tiempoTexto = 'Tiempo promedio: 5-8 min';
                } else if (enPreparacion <= 6) {
                    tiempoTexto = 'Tiempo promedio: 8-12 min';
                } else {
                    tiempoTexto = 'Tiempo promedio: 12-15 min';
                }
                const tiempoElement = document.getElementById('tiempo-promedio');
                if (tiempoElement) tiempoElement.textContent = tiempoTexto;

                // Actualizar Card Verde - Entregados
                const entregadosElement = document.getElementById('total-entregados');
                if (entregadosElement) entregadosElement.textContent = entregados;

                // Calcular porcentaje de entregados
                const porcentajeEntregados = totalHoy > 0 ? Math.round((entregados / totalHoy) * 100) : 0;
                const porcentajeTexto = totalHoy === 0 ? 'Sin pedidos hoy' : `${porcentajeEntregados}% del total`;
                const porcentajeElement = document.getElementById('porcentaje-entregados');
                if (porcentajeElement) porcentajeElement.textContent = porcentajeTexto;

            } else {
                // Error al obtener pedidos, mostrar valores por defecto silenciosamente
                console.warn('No se pudieron obtener los pedidos para estadísticas');
            }

        } catch (error) {
            console.error('Error al cargar estadísticas de cards:', error);
            // No hacer nada visible para el usuario, mantener valores por defecto
        }
    }

    reemplazarToolbar() {
        const toolbar = document.querySelector('.pedidos-toolbar');
        if (!toolbar) return;

        // Limpiar toolbar existente
        toolbar.innerHTML = '';

        // Crear input de búsqueda por número de pedido
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'input-search';
        searchInput.id = 'search-numero-pedido';
        searchInput.placeholder = 'Buscar por número o ID...';
        toolbar.appendChild(searchInput);

        // Crear select de estado
        const selectEstado = document.createElement('select');
        selectEstado.className = 'filter-select';
        selectEstado.id = 'filter-estado';
        selectEstado.innerHTML = `
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendiente</option>
            <option value="preparacion">En preparación</option>
            <option value="listo">Listo</option>
            <option value="entregado">Entregado</option>
            <option value="cancelado">Cancelado</option>
        `;
        toolbar.appendChild(selectEstado);

        // Crear select de método de pago
        const selectMetodo = document.createElement('select');
        selectMetodo.className = 'filter-select';
        selectMetodo.id = 'filter-metodo';
        selectMetodo.innerHTML = `
            <option value="">Todos los métodos</option>
            <option value="efectivo">Efectivo</option>
            <option value="virtual">Virtual</option>
        `;
        toolbar.appendChild(selectMetodo);

        // Crear select de punto de venta (kiosco/buffet)
        const selectPuntoVenta = document.createElement('select');
        selectPuntoVenta.className = 'filter-select';
        selectPuntoVenta.id = 'filter-punto-venta';
        selectPuntoVenta.innerHTML = `
            <option value="">Todos los puntos</option>
            <option value="kiosco">Kiosco</option>
            <option value="buffet">Buffet</option>
        `;
        toolbar.appendChild(selectPuntoVenta);

        // Crear input de fecha
        const inputFecha = document.createElement('input');
        inputFecha.type = 'date';
        inputFecha.className = 'filter-select';
        inputFecha.id = 'filter-fecha';
        inputFecha.style.minWidth = '160px';
        toolbar.appendChild(inputFecha);

        // Crear select de orden unificado
        const selectOrden = document.createElement('select');
        selectOrden.className = 'filter-select';
        selectOrden.id = 'filter-orden';
        selectOrden.innerHTML = `
            <option value="fecha-desc">Fecha (más reciente)</option>
            <option value="fecha-asc">Fecha (más antigua)</option>
            <option value="numero-desc">Número (descendente)</option>
            <option value="numero-asc">Número (ascendente)</option>
            <option value="precio-desc">Precio (mayor a menor)</option>
            <option value="precio-asc">Precio (menor a mayor)</option>
        `;
        toolbar.appendChild(selectOrden);
    }

    bindEvents() {
        // Event listeners para todos los filtros
        const searchInput = document.getElementById('search-numero-pedido');
        const filterEstado = document.getElementById('filter-estado');
        const filterMetodo = document.getElementById('filter-metodo');
        const filterPuntoVenta = document.getElementById('filter-punto-venta');
        const filterFecha = document.getElementById('filter-fecha');
        const filterOrden = document.getElementById('filter-orden');

        if (searchInput) searchInput.addEventListener('input', () => this.aplicarFiltros());
        if (filterEstado) filterEstado.addEventListener('change', () => this.aplicarFiltros());
        if (filterMetodo) filterMetodo.addEventListener('change', () => this.aplicarFiltros());
        if (filterPuntoVenta) filterPuntoVenta.addEventListener('change', () => this.aplicarFiltros());
        if (filterFecha) filterFecha.addEventListener('change', () => this.aplicarFiltros());
        if (filterOrden) filterOrden.addEventListener('change', () => this.aplicarFiltros());
    }

    async cargarPedidos() {
        const tbody = document.querySelector('.pedidos-table tbody');
        const listCount = document.querySelector('.pedidos-lista-count');
        
        if (tbody) tbody.innerHTML = `
            <tr>
                <td style="text-align: center; padding: 2rem;" colspan="8">Cargando...</td>
            </tr>
        `;

        try {
            const res = await fetch('/Totem_Murialdo/backend/admin/api/api_pedidos.php');
            const data = await res.json();

            if (data.success && Array.isArray(data.pedidos)) {
                this.pedidosOriginales = data.pedidos;
                this.pedidosFiltrados = [...this.pedidosOriginales];
                this.aplicarFiltros();
                
                // Actualizar estadísticas de las cards después de cargar los pedidos
                this.cargarEstadisticasCards();
            } else {
                if (tbody) tbody.innerHTML = `
                    <tr>
                        <td style="text-align: center; padding: 2rem;" colspan="8">No hay pedidos registrados</td>
                    </tr>
                `;
                if (listCount) listCount.textContent = '0 pedidos encontrados';
            }
        } catch (e) {
            console.error('Error al cargar pedidos:', e);
            if (tbody) tbody.innerHTML = `
                <tr>
                    <td style="text-align: center; padding: 2rem;" colspan="8">Error de conexión.</td>
                </tr>
            `;
            if (listCount) listCount.textContent = '0 pedidos encontrados';
        }
    }

    aplicarFiltros() {
        const searchTerm = document.getElementById('search-numero-pedido')?.value?.toLowerCase() || '';
        const estadoFilter = document.getElementById('filter-estado')?.value || '';
        const metodoFilter = document.getElementById('filter-metodo')?.value || '';
        const puntoVentaFilter = document.getElementById('filter-punto-venta')?.value || '';
        const fechaFilter = document.getElementById('filter-fecha')?.value || '';
        const ordenFilter = document.getElementById('filter-orden')?.value || 'fecha-desc';

        // Filtrar pedidos
        this.pedidosFiltrados = this.pedidosOriginales.filter(pedido => {
            // Filtro por número de pedido (buscar en el número del pedido)
            if (searchTerm) {
                const numeroStr = pedido.numero_pedido.toString().toLowerCase();
                const idStr = pedido.id_pedido.toString().toLowerCase();
                if (!numeroStr.includes(searchTerm) && !idStr.includes(searchTerm)) {
                    return false;
                }
            }

            // Filtro por estado
            if (estadoFilter && !pedido.estado.toLowerCase().includes(estadoFilter.toLowerCase())) {
                return false;
            }

            // Filtro por método de pago
            if (metodoFilter && !pedido.metodo_pago.toLowerCase().includes(metodoFilter.toLowerCase())) {
                return false;
            }

            // Filtro por punto de venta
            if (puntoVentaFilter) {
                const puntoVenta = (pedido.punto_venta || '').toLowerCase();
                if (!puntoVenta.includes(puntoVentaFilter.toLowerCase())) {
                    return false;
                }
            }

            // Filtro por fecha
            if (fechaFilter && pedido.creado_en.slice(0, 10) !== fechaFilter) {
                return false;
            }

            return true;
        });

        // Ordenar pedidos
        this.pedidosFiltrados.sort((a, b) => {
            switch (ordenFilter) {
                case 'fecha-asc':
                    return a.creado_en.localeCompare(b.creado_en);
                case 'fecha-desc':
                    return b.creado_en.localeCompare(a.creado_en);
                case 'numero-asc':
                    const numA = this.extraerNumero(a.numero_pedido);
                    const numB = this.extraerNumero(b.numero_pedido);
                    return numA - numB;
                case 'numero-desc':
                    const numA2 = this.extraerNumero(a.numero_pedido);
                    const numB2 = this.extraerNumero(b.numero_pedido);
                    return numB2 - numA2;
                case 'precio-asc':
                    const precioA = parseFloat(a.monto_total) || 0;
                    const precioB = parseFloat(b.monto_total) || 0;
                    return precioA - precioB;
                case 'precio-desc':
                    const precioA2 = parseFloat(a.monto_total) || 0;
                    const precioB2 = parseFloat(b.monto_total) || 0;
                    return precioB2 - precioA2;
                default:
                    return b.creado_en.localeCompare(a.creado_en);
            }
        });

        this.renderPedidos();
    }

    extraerNumero(numeroPedido) {
        // Extraer números del string, maneja formatos como K-001, K001-005, etc.
        const match = numeroPedido.toString().match(/\d+/g);
        if (match && match.length > 0) {
            // Si hay múltiples números, usar el último (ej: K001-005 -> 005)
            return parseInt(match[match.length - 1]);
        }
        return 0; // Si no hay números, devolver 0
    }

    renderPedidos() {
        const tbody = document.querySelector('.pedidos-table tbody');
        const listCount = document.querySelector('.pedidos-lista-count');
        
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!this.pedidosFiltrados || this.pedidosFiltrados.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td style="text-align: center; padding: 2rem; color: #666;" colspan="8">No hay pedidos que coincidan con los filtros</td>
                </tr>
            `;
            if (listCount) listCount.textContent = '0 pedidos encontrados';
            return;
        }

        this.pedidosFiltrados.forEach(pedido => {
            const tr = document.createElement('tr');
            const metodoPago = this.formatearMetodoPago(pedido.metodo_pago);
            const puntoVenta = this.formatearPuntoVenta(pedido.punto_venta);
            tr.innerHTML = `
                <td>#${pedido.numero_pedido}</td>
                <td>${pedido.cantidad_items || 0}</td>
                <td>$${Number(pedido.monto_total).toLocaleString()}</td>
                <td>${this.generarEstadoBadge(pedido.estado)}</td>
                <td>${metodoPago}</td>
                <td>${puntoVenta}</td>
                <td>${pedido.creado_en ? pedido.creado_en.substring(11, 16) : '-'}</td>
                <td><button class="btn-icon btn-abrir-overlay" data-id="${pedido.id_pedido}">👁️</button></td>
            `;
            tbody.appendChild(tr);
        });

        if (listCount) {
            listCount.textContent = `${this.pedidosFiltrados.length} pedido${this.pedidosFiltrados.length !== 1 ? 's' : ''} encontrados`;
        }

        // Agregar eventos a los botones de detalle
        tbody.querySelectorAll('.btn-abrir-overlay').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (typeof mostrarDetallePedido === 'function') {
                    mostrarDetallePedido(btn.getAttribute('data-id'));
                }
            });
        });
    }

    generarEstadoBadge(estado) {
        const estadoLower = estado.toLowerCase();
        let background, color, text;

        if (estadoLower === 'pendiente' || estadoLower === 'esperando') {
            background = '#fff3cd';
            color = '#856404';
            text = 'Pendiente';
        } else if (estadoLower === 'preparacion' || estadoLower === 'preparando') {
            background = '#d1ecf1';
            color = '#0c5460';
            text = 'En preparación';
        } else if (estadoLower === 'preparado' || estadoLower === 'listo') {
            background = '#d4edda';
            color = '#155724';
            text = 'Listo';
        } else if (estadoLower === 'entregado' || estadoLower === 'completado' || estadoLower === 'pago') {
            background = '#e6f9ed';
            color = '#1a7f37';
            text = 'Entregado';
        } else if (estadoLower === 'cancelado') {
            background = '#f8d7da';
            color = '#721c24';
            text = 'Cancelado';
        } else {
            background = '#f8f9fa';
            color = '#6c757d';
            text = estado;
        }

        return `<span style="display:inline-flex;align-items:center;background:${background};color:${color};font-weight:600;padding:4px 14px;border-radius:16px;font-size:15px;">${text}</span>`;
    }

    formatearMetodoPago(metodo) {
        if (!metodo) return '<span style="color:#999;">N/A</span>';
        
        const metodoLower = metodo.toLowerCase();
        let icono = '';
        let color = '#6b7280';
        let nombre = metodo;

        if (metodoLower.includes('efectivo')) {
            icono = '💵';
            color = '#22c55e';
            nombre = 'Efectivo';
        } else if (metodoLower.includes('virtual')) {
            icono = '💳';
            color = '#3b82f6';
            nombre = 'Virtual';
        }
        return `<span style="color:${color};font-weight:500;">${icono} ${nombre}</span>`;
    }

    destruir() {
        if (this.intervaloEstadisticas) {
            clearInterval(this.intervaloEstadisticas);
            this.intervaloEstadisticas = null;
        }
    }

    formatearPuntoVenta(puntoVenta) {
        if (!puntoVenta) return '<span style="color:#999;">N/A</span>';
        
        const puntoLower = puntoVenta.toLowerCase();
        let icono = '';
        let color = '#6b7280';
        let nombre = puntoVenta;

        if (puntoLower.includes('kiosco')) {
            icono = '🏪';
            color = '#f59e0b';
            nombre = 'Kiosco';
        } else if (puntoLower.includes('buffet')) {
            icono = '🍽️';
            color = '#10b981';
            nombre = 'Buffet';
        }

        return `<span style="color:${color};font-weight:500;">${icono} ${nombre}</span>`;
    }
}

// Instancia global del manager
window.pedidosManager = null;

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar solo si estamos en la sección de pedidos
    const initPedidosManager = () => {
        // Limpiar instancia anterior si existe
        if (window.pedidosManager) {
            window.pedidosManager.destruir();
            window.pedidosManager = null;
        }
        
        if (document.getElementById('pedidos-section')) {
            window.pedidosManager = new PedidosManager();
        }
    };

    // Inicializar inmediatamente si la sección ya existe
    initPedidosManager();

    // También escuchar clicks en el menú de pedidos
    const pedidosMenuItem = document.querySelector('#sidebar-menu li[data-section="pedidos"]');
    if (pedidosMenuItem) {
        pedidosMenuItem.addEventListener('click', () => {
            setTimeout(() => {
                initPedidosManager();
                if (window.pedidosManager) {
                    window.pedidosManager.cargarPedidos();
                }
            }, 150);
        });
    }

    // Limpiar cuando se cambie a otra sección
    const otrosMenuItems = document.querySelectorAll('#sidebar-menu li:not([data-section="pedidos"])');
    otrosMenuItems.forEach(item => {
        item.addEventListener('click', () => {
            if (window.pedidosManager) {
                window.pedidosManager.destruir();
                window.pedidosManager = null;
            }
        });
    });
});
