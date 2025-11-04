/**
 * Sistema de Detección de Inactividad para Tótem Murialdo
 * Monitorea la actividad del usuario y redirige a pantalla de anuncio después del tiempo configurado
 */

class InactivityDetector {
    constructor(options = {}) {
        this.timeoutDuration = options.timeout || 300000; // 5 minutos por defecto
        this.redirectUrl = options.redirectUrl || '/Totem_Murialdo/frontend/views/anuncio.html';
        this.excludePages = options.excludePages || ['anuncio.html', 'ConfigDash.html'];
        
        this.timeoutId = null;
        this.lastActivity = Date.now();
        this.startTime = Date.now();
        this.logInterval = null;
        
        // Verificar si debe ejecutarse en esta página
        if (this.shouldRunOnCurrentPage()) {
            this.init();
        }
    }

    shouldRunOnCurrentPage() {
        const currentPage = window.location.pathname.split('/').pop();
        return !this.excludePages.some(page => currentPage.includes(page));
    }

    async loadConfiguration() {
        try {

            const response = await fetch('/Totem_Murialdo/backend/admin/api/configuracion_horarios.php');
            const data = await response.json();
            
            if (data && data.success && data.config && data.config.tiempo_inactividad) {
                const oldTimeout = this.timeoutDuration;
                this.timeoutDuration = data.config.tiempo_inactividad * 1000; // Convertir a milisegundos
                
            } else {

            }
        } catch (error) {

        }
    }

    async init() {
        // Cargar configuración desde el servidor
        await this.loadConfiguration();
        
        // Configurar eventos de actividad
        this.setupActivityListeners();
        
        // Iniciar el temporizador
        this.resetTimer();
        
        // Iniciar logging periódico
        this.startPeriodicLogging();
    }

    setupActivityListeners() {
        const events = [
            'mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 
            'click', 'change', 'focus', 'blur', 'resize'
        ];

        events.forEach(event => {
            document.addEventListener(event, () => this.onActivity(), true);
        });

        // Listener especial para detectar interacción en elementos específicos
        document.addEventListener('input', () => this.onActivity(), true);
    }

    onActivity() {
        const now = Date.now();
        const timeSinceLastActivity = now - this.lastActivity;
        
        
        this.lastActivity = now;
        this.resetTimer();
    }

    startPeriodicLogging() {
        // Log cada 5 segundos para mostrar el progreso
        this.logInterval = setInterval(() => {
            const now = Date.now();
            const timeElapsed = (now - this.lastActivity) / 1000;
            const timeRemaining = (this.timeoutDuration / 1000) - timeElapsed;
            
            if (timeRemaining > 0) {
                
                // Avisos especiales en los últimos segundos
                if (timeRemaining <= 10) {
                    console.warn(`🚨 ¡Redirección en ${timeRemaining.toFixed(1)} segundos!`);
                } else if (timeRemaining <= 30) {
                    console.log(`⚠️  Advertencia: ${timeRemaining.toFixed(1)} segundos restantes`);
                }
            }
        }, 5000); // Cada 5 segundos
    }

    stopPeriodicLogging() {
        if (this.logInterval) {
            clearInterval(this.logInterval);
            this.logInterval = null;
        }
    }

    resetTimer() {
        // Limpiar temporizador existente
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
        }

        // Reiniciar logging
        this.stopPeriodicLogging();
        this.startPeriodicLogging();

        const resetTime = new Date().toLocaleTimeString();
        console.log(`🔄 Timer reiniciado a las ${resetTime} - Próxima redirección en ${this.timeoutDuration / 1000} segundos`);

        // Configurar redirección directa
        this.timeoutId = setTimeout(() => {
            this.redirectToAnnouncement();
        }, this.timeoutDuration);
    }

    redirectToAnnouncement() {
        // Limpiar temporizador
        if (this.timeoutId) clearTimeout(this.timeoutId);
        this.stopPeriodicLogging();
        
        const finalTime = new Date().toLocaleTimeString();
        const totalTimeElapsed = (Date.now() - this.lastActivity) / 1000;
        
        console.log(`🚀 REDIRECCIÓN ACTIVADA:`);
        console.log(`⏰ Hora: ${finalTime}`);
        console.log(`⏱️  Tiempo total de inactividad: ${totalTimeElapsed.toFixed(1)} segundos`);
        console.log(`🔄 Redirigiendo a: ${this.redirectUrl}`);
        
        // Efecto de transición
        document.body.style.transition = 'opacity 0.5s ease-out';
        document.body.style.opacity = '0';
        
        setTimeout(() => {
            console.log(`✅ Ejecutando redirección...`);
            window.location.href = this.redirectUrl;
        }, 500);
    }

    // Método público para pausar la detección (útil para procesos importantes)
    pause() {
        if (this.timeoutId) clearTimeout(this.timeoutId);
        this.stopPeriodicLogging();
        console.log('⏸️  Sistema de inactividad pausado');
    }

    // Método público para reanudar la detección
    resume() {
        this.resetTimer();
        console.log('▶️  Sistema de inactividad reanudado');
    }

    // Método público para destruir el detector
    destroy() {
        this.pause();
        console.log('🛑 Sistema de inactividad desactivado');
    }
}

// Auto-inicialización del sistema
document.addEventListener('DOMContentLoaded', () => {
    // Solo inicializar si no estamos en páginas excluidas
    const currentPage = window.location.pathname.split('/').pop();
    const excludePages = ['anuncio.html', 'ConfigDash.html'];
    
    if (!excludePages.some(page => currentPage.includes(page))) {
        window.inactivityDetector = new InactivityDetector({
            timeout: 300000, // 5 minutos por defecto
            redirectUrl: '/Totem_Murialdo/frontend/views/anuncio.html'
        });
    }
});

// Exportar para uso en otros scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = InactivityDetector;
}
