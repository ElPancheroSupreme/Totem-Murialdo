// Sistema Unificado de Notificaciones - Buffet Murialdino
class NotificacionesManager {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        this.crearContainer();
    }

    crearContainer() {
        // Crear contenedor de alertas si no existe
        let container = document.getElementById('alertas-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'alertas-container';
            container.className = 'alertas-container';
            document.body.appendChild(container);
        }
        this.container = container;
    }

    // Método principal para mostrar notificaciones
    mostrar(mensaje, tipo = 'info', duracion = 5000) {
        // Validar tipo
        const tiposValidos = ['success', 'error', 'info', 'warning'];
        if (!tiposValidos.includes(tipo)) {
            tipo = 'info';
        }

        // Crear alerta
        const alerta = document.createElement('div');
        alerta.className = `alerta alerta-${tipo}`;
        
        // Obtener icono según el tipo
        const icono = this.obtenerIcono(tipo);
        
        // Crear contenido de la alerta
        alerta.innerHTML = `
            <div class="alerta-content">
                <div class="alerta-icon">${icono}</div>
                <div class="alerta-message">${mensaje}</div>
                <button class="alerta-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        // Aplicar estilos según el tipo
        this.aplicarEstilos(alerta, tipo);

        // Agregar al contenedor
        this.container.appendChild(alerta);

        // Auto-remover después de la duración especificada
        if (duracion > 0) {
            setTimeout(() => {
                if (alerta.parentElement) {
                    this.removerAlerta(alerta);
                }
            }, duracion);
        }

        return alerta;
    }

    // Métodos específicos para cada tipo de notificación
    exito(mensaje, duracion = 5000) {
        return this.mostrar(mensaje, 'success', duracion);
    }

    error(mensaje, duracion = 7000) {
        return this.mostrar(mensaje, 'error', duracion);
    }

    info(mensaje, duracion = 5000) {
        return this.mostrar(mensaje, 'info', duracion);
    }

    advertencia(mensaje, duracion = 6000) {
        return this.mostrar(mensaje, 'warning', duracion);
    }

    // Método para compatibilidad con código existente
    mostrarAlerta(mensaje, tipo = 'info', duracion = 5000) {
        return this.mostrar(mensaje, tipo, duracion);
    }

    mostrarExito(mensaje, duracion = 5000) {
        return this.exito(mensaje, duracion);
    }

    mostrarError(mensaje, duracion = 7000) {
        return this.error(mensaje, duracion);
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        return this.mostrar(mensaje, tipo, duracion);
    }

    // Obtener icono según el tipo
    obtenerIcono(tipo) {
        const iconos = {
            success: '✅',
            error: '❌',
            info: 'ℹ️',
            warning: '⚠️'
        };
        return iconos[tipo] || 'ℹ️';
    }

    // Aplicar estilos según el tipo
    aplicarEstilos(alerta, tipo) {
        // Detectar si estamos en modo oscuro
        const isDarkMode = document.body.getAttribute('data-theme') === 'dark';
        
        const estilos = {
            success: {
                backgroundColor: isDarkMode ? '#065f46' : '#d4edda',
                borderColor: isDarkMode ? '#059669' : '#c3e6cb',
                color: isDarkMode ? '#a7f3d0' : '#155724'
            },
            error: {
                backgroundColor: isDarkMode ? '#7f1d1d' : '#f8d7da',
                borderColor: isDarkMode ? '#dc2626' : '#f5c6cb',
                color: isDarkMode ? '#fecaca' : '#721c24'
            },
            info: {
                backgroundColor: isDarkMode ? '#1e3a8a' : '#d1ecf1',
                borderColor: isDarkMode ? '#3b82f6' : '#bee5eb',
                color: isDarkMode ? '#bfdbfe' : '#0c5460'
            },
            warning: {
                backgroundColor: isDarkMode ? '#92400e' : '#fff3cd',
                borderColor: isDarkMode ? '#f59e0b' : '#ffeaa7',
                color: isDarkMode ? '#fde68a' : '#856404'
            }
        };

        const estilo = estilos[tipo] || estilos.info;
        Object.assign(alerta.style, {
            backgroundColor: estilo.backgroundColor,
            borderColor: estilo.borderColor,
            color: estilo.color,
            border: `1px solid ${estilo.borderColor}`,
            borderRadius: '8px',
            padding: '12px 16px',
            marginBottom: '12px',
            boxShadow: isDarkMode ? '0 2px 8px rgba(0, 0, 0, 0.3)' : '0 2px 8px rgba(0, 0, 0, 0.1)',
            position: 'relative',
            animation: 'slideInRight 0.3s ease-out',
            fontSize: '14px',
            fontWeight: '500',
            maxWidth: '400px',
            wordWrap: 'break-word'
        });

        // Estilos para el contenido
        const content = alerta.querySelector('.alerta-content');
        if (content) {
            Object.assign(content.style, {
                display: 'flex',
                alignItems: 'center',
                gap: '10px'
            });
        }

        // Estilos para el icono
        const icon = alerta.querySelector('.alerta-icon');
        if (icon) {
            Object.assign(icon.style, {
                fontSize: '18px',
                flexShrink: '0'
            });
        }

        // Estilos para el mensaje
        const message = alerta.querySelector('.alerta-message');
        if (message) {
            Object.assign(message.style, {
                flex: '1',
                lineHeight: '1.4',
                color: estilo.color
            });
        }

        // Estilos para el botón de cerrar
        const closeBtn = alerta.querySelector('.alerta-close');
        if (closeBtn) {
            Object.assign(closeBtn.style, {
                background: 'none',
                border: 'none',
                fontSize: '18px',
                cursor: 'pointer',
                padding: '0',
                marginLeft: '10px',
                opacity: '0.7',
                transition: 'opacity 0.2s',
                color: estilo.color
            });

            closeBtn.addEventListener('mouseenter', () => {
                closeBtn.style.opacity = '1';
            });

            closeBtn.addEventListener('mouseleave', () => {
                closeBtn.style.opacity = '0.7';
            });
        }
    }

    // Remover alerta con animación
    removerAlerta(alerta) {
        if (!alerta || !alerta.parentElement) return;

        alerta.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (alerta.parentElement) {
                alerta.remove();
            }
        }, 300);
    }

    // Limpiar todas las notificaciones
    limpiarTodas() {
        const alertas = this.container.querySelectorAll('.alerta');
        alertas.forEach(alerta => {
            this.removerAlerta(alerta);
        });
    }

    // Actualizar estilos cuando cambie el tema
    actualizarTema() {
        const alertas = this.container.querySelectorAll('.alerta');
        alertas.forEach(alerta => {
            // Determinar el tipo de alerta basado en sus clases
            let tipo = 'info';
            if (alerta.classList.contains('alerta-success')) tipo = 'success';
            else if (alerta.classList.contains('alerta-error')) tipo = 'error';
            else if (alerta.classList.contains('alerta-warning')) tipo = 'warning';
            
            // Volver a aplicar estilos con el nuevo tema
            this.aplicarEstilos(alerta, tipo);
        });
    }

    // Métodos para testing (solo para desarrollo)
    probarExito() {
        this.exito('¡Operación completada exitosamente!');
    }

    probarError() {
        this.error('Error: No se pudo completar la operación. Intenta nuevamente.');
    }

    probarInfo() {
        this.info('Información: Esta es una notificación informativa.');
    }

    probarAdvertencia() {
        this.advertencia('Advertencia: Verifica los datos antes de continuar.');
    }

    probarTodas() {
        setTimeout(() => this.probarExito(), 0);
        setTimeout(() => this.probarInfo(), 500);
        setTimeout(() => this.probarAdvertencia(), 1000);
        setTimeout(() => this.probarError(), 1500);
    }

    probarModoOscuro() {
        // Forzar actualización del tema
        this.actualizarTema();
        
        // Mostrar todas las notificaciones con mensajes específicos para modo oscuro
        setTimeout(() => this.exito('🌙 Notificación de ÉXITO en modo oscuro - ¿Se ve correctamente?'), 0);
        setTimeout(() => this.info('🌙 Notificación de INFO en modo oscuro - ¿Se lee bien el texto?'), 500);
        setTimeout(() => this.advertencia('🌙 Notificación de ADVERTENCIA en modo oscuro - ¿Contraste adecuado?'), 1000);
        setTimeout(() => this.error('🌙 Notificación de ERROR en modo oscuro - ¿Colores visibles?'), 1500);
        setTimeout(() => this.info('💡 Cambia entre modo claro y oscuro para verificar la legibilidad en ambos temas.'), 2000);
    }
}

// Crear instancia global
window.notificacionesManager = new NotificacionesManager();

// Agregar estilos CSS para las animaciones
const estilosCSS = `
<style>
/* Estilos para el contenedor de alertas */
.alertas-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
    pointer-events: none;
}

