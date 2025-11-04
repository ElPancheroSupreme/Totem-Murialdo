// Configuración QZ Tray DEFINITIVA para ilm2025.webhop.net
// Basada en el diagnóstico exitoso del servidor

function configureQZTrayFinal() {
    if (typeof qz === 'undefined') {
        console.error('QZ Tray no está disponible');
        return false;
    }

    const baseUrl = window.location.protocol + '//' + window.location.host;
    
    console.log('🔧 Configurando QZ Tray con rutas verificadas');

    // Configurar certificado
    qz.security.setCertificatePromise(function(resolve, reject) {
        // Intentar ambas rutas: local primero, luego relativa
        const certPaths = ['./digital-certificate.txt', '../test/digital-certificate.txt'];
        
        console.log('🔑 Cargando certificado desde múltiples rutas...');
        
        function tryLoadCert(pathIndex) {
            if (pathIndex >= certPaths.length) {
                reject(new Error('No se pudo cargar el certificado desde ninguna ruta'));
                return;
            }
            
            const certPath = certPaths[pathIndex];
            console.log('🔑 Intentando ruta:', certPath);
            
            fetch(certPath, {
                cache: 'no-store',
                headers: {'Content-Type': 'text/plain'}
            })
            .then(function(response) {
                if (response.ok) {
                    return response.text();
                } else {
                    throw new Error('No se pudo cargar el certificado: HTTP ' + response.status);
                }
            })
            .then(function(certificate) {
                if (certificate && certificate.includes('-----BEGIN CERTIFICATE-----')) {
                    console.log('✅ Certificado demo cargado correctamente desde:', certPath);
                    resolve(certificate);
                } else {
                    throw new Error('Formato de certificado inválido');
                }
            })
            .catch(function(error) {
                console.log('⚠️ Error en ruta ' + certPath + ':', error.message);
                // Intentar siguiente ruta
                tryLoadCert(pathIndex + 1);
            });
        }
        
        // Comenzar con la primera ruta
        tryLoadCert(0);
    });

    // Configurar firma con la ruta VERIFICADA
    qz.security.setSignatureAlgorithm("SHA512");
    qz.security.setSignaturePromise(function(toSign) {
        return function(resolve, reject) {
            // RUTA VERIFICADA que funciona en tu servidor
            const signUrl = baseUrl + '/Totem_Murialdo/backend/api/sign_message.php';
            
            console.log('🔏 Firmando mensaje usando ruta verificada:', signUrl);
            
            fetch(signUrl + '?request=' + encodeURIComponent(toSign), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(function(response) {
                if (response.ok) {
                    return response.json();
                } else {
                    throw new Error('Error del servidor: HTTP ' + response.status);
                }
            })
            .then(function(data) {
                if (data.signature && data.success) {
                    console.log('✅ Mensaje firmado correctamente');
                    console.log('📊 Estadísticas:', {
                        keyPath: data.key_path,
                        messageLength: data.message_length,
                        signatureLength: data.signature_length
                    });
                    resolve(data.signature);
                } else if (data.error) {
                    throw new Error('Error del servidor: ' + data.error);
                } else {
                    throw new Error('Respuesta inválida del servidor de firma');
                }
            })
            .catch(function(error) {
                console.error('❌ Error firmando mensaje:', error);
                reject(error);
            });
        };
    });

    return true;
}

// Función de conexión optimizada
function connectToQZTray() {
    return new Promise((resolve, reject) => {
        if (typeof qz === 'undefined') {
            reject(new Error('QZ Tray no está disponible'));
            return;
        }

        // Si ya está conectado, no intentar conectar de nuevo
        if (qz.websocket.isActive()) {
            console.log('🔗 QZ Tray ya está conectado');
            resolve();
            return;
        }

        // Configurar QZ Tray antes de conectar
        if (!configureQZTrayFinal()) {
            reject(new Error('Error configurando QZ Tray'));
            return;
        }

        // Conectar
        qz.websocket.connect()
            .then(function() {
                console.log('🔗 Conectado a QZ Tray exitosamente');
                console.log('🚫 Cartel de QZ Tray eliminado con llaves demo');
                resolve();
            })
            .catch(function(error) {
                console.error('❌ Error conectando a QZ Tray:', error);
                reject(error);
            });
    });
}

// Para compatibilidad
function configureQZTray() {
    return configureQZTrayFinal();
}
