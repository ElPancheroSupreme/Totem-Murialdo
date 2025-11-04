// Configuración de certificados para QZ Tray
// Usando Demo Keys generadas desde QZ Tray > Advanced > Site Manager

// Configuración de QZ Tray con Demo Keys
function configureQZTray() {
    if (typeof qz === 'undefined') {
        console.error('QZ Tray no está disponible');
        return false;
    }

    // Configurar certificado usando Demo Keys
    qz.security.setCertificatePromise(function(resolve, reject) {
        // Cargar el certificado demo desde el archivo
        fetch('./test/digital-certificate.txt', {
            cache: 'no-store', 
            headers: {'Content-Type': 'text/plain'}
        })
        .then(function(response) { 
            if (response.ok) {
                return response.text();
            } else {
                throw new Error('No se pudo cargar el certificado demo');
            }
        })
        .then(function(certificate) {
            console.log('Certificado demo cargado correctamente');
            resolve(certificate);
        })
        .catch(function(error) {
            console.error('Error cargando certificado demo:', error);
            reject(error);
        });
    });

    // Configurar firma usando Demo Keys con endpoint del backend
    qz.security.setSignatureAlgorithm("SHA512"); // QZ Tray 2.1+
    qz.security.setSignaturePromise(function(toSign) {
        return function(resolve, reject) {
            // Usar el endpoint de firma del backend
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
                    throw new Error('Error en el servidor de firma');
                }
            })
            .then(function(data) {
                if (data.signature) {
                    console.log('Mensaje firmado correctamente');
                    resolve(data.signature);
                } else {
                    throw new Error('No se recibió la firma del servidor');
                }
            })
            .catch(function(error) {
                console.error('Error firmando mensaje:', error);
                reject(error);
            });
        };
    });

    return true;
}

// Función para conectar a QZ Tray
function connectToQZTray() {
    return new Promise((resolve, reject) => {
        if (!configureQZTray()) {
            reject(new Error('QZ Tray no está disponible'));
            return;
        }

        if (qz.websocket.isActive()) {
            resolve();
        } else {
            qz.websocket.connect()
                .then(() => {
                    console.log('Conectado a QZ Tray exitosamente');
                    resolve();
                })
                .catch((error) => {
                    console.error('Error al conectar con QZ Tray:', error);
                    reject(error);
                });
        }
    });
}

// Función mejorada para imprimir
function printWithQZTray(ticketData) {
    return connectToQZTray()
        .then(() => {
            return qz.printers.getDefault();
        })
        .then(printer => {
            if (!printer) {
                return qz.printers.find().then(printers => {
                    // Buscar impresoras térmicas
                    const thermalPrinters = printers.filter(p => 
                        p.toLowerCase().includes('tm-') || 
                        p.toLowerCase().includes('thermal') ||
                        p.toLowerCase().includes('receipt') ||
                        p.toLowerCase().includes('pos')
                    );
                    
                    if (thermalPrinters.length > 0) {
                        return thermalPrinters[0];
                    } else if (printers.length > 0) {
                        return printers[0];
                    } else {
                        throw new Error('No se encontraron impresoras disponibles.');
                    }
                });
            }
            return printer;
        })
        .then(selectedPrinter => {
            console.log('Imprimiendo en:', selectedPrinter);
            const config = qz.configs.create(selectedPrinter);
            return qz.print(config, [{ 
                type: 'raw', 
                format: 'plain', 
                data: ticketData 
            }]);
        })
        .then(() => {
            console.log('Ticket enviado a la impresora exitosamente');
            return qz.websocket.disconnect();
        });
}