.alertas-container .alerta {
    pointer-events: all;
    margin-bottom: 12px;
}

/* Animaciones */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Tema oscuro - Los estilos se manejan dinámicamente en JavaScript */
[data-theme="dark"] .alertas-container {
    /* Los colores se aplican dinámicamente según el tipo */
}

/* Responsive */
@media (max-width: 768px) {
    .alertas-container {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
    
    .alerta {
        font-size: 13px !important;
        padding: 10px 12px !important;
    }
}
</style>
`;

// Insertar estilos en el head
if (!document.getElementById('notificaciones-styles')) {
    const head = document.head || document.getElementsByTagName('head')[0];
    const style = document.createElement('style');
    style.id = 'notificaciones-styles';
    style.innerHTML = estilosCSS.replace('<style>', '').replace('</style>', '');
    head.appendChild(style);
}

// Exponer métodos globales para compatibilidad
window.mostrarAlerta = (mensaje, tipo, duracion) => window.notificacionesManager.mostrar(mensaje, tipo, duracion);
window.mostrarExito = (mensaje, duracion) => window.notificacionesManager.exito(mensaje, duracion);
window.mostrarError = (mensaje, duracion) => window.notificacionesManager.error(mensaje, duracion);
window.mostrarInfo = (mensaje, duracion) => window.notificacionesManager.info(mensaje, duracion);
window.mostrarAdvertencia = (mensaje, duracion) => window.notificacionesManager.advertencia(mensaje, duracion);

// Observer para detectar cambios de tema
const observarCambiosTema = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
            if (window.notificacionesManager) {
                window.notificacionesManager.actualizarTema();
            }
        }
    });
});

// Iniciar observación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    observarCambiosTema.observe(document.body, {
        attributes: true,
        attributeFilter: ['data-theme']
    });
});
