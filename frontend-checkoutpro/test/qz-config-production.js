// Configuración de QZ Tray para sitio hosteado en https://ilm2025.webhop.net
// Usando Demo Keys para eliminar el cartel de QZ Tray

// Configuración de QZ Tray para producción
function configureQZTrayForProduction() {
    if (typeof qz === 'undefined') {
        console.error('QZ Tray no está disponible');
        return false;
    }

    // Configurar certificado usando Demo Keys desde la URL del sitio
    qz.security.setCertificatePromise(function(resolve, reject) {
        // Determinar la ruta base según el entorno
        const baseUrl = window.location.protocol + '//' + window.location.host;
        const certPath = window.location.pathname.includes('/frontend/test/') 
            ? './digital-certificate.txt'  // Ruta relativa si ya estamos en el directorio test
            : baseUrl + '/frontend/test/digital-certificate.txt'; // Ruta absoluta
            
        console.log('Intentando cargar certificado desde:', certPath);
        
        // Cargar el certificado demo desde el servidor
        fetch(certPath, {
            cache: 'no-store', 
            headers: {'Content-Type': 'text/plain'},
            mode: 'cors'
        })
        .then(function(response) { 
            if (response.ok) {
                return response.text();
            } else {
                throw new Error('No se pudo cargar el certificado demo desde el servidor');
            }
        })
        .then(function(certificate) {
            console.log('Certificado demo cargado correctamente desde producción');
            resolve(certificate);
        })
        .catch(function(error) {
            console.error('Error cargando certificado demo:', error);
            reject(error);
        });
    });

    // Configurar firma usando Demo Keys con endpoint del backend en producción
    qz.security.setSignatureAlgorithm("SHA512"); // QZ Tray 2.1+
    qz.security.setSignaturePromise(function(toSign) {
        return function(resolve, reject) {
            // Determinar la ruta base según el entorno
            const baseUrl = window.location.protocol + '//' + window.location.host;
            const signUrl = baseUrl + '/backend/api/sign_message.php';
            
            console.log('Intentando firmar mensaje usando:', signUrl);
            
            // Usar el endpoint de firma del backend en producción
            fetch(signUrl + '?request=' + encodeURIComponent(toSign), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                mode: 'cors'
            })
            .then(function(response) {
                if (response.ok) {
                    return response.json();
                } else {
                    throw new Error('Error en el servidor de firma: ' + response.status);
                }
            })
            .then(function(data) {
                if (data.signature) {
                    console.log('Mensaje firmado correctamente en producción');
                    resolve(data.signature);
                } else {
                    throw new Error('No se recibió la firma del servidor');
                }
            })
            .catch(function(error) {
                console.error('Error firmando mensaje en producción:', error);
                reject(error);
            });
        };
    });

    return true;
}

// Función para conectar a QZ Tray con mejor manejo de errores
function connectToQZTrayProduction() {
    return new Promise((resolve, reject) => {
        if (typeof qz === 'undefined') {
            reject(new Error('QZ Tray no está disponible'));
            return;
        }

        // Configurar QZ Tray primero
        if (!configureQZTrayForProduction()) {
            reject(new Error('Error configurando QZ Tray'));
            return;
        }

        // Conectar a QZ Tray
        qz.websocket.connect()
            .then(function() {
                console.log('Conectado a QZ Tray en producción');
                resolve();
            })
            .catch(function(error) {
                console.error('Error conectando a QZ Tray:', error);
                reject(error);
            });
    });
}

// Detectar si estamos en producción y usar la configuración apropiada
function configureQZTray() {
    const isProduction = window.location.hostname === 'ilm2025.webhop.net';
    
    if (isProduction) {
        console.log('Configurando QZ Tray para producción');
        return configureQZTrayForProduction();
    } else {
        console.log('Configurando QZ Tray para desarrollo local');
        // Usar la configuración local original
        if (typeof qz === 'undefined') {
            console.error('QZ Tray no está disponible');
            return false;
        }

        // Configuración para desarrollo local
        qz.security.setCertificatePromise(function(resolve, reject) {
            fetch('./test/digital-certificate.txt', {
                cache: 'no-store', 
                headers: {'Content-Type': 'text/plain'}
            })
            .then(function(response) { 
                if (response.ok) {
                    return response.text();
                } else {
                    throw new Error('No se pudo cargar el certificado demo local');
                }
            })
            .then(function(certificate) {
                console.log('Certificado demo cargado correctamente (local)');
                resolve(certificate);
            })
            .catch(function(error) {
                console.error('Error cargando certificado demo local:', error);
                reject(error);
            });
        });

        qz.security.setSignatureAlgorithm("SHA512");
        qz.security.setSignaturePromise(function(toSign) {
            return function(resolve, reject) {
                fetch('../../backend/api/sign_message.php?request=' + encodeURIComponent(toSign), {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(function(response) {
                    if (response.ok) {
                        return response.json();
                    } else {
                        throw new Error('Error en el servidor de firma local');
                    }
                })
                .then(function(data) {
                    if (data.signature) {
                        console.log('Mensaje firmado correctamente (local)');
                        resolve(data.signature);
                    } else {
                        throw new Error('No se recibió la firma del servidor local');
                    }
                })
                .catch(function(error) {
                    console.error('Error firmando mensaje local:', error);
                    reject(error);
                });
            };
        });

        return true;
    }
}

// Función para conectar a QZ Tray que detecta el entorno
function connectToQZTray() {
    const isProduction = window.location.hostname === 'ilm2025.webhop.net';
    
    if (isProduction) {
        return connectToQZTrayProduction();
    } else {
        return new Promise((resolve, reject) => {
            if (typeof qz === 'undefined') {
                reject(new Error('QZ Tray no está disponible'));
                return;
            }

            if (!configureQZTray()) {
                reject(new Error('Error configurando QZ Tray'));
                return;
            }

            qz.websocket.connect()
                .then(function() {
                    console.log('Conectado a QZ Tray (local)');
                    resolve();
                })
                .catch(function(error) {
                    console.error('Error conectando a QZ Tray:', error);
                    reject(error);
                });
        });
    }
}

