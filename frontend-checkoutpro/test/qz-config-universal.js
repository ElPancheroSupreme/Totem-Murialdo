// Configuración QZ Tray Universal - Rutas Dinámicas
// Funciona en cualquier estructura de servidor

function configureQZTrayUniversal() {
    if (typeof qz === 'undefined') {
        console.error('QZ Tray no está disponible');
        return false;
    }

    // Detectar rutas base dinámicamente
    const currentPath = window.location.pathname;
    const baseUrl = window.location.protocol + '//' + window.location.host;
    
    // Función para construir rutas inteligentemente
    function buildPath(targetPath) {
        // Si estamos en el directorio test, usar ruta relativa
        if (currentPath.includes('/test/')) {
            return targetPath.startsWith('./') ? targetPath : './' + targetPath;
        }
        
        // Si estamos en otro directorio, construir ruta absoluta
        return baseUrl + '/' + targetPath.replace(/^\.\//, '');
    }

    // Configurar certificado
    qz.security.setCertificatePromise(function(resolve, reject) {
        const certPath = buildPath('./digital-certificate.txt');
        
        console.log('🔑 Cargando certificado desde:', certPath);
        
        fetch(certPath, {
            cache: 'no-store',
            headers: {'Content-Type': 'text/plain'}
        })
        .then(function(response) {
            if (response.ok) {
                return response.text();
            } else {
                // Intentar rutas alternativas
                const altPaths = [
                    './frontend/test/digital-certificate.txt',
                    '/frontend/test/digital-certificate.txt',
                    baseUrl + '/frontend/test/digital-certificate.txt'
                ];
                
                console.log('🔄 Intentando rutas alternativas:', altPaths);
                
                // Probar la primera ruta alternativa
                return fetch(altPaths[0], {
                    cache: 'no-store',
                    headers: {'Content-Type': 'text/plain'}
                }).then(res => {
                    if (res.ok) return res.text();
                    // Probar la segunda ruta alternativa
                    return fetch(altPaths[1], {
                        cache: 'no-store',
                        headers: {'Content-Type': 'text/plain'}
                    }).then(res2 => {
                        if (res2.ok) return res2.text();
                        // Probar la tercera ruta alternativa
                        return fetch(altPaths[2], {
                            cache: 'no-store',
                            headers: {'Content-Type': 'text/plain'}
                        }).then(res3 => {
                            if (res3.ok) return res3.text();
                            throw new Error('Certificado no encontrado en ninguna ruta');
                        });
                    });
                });
            }
        })
        .then(function(certificate) {
            if (certificate && certificate.includes('-----BEGIN CERTIFICATE-----')) {
                console.log('✅ Certificado demo cargado correctamente');
                resolve(certificate);
            } else {
                throw new Error('Formato de certificado inválido');
            }
        })
        .catch(function(error) {
            console.error('❌ Error cargando certificado:', error);
            reject(error);
        });
    });

    // Configurar firma
    qz.security.setSignatureAlgorithm("SHA512");
    qz.security.setSignaturePromise(function(toSign) {
        return function(resolve, reject) {
            // Usar la ruta correcta encontrada en el diagnóstico
            const baseUrl = window.location.protocol + '//' + window.location.host;
            const signPath = baseUrl + '/Totem_Murialdo/backend-checkoutpro/api/sign_message.php';
            
            console.log('🔏 Firmando mensaje usando:', signPath);
            
            fetch(signPath + '?request=' + encodeURIComponent(toSign), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(function(response) {
                if (response.ok) {
                    return response.json();
                } else {
                    // Intentar rutas alternativas para el endpoint de firma (ordenadas por probabilidad)
                    const altSignPaths = [
                        baseUrl + '/Totem_Murialdo/backend-checkoutpro/api/sign_message.php',  // Ruta principal encontrada
                        '../../backend/api/sign_message.php',                      // Ruta relativa que funciona
                        '/backend/api/sign_message.php',
                        '/api/sign_message.php',
                        './backend/api/sign_message.php',
                        baseUrl + '/certsite/Totem_Murialdo/backend-checkoutpro/api/sign_message.php'
                    ];
                    
                    console.log('🔄 Probando rutas alternativas de firma:', altSignPaths);
                    
                    // Probar primera alternativa
                    return fetch(altSignPaths[0] + '?request=' + encodeURIComponent(toSign), {
                        method: 'GET',
                        headers: { 'Content-Type': 'application/json' }
                    }).then(res => {
                        if (res.ok) return res.json();
                        // Probar segunda alternativa
                        return fetch(altSignPaths[1] + '?request=' + encodeURIComponent(toSign), {
                            method: 'GET',
                            headers: { 'Content-Type': 'application/json' }
                        }).then(res2 => {
                            if (res2.ok) return res2.json();
                            throw new Error('Endpoint de firma no encontrado');
                        });
                    });
                }
            })
            .then(function(data) {
                if (data.signature) {
                    console.log('✅ Mensaje firmado correctamente');
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

// Función universal de conexión
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
        if (!configureQZTrayUniversal()) {
            reject(new Error('Error configurando QZ Tray'));
            return;
        }

        // Conectar
        qz.websocket.connect()
            .then(function() {
                console.log('🔗 Conectado a QZ Tray exitosamente');
                resolve();
            })
            .catch(function(error) {
                console.error('❌ Error conectando a QZ Tray:', error);
                reject(error);
            });
    });
}

// Para compatibilidad con versiones anteriores
function configureQZTray() {
    return configureQZTrayUniversal();
}

