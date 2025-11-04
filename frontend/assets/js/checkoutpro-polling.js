/**
 * SISTEMA DE POLLING INTELIGENTE PARA CHECKOUTPRO
 * Optimizado para minimizar latencia y maximizar confiabilidad
 */

class CheckoutProPolling {
    constructor(external_reference) {
        this.external_reference = external_reference;
        this.polling_active = false;
        this.attempt_count = 0;
        this.max_attempts = 40; // 40 intentos máximo
        this.intervals = [
            3000,  // 3s (primeros 5 intentos)
            5000,  // 5s (siguientes 5 intentos)
            10000, // 10s (siguientes 10 intentos)
            15000, // 15s (siguientes 10 intentos)
            30000  // 30s (resto)
        ];
        this.success_callback = null;
        this.error_callback = null;
        this.status_callback = null;
        
        console.log(`🔄 CheckoutPro Polling iniciado para: ${external_reference}`);
    }
    
    // Callbacks para eventos
    onSuccess(callback) {
        this.success_callback = callback;
        return this;
    }
    
    onError(callback) {
        this.error_callback = callback;
        return this;
    }
    
    onStatus(callback) {
        this.status_callback = callback;
        return this;
    }
    
    // Obtener intervalo basado en el número de intento
    getInterval() {
        if (this.attempt_count < 5) return this.intervals[0];      // 3s primeros 5
        if (this.attempt_count < 10) return this.intervals[1];     // 5s siguientes 5
        if (this.attempt_count < 20) return this.intervals[2];     // 10s siguientes 10
        if (this.attempt_count < 30) return this.intervals[3];     // 15s siguientes 10
        return this.intervals[4];                                   // 30s resto
    }
    
    // Iniciar polling
    start() {
        if (this.polling_active) {
            console.warn('⚠️ Polling ya está activo');
            return;
        }
        
        this.polling_active = true;
        this.attempt_count = 0;
        console.log(`🚀 Iniciando polling robusto para ${this.external_reference}`);
        
        // Primera verificación inmediata
        this.checkPayment();
    }
    
    // Detener polling
    stop() {
        this.polling_active = false;
        if (this.next_timeout) {
            clearTimeout(this.next_timeout);
        }
        console.log(`⏹️ Polling detenido para ${this.external_reference}`);
    }
    
    // Verificar estado del pago
    async checkPayment() {
        if (!this.polling_active) return;
        
        this.attempt_count++;
        const currentInterval = this.getInterval();
        
        console.log(`🔍 Intento ${this.attempt_count}/${this.max_attempts} - Próximo en ${currentInterval/1000}s`);
        
        // Notificar estado actual
        if (this.status_callback) {
            this.status_callback({
                attempt: this.attempt_count,
                max_attempts: this.max_attempts,
                next_interval: currentInterval
            });
        }
        
        try {
            // Usar el checker robusto
            const response = await fetch(`/Totem_Murialdo/backend/api/checkoutpro_robust_checker.php?external_reference=${this.external_reference}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            console.log(`📊 Resultado intento ${this.attempt_count}:`, result);
            
            if (result.success) {
                if (result.status === 'approved' && result.processed) {
                    // ¡ÉXITO! Pago procesado
                    console.log(`🎉 PAGO PROCESADO EXITOSAMENTE en intento ${this.attempt_count}`);
                    this.stop();
                    
                    if (this.success_callback) {
                        this.success_callback({
                            external_reference: this.external_reference,
                            attempts: this.attempt_count,
                            result: result
                        });
                    }
                    return;
                    
                } else if (result.status === 'approved' && !result.processed) {
                    console.log(`✅ Pago aprobado pero aún procesando...`);
                    
                } else {
                    console.log(`⏳ Pago aún pendiente: ${result.status}`);
                }
            } else {
                console.warn(`⚠️ Error en verificación:`, result.error);
            }
            
        } catch (error) {
            console.error(`❌ Error en intento ${this.attempt_count}:`, error);
        }
        
        // Verificar si hemos alcanzado el máximo de intentos
        if (this.attempt_count >= this.max_attempts) {
            console.log(`⏰ Máximo de intentos alcanzado (${this.max_attempts})`);
            this.stop();
            
            if (this.error_callback) {
                this.error_callback({
                    external_reference: this.external_reference,
                    attempts: this.attempt_count,
                    reason: 'max_attempts_reached'
                });
            }
            return;
        }
        
        // Programar próxima verificación
        if (this.polling_active) {
            this.next_timeout = setTimeout(() => {
                this.checkPayment();
            }, currentInterval);
        }
    }
    
    // Método estático para crear e iniciar polling fácilmente
    static create(external_reference) {
        return new CheckoutProPolling(external_reference);
    }
}

// ===================================
// FUNCIONES DE UTILIDAD PARA EL DOM
// ===================================

// Mostrar estado de polling en la UI
function updatePollingStatus(status) {
    const statusElement = document.getElementById('polling-status');
    if (statusElement) {
        const progress = Math.round((status.attempt / status.max_attempts) * 100);
        statusElement.innerHTML = `
            <div style="background: #f0f8ff; padding: 10px; border-radius: 5px; margin: 10px 0;">
                <p><strong>🔄 Verificando pago...</strong></p>
                <p>Intento ${status.attempt} de ${status.max_attempts}</p>
                <p>Próxima verificación en ${status.next_interval/1000} segundos</p>
                <div style="background: #ddd; height: 10px; border-radius: 5px;">
                    <div style="background: #4CAF50; height: 100%; width: ${progress}%; border-radius: 5px; transition: width 0.3s;"></div>
                </div>
            </div>
        `;
    }
}

// Mostrar éxito
function showPaymentSuccess(data) {
    const statusElement = document.getElementById('polling-status');
    if (statusElement) {
        statusElement.innerHTML = `
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;">
                <h3>🎉 ¡Pago Procesado Exitosamente!</h3>
                <p><strong>Número de Pedido:</strong> ${data.result.processing_result?.numero_pedido || 'N/A'}</p>
                <p><strong>Intentos necesarios:</strong> ${data.attempts}</p>
                <p><strong>Método:</strong> ${data.result.source === 'local_file' ? 'Webhook instantáneo' : 'Polling inteligente'}</p>
            </div>
        `;
    }
    
    // Opcional: redirigir después de 3 segundos
    setTimeout(() => {
        window.location.href = '/Totem_Murialdo/frontend/views/dashboard.html';
    }, 3000);
}

// Mostrar error
function showPaymentError(data) {
    const statusElement = document.getElementById('polling-status');
    if (statusElement) {
        statusElement.innerHTML = `
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;">
                <h3>⚠️ Tiempo de Espera Agotado</h3>
                <p>No se pudo confirmar el pago automáticamente después de ${data.attempts} intentos.</p>
                <p><strong>¿Qué hacer?</strong></p>
                <ul>
                    <li>Verificar el estado del pago en MercadoPago</li>
                    <li>Si el pago fue exitoso, aparecerá en el sistema en breve</li>
                    <li>Contactar soporte si el problema persiste</li>
                </ul>
                <button onclick="location.reload()" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    🔄 Intentar Nuevamente
                </button>
            </div>
        `;
    }
}

// EJEMPLO DE USO
/*
// En tu página de checkout después de crear la preferencia:
const polling = CheckoutProPolling
    .create('CP_1726234567_123')
    .onStatus(updatePollingStatus)
    .onSuccess(showPaymentSuccess)
    .onError(showPaymentError)
    .start();

// Para detener manualmente (si es necesario):
// polling.stop();
*/

// Exportar para uso global
window.CheckoutProPolling = CheckoutProPolling;
window.updatePollingStatus = updatePollingStatus;
window.showPaymentSuccess = showPaymentSuccess;
window.showPaymentError = showPaymentError;