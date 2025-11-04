<?php<?php

// webhook_checkoutpro.php - Webhook exclusivo para flujo CheckoutPro// webhook_final.php - Versión final simplificada del webhook



header('Content-Type: application/json');header('Content-Type: application/json');

error_reporting(E_ALL);error_reporting(E_ALL);



// Log específico para CheckoutPro// Log simple que siempre funciona

function checkoutpro_log($message) {function simple_log($message) {

    $timestamp = date('Y-m-d H:i:s');    $timestamp = date('Y-m-d H:i:s');

    $entry = "[$timestamp] CHECKOUTPRO_WEBHOOK: $message\n";    $entry = "[$timestamp] $message\n";

        

    // Logs específicos para CheckoutPro    // Guardar en múltiples ubicaciones para asegurar que se escriba

    $log_files = [    $log_files = [

        __DIR__ . '/webhook_checkoutpro.log',        __DIR__ . '/webhook_simple.log',

        __DIR__ . '/../logs/checkoutpro_webhook.log'        __DIR__ . '/../webhook_simple.log',

    ];        __DIR__ . '/../../webhook_debug.log'

        ];

    foreach ($log_files as $log_file) {    

        @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);    foreach ($log_files as $log_file) {

    }        @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);

}    }

    

checkoutpro_log("=== WEBHOOK CHECKOUTPRO INICIADO ===");    // También enviar a un archivo de debug visible

    $debug_file = __DIR__ . '/../../webhook_activity.txt';

// Solo procesamos webhooks de CheckoutPro - no QR    $debug_entry = "[" . date('H:i:s') . "] $message\n";

$source = $_GET['source'] ?? 'checkoutpro';    @file_put_contents($debug_file, $debug_entry, FILE_APPEND);

checkoutpro_log("Fuente detectada: $source (solo CheckoutPro soportado)");    

    // NUEVO: También crear archivo que siempre funcione

if ($source !== 'checkoutpro' && $source !== 'unknown') {    $always_file = __DIR__ . '/webhook_always.log';

    checkoutpro_log("ERROR: Webhook no es de CheckoutPro. Fuente: $source");    @file_put_contents($always_file, $entry, FILE_APPEND | LOCK_EX);

    http_response_code(400);}

    echo json_encode(['error' => 'Este webhook solo maneja pagos CheckoutPro', 'source_received' => $source]);

    exit;simple_log("=== WEBHOOK INICIADO ===");

}

// Detectar fuente del webhook

try {$source = $_GET['source'] ?? 'unknown';

    // Leer body del webhooksimple_log("Fuente del webhook: $source");

    $raw_body = file_get_contents('php://input');

    checkoutpro_log("Body recibido: " . substr($raw_body, 0, 200));if ($source === 'checkoutpro') {

        simple_log("*** WEBHOOK CHECKOUTPRO DETECTADO ***");

    if (empty($raw_body)) {}

        checkoutpro_log("ERROR: Body vacío");

        echo json_encode(['error' => 'Empty body']);try {

        exit;    // Leer body

    }    $raw_body = file_get_contents('php://input');

        simple_log("Body recibido: " . substr($raw_body, 0, 200));

    $payload = json_decode($raw_body, true);    

    if (!$payload) {    if (empty($raw_body)) {

        checkoutpro_log("ERROR: JSON inválido");        simple_log("ERROR: Body vacío");

        echo json_encode(['error' => 'Invalid JSON']);        echo json_encode(['error' => 'Empty body', 'debug' => true]);

        exit;        exit;

    }    }

        

    checkoutpro_log("Payload type: " . ($payload['type'] ?? 'unknown'));    // Decodificar JSON

        $payload = json_decode($raw_body, true);

    // Respuesta a test de configuración    if (json_last_error() !== JSON_ERROR_NONE) {

    if (isset($payload['type']) && $payload['type'] === 'test' && !isset($payload['data'])) {        simple_log("ERROR: JSON inválido");

        checkoutpro_log("Test de configuración recibido");        echo json_encode(['error' => 'Invalid JSON', 'debug' => true]);

        echo json_encode(['success' => true, 'message' => 'CheckoutPro Webhook active']);        exit;

        exit;    }

    }    

        simple_log("Payload type: " . ($payload['type'] ?? 'unknown'));

    // Obtener ID del webhook    

    $webhook_id = null;    // Respuesta a test simple (webhook de configuración)

    $webhook_type = $payload['type'] ?? 'unknown';    if (isset($payload['type']) && $payload['type'] === 'test' && !isset($payload['data'])) {

            simple_log("Test de configuración recibido (sin data)");

    if (strpos($webhook_type, 'merchant_order') !== false) {        echo json_encode(['success' => true, 'message' => 'Test received']);

        $webhook_id = $payload['data']['id'] ?? null;        exit;

        checkoutpro_log("Webhook merchant_order - ID: $webhook_id");    }

    } elseif (strpos($webhook_type, 'payment') !== false) {    

        $webhook_id = $payload['data']['id'] ?? null;    // Verificar diferentes tipos de webhook

        checkoutpro_log("Webhook payment - ID: $webhook_id");    $webhook_id = null;

    }    $webhook_type = $payload['type'] ?? 'unknown';

        

    if (!$webhook_id) {    // Manejar merchant_order (producción)

        checkoutpro_log("No se encontró ID válido");    if (strpos($webhook_type, 'merchant_order') !== false) {

        echo json_encode(['success' => true, 'message' => 'No valid ID']);        $webhook_id = $payload['data']['id'] ?? null;

        exit;        simple_log("Webhook tipo merchant_order detectado - ID: $webhook_id");

    }    }

        // Manejar payment (común en cuentas de prueba)

    // Consultar API de MercadoPago con credenciales CheckoutPro    elseif (strpos($webhook_type, 'payment') !== false) {

    require_once __DIR__ . '/../config/config.php';        $webhook_id = $payload['data']['id'] ?? null;

            simple_log("Webhook tipo payment detectado - ID: $webhook_id");

    $api_url = "https://api.mercadopago.com/v1/payments/$webhook_id";    }

    checkoutpro_log("Consultando API MP: $api_url");    // Manejar test con data (notificaciones de prueba)

        elseif ($webhook_type === 'test' && isset($payload['data']['id'])) {

    $ch = curl_init($api_url);        $webhook_id = $payload['data']['id'] ?? null;

    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . MP_ACCESS_TOKEN]);        simple_log("Webhook de prueba con data detectado - ID: $webhook_id");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);        

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // Para tests internos, verificar si el external_reference está en los parámetros GET

    curl_setopt($ch, CURLOPT_TIMEOUT, 10);        if (isset($_GET['external_reference'])) {

                $external_reference = $_GET['external_reference'];

    $api_response = curl_exec($ch);            simple_log("TEST INTERNO: external_reference recibido por GET: $external_reference");

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);            

    curl_close($ch);            // Simular pago exitoso para tests

                $order_status = 'paid';

    if ($http_code !== 200 || !$api_response) {            simple_log("TEST INTERNO: Simulando pago exitoso para $external_reference");

        checkoutpro_log("ERROR: API MP respondió HTTP $http_code");            

        echo json_encode(['success' => false, 'error' => "API error: $http_code"]);            // Saltar consulta a MercadoPago API y proceder directamente

        exit;            goto procesar_pago_exitoso;

    }        }

        }

    $response_data = json_decode($api_response, true);    

    if (!$response_data) {    if (!$webhook_id) {

        checkoutpro_log("ERROR: Respuesta de MP no es JSON válido");        simple_log("No se encontró ID válido en webhook type: $webhook_type");

        echo json_encode(['success' => false, 'error' => 'Invalid MP response']);        echo json_encode(['success' => true, 'message' => 'No valid ID found']);

        exit;        exit;

    }    }

        

    // Extraer datos del pago    simple_log("Procesando webhook ID: $webhook_id de tipo: $webhook_type");

    $external_reference = $response_data['external_reference'] ?? null;    

    $payment_status = $response_data['status'] ?? 'unknown';    // Consultar API de Mercado Pago según el tipo

    $payment_amount = $response_data['transaction_amount'] ?? 0;    require_once __DIR__ . '/../config/config.php';

        

    checkoutpro_log("Pago CheckoutPro procesado - Ref: $external_reference, Status: $payment_status, Monto: $payment_amount");    // DETERMINAR QUÉ CREDENCIALES USAR SEGÚN LA FUENTE

        $es_checkoutpro = ($source === 'checkoutpro') || 

    // Verificar que es un pago CheckoutPro (no QR)                      (isset($payload['data']['external_reference']) && strpos($payload['data']['external_reference'], 'CP_') === 0) ||

    if ($external_reference && strpos($external_reference, 'CP_') !== 0) {                      (isset($_GET['external_reference']) && strpos($_GET['external_reference'], 'CP_') === 0);

        checkoutpro_log("WARNING: Este parece ser un pago QR, no CheckoutPro");    

        echo json_encode(['success' => false, 'error' => 'QR payment detected in CheckoutPro webhook']);    // DETECTIVE INTELIGENTE: Si no se detecta CheckoutPro, probar con ambas credenciales

        exit;    if (!$es_checkoutpro && strpos($webhook_type, 'payment') !== false && $webhook_id) {

    }        simple_log("🕵️ Detectando tipo de pago - probando ambas credenciales...");

            

    // Solo procesar si el pago está aprobado        // Probar primero con credenciales de CheckoutPro

    if ($payment_status !== 'approved') {        $credentials_cp = get_mp_credentials(true);

        checkoutpro_log("Pago no aprobado: $payment_status");        $test_url = "https://api.mercadopago.com/v1/payments/$webhook_id";

        echo json_encode(['success' => true, 'message' => 'Payment not approved', 'status' => $payment_status]);        

        exit;        $ch_test = curl_init($test_url);

    }        curl_setopt($ch_test, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $credentials_cp['access_token']]);

            curl_setopt($ch_test, CURLOPT_RETURNTRANSFER, true);

    // Leer datos previos del pedido        curl_setopt($ch_test, CURLOPT_SSL_VERIFYPEER, false);

    $pre_file = __DIR__ . "/../ordenes_status/orden_{$external_reference}_pre.json";        curl_setopt($ch_test, CURLOPT_TIMEOUT, 10);

    $orden_previa = [];        

            $test_response = curl_exec($ch_test);

    if (file_exists($pre_file)) {        $test_http_code = curl_getinfo($ch_test, CURLINFO_HTTP_CODE);

        $orden_previa = json_decode(file_get_contents($pre_file), true) ?? [];        curl_close($ch_test);

        checkoutpro_log("Datos previos encontrados para $external_reference");        

    } else {        if ($test_http_code === 200) {

        checkoutpro_log("WARNING: Sin datos previos para $external_reference");            $test_data = json_decode($test_response, true);

                    $test_external_ref = $test_data['external_reference'] ?? '';

        // Para CheckoutPro, intentar reconstruir carrito desde MP payload            

        if (isset($response_data['additional_info']['items'])) {            if (strpos($test_external_ref, 'CP_') === 0) {

            $mp_items = $response_data['additional_info']['items'];                $es_checkoutpro = true;

            $carrito_reconstruido = [];                simple_log("🎯 DETECTADO: Es CheckoutPro - external_reference: $test_external_ref");

                        } else {

            foreach ($mp_items as $mp_item) {                simple_log("📱 DETECTADO: Es QR - external_reference: $test_external_ref");

                $carrito_reconstruido[] = [            }

                    'nombre' => $mp_item['title'] ?? 'Producto',        } else {

                    'precio_unitario' => $mp_item['unit_price'] ?? 0,            // Si falló con CheckoutPro, probar con credenciales de QR

                    'cantidad' => $mp_item['quantity'] ?? 1            simple_log("⚠️ No encontrado con credenciales CheckoutPro, probando QR...");

                ];            

            }            $ch_qr = curl_init($test_url);

                        curl_setopt($ch_qr, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . MP_ACCESS_TOKEN]);

            $orden_previa['carrito'] = $carrito_reconstruido;            curl_setopt($ch_qr, CURLOPT_RETURNTRANSFER, true);

            checkoutpro_log("Carrito reconstruido desde payload MP");            curl_setopt($ch_qr, CURLOPT_SSL_VERIFYPEER, false);

        }            curl_setopt($ch_qr, CURLOPT_TIMEOUT, 10);

    }            

                $qr_response = curl_exec($ch_qr);

    // Procesar pago exitoso CheckoutPro            $qr_http_code = curl_getinfo($ch_qr, CURLINFO_HTTP_CODE);

    checkoutpro_log("¡PAGO CHECKOUTPRO APROBADO! Procesando pedido...");            curl_close($ch_qr);

                

    // Generar número de pedido único            if ($qr_http_code === 200) {

    require_once __DIR__ . '/../config/pdo_connection.php';                simple_log("📱 CONFIRMADO: Es QR (encontrado con credenciales QR)");

                    $es_checkoutpro = false;

    do {            } else {

        $numero_pedido = 'CP-' . substr(uniqid(), -6);                simple_log("❌ No encontrado en ninguna cuenta - payment_id: $webhook_id");

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE numero_orden = ?');            }

        $stmt->execute([$numero_pedido]);        }

        $exists = $stmt->fetchColumn() > 0;    }

    } while ($exists);    

        if ($es_checkoutpro) {

    checkoutpro_log("Número de pedido generado: $numero_pedido");        $credentials = get_mp_credentials(true); // CheckoutPro credentials

            $access_token = $credentials['access_token'];

    // Calcular total        simple_log("USANDO CREDENCIALES DE CHECKOUTPRO: " . substr($access_token, 0, 20) . "...");

    $total = 0;    } else {

    $carrito = $orden_previa['carrito'] ?? [];        $access_token = MP_ACCESS_TOKEN; // QR credentials

            simple_log("USANDO CREDENCIALES DE QR: " . substr($access_token, 0, 20) . "...");

    foreach ($carrito as $item) {    }

        $precio_unitario = floatval($item['precio_unitario'] ?? 0);    

        $cantidad = intval($item['cantidad'] ?? 1);    // Determinar la URL de API según el tipo de webhook

        $total += $precio_unitario * $cantidad;    if (strpos($webhook_type, 'merchant_order') !== false) {

    }        $api_url = "https://api.mercadopago.com/merchant_orders/$webhook_id";

        } elseif (strpos($webhook_type, 'payment') !== false || $webhook_type === 'test') {

    // Insertar pedido en base de datos        // Para payments o tests, consultar el endpoint de payments

    $stmt = $pdo->prepare('        $api_url = "https://api.mercadopago.com/v1/payments/$webhook_id";

        INSERT INTO pedidos (    } else {

            numero_orden, total, metodo_pago, estado,         // Fallback: intentar merchant_order

            fecha_creacion, carrito_json        $api_url = "https://api.mercadopago.com/merchant_orders/$webhook_id";

        ) VALUES (?, ?, ?, ?, NOW(), ?)    }

    ');    

        simple_log("Consultando: $api_url");

    $carrito_json = json_encode($carrito, JSON_UNESCAPED_UNICODE);    

    $stmt->execute([    $ch = curl_init($api_url);

        $numero_pedido,    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);

        $total,    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        'CHECKOUTPRO',    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        'PAGADO',    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $carrito_json    

    ]);    $api_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    checkoutpro_log("Pedido CheckoutPro insertado en BD: $numero_pedido");    curl_close($ch);

        

    // Crear archivo de estado final    simple_log("API HTTP: $http_code");

    $final_data = [    

        'success' => true,    if ($http_code !== 200) {

        'status' => 'paid',        simple_log("ERROR: API falló con HTTP $http_code - Response: " . substr($api_response, 0, 200));

        'external_reference' => $external_reference,        echo json_encode(['error' => "API returned HTTP $http_code", 'debug' => true]);

        'payment_status' => $payment_status,        exit;

        'payment_amount' => $payment_amount,    }

        'numero_pedido' => $numero_pedido,    

        'metodo_pago' => 'CHECKOUTPRO',    $response_data = json_decode($api_response, true);

        'carrito' => $carrito,    

        'total' => $total,    // Procesar respuesta según el tipo de endpoint

        'ticket_data' => [    $order_status = 'unknown';

            'numero_orden' => $numero_pedido,    $external_reference = null;

            'metodo_pago' => 'CheckoutPro Mercado Pago',    

            'ticket_items' => $carrito,    if (strpos($webhook_type, 'merchant_order') !== false) {

            'ticket_total' => number_format($total, 2)        // Respuesta de merchant_order

        ]        $order_status = $response_data['order_status'] ?? 'unknown';

    ];        $external_reference = $response_data['external_reference'] ?? null;

            simple_log("Merchant Order - Status: $order_status, external_reference: $external_reference");

    $status_file = __DIR__ . "/../ordenes_status/orden_{$external_reference}.json";    } elseif (strpos($webhook_type, 'payment') !== false || $webhook_type === 'test') {

    file_put_contents($status_file, json_encode($final_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));        // Respuesta de payment

            $payment_status = $response_data['status'] ?? 'unknown';

    checkoutpro_log("Archivo de estado CheckoutPro creado: $status_file");        $external_reference = $response_data['external_reference'] ?? null;

            

    echo json_encode(['success' => true, 'message' => 'CheckoutPro payment processed successfully']);        // Para CheckoutPro, a veces el external_reference está en different_path

            if (!$external_reference && $es_checkoutpro) {

} catch (Exception $e) {            // Buscar en otras ubicaciones posibles

    checkoutpro_log("EXCEPCIÓN: " . $e->getMessage());            if (isset($response_data['order']['external_reference'])) {

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);                $external_reference = $response_data['order']['external_reference'];

}                simple_log("External reference encontrado en order.external_reference: $external_reference");

?>            } elseif (isset($response_data['additional_info']['external_reference'])) {
                $external_reference = $response_data['additional_info']['external_reference'];
                simple_log("External reference encontrado en additional_info.external_reference: $external_reference");
            }
        }
        
        // Convertir status de payment a order_status
        $order_status = ($payment_status === 'approved') ? 'paid' : $payment_status;
        simple_log("Payment - Status: $payment_status -> Order status: $order_status, external_reference: $external_reference");
    }
    
    // Verificar si está pagada
    if (!in_array($order_status, ['paid', 'partially_paid']) && $order_status !== 'approved') {
        simple_log("Pago no completado - Status: $order_status");
        echo json_encode(['success' => true, 'message' => 'Payment not completed']);
        exit;
    }
    
    procesar_pago_exitoso: // Etiqueta para tests internos
    
    if (!$external_reference) {
        simple_log("ERROR: Sin external_reference en respuesta");
        echo json_encode(['error' => 'No external_reference in response']);
        exit;
    }
    
    simple_log("¡PAGO COMPLETADO! External reference: $external_reference - Iniciando proceso de guardado de pedido");
    
    // VERIFICAR SI ES CHECKOUTPRO (external_reference empieza con CP_)
    $es_checkoutpro = strpos($external_reference, 'CP_') === 0;
    simple_log("Tipo de pago detectado: " . ($es_checkoutpro ? 'CheckoutPro' : 'QR'));
    
    // Leer datos previos (contienen el carrito)
    $pre_file = __DIR__ . "/../ordenes_status/orden_{$external_reference}_pre.json";
    $orden_previa = [];
    
    if (file_exists($pre_file)) {
        $orden_previa = json_decode(file_get_contents($pre_file), true) ?? [];
        simple_log("Datos previos encontrados para $external_reference");
    } else {
        simple_log("WARNING: Sin datos previos para $external_reference");
        
        if ($es_checkoutpro) {
            // Para CheckoutPro, intentar obtener datos del payload de MercadoPago
            simple_log("CheckoutPro detectado - Intentando obtener carrito del payload de MercadoPago");
            
            // Si tenemos datos de MercadoPago, intentar reconstruir el carrito
            if (isset($response_data['additional_info']['items'])) {
                $mp_items = $response_data['additional_info']['items'];
                $carrito_reconstruido = [];
                
                foreach ($mp_items as $mp_item) {
                    // Parsear el título para extraer personalizaciones
                    $titulo = $mp_item['title'] ?? '';
                    $cantidad = $mp_item['quantity'] ?? 1;
                    $precio_unitario = $mp_item['unit_price'] ?? 0;
                    
                    // Separar nombre y personalizaciones del título
                    $nombre = $titulo;
                    $personalizaciones = [];
                    
                    if (strpos($titulo, '(') !== false && strpos($titulo, ')') !== false) {
                        $parts = explode('(', $titulo, 2);
                        $nombre = trim($parts[0]);
                        $pers_str = trim($parts[1], ')');
                        
                        if (!empty($pers_str)) {
                            $pers_array = explode(',', $pers_str);
                            foreach ($pers_array as $pers) {
                                $personalizaciones[] = [
                                    'nombre_opcion' => trim($pers),
                                    'precio_extra' => 0 // No podemos recuperar el precio exacto
                                ];
                            }
                        }
                    }
                    
                    $carrito_reconstruido[] = [
                        'id_producto' => rand(1000, 9999), // ID temporal
                        'nombre' => $nombre,
                        'precio_unitario' => $precio_unitario / $cantidad, // Precio unitario sin personalizaciones
                        'cantidad' => $cantidad,
                        'personalizaciones' => $personalizaciones
                    ];
                }
                
                if (!empty($carrito_reconstruido)) {
                    $orden_previa = [
                        'carrito' => $carrito_reconstruido,
                        'external_reference' => $external_reference,
                        'tipo_pago' => 'checkoutpro',
                        'fecha_creacion' => date('Y-m-d H:i:s'),
                        'estado' => 'pagado',
                        'reconstruido_desde_mp' => true
                    ];
                    simple_log("Carrito reconstruido desde MercadoPago con " . count($carrito_reconstruido) . " items");
                } else {
                    simple_log("ERROR: No se pudo reconstruir carrito desde MercadoPago");
                }
            }
            
            // Si aún no tenemos carrito, crear uno genérico para no fallar
            if (empty($orden_previa['carrito'])) {
                simple_log("FALLBACK: Creando carrito genérico para CheckoutPro");
                $monto_mp = $response_data['transaction_amount'] ?? 100;
                $orden_previa = [
                    'carrito' => [
                        [
                            'id_producto' => 9999,
                            'nombre' => 'Pedido CheckoutPro',
                            'precio_unitario' => $monto_mp,
                            'cantidad' => 1,
                            'personalizaciones' => []
                        ]
                    ],
                    'external_reference' => $external_reference,
                    'tipo_pago' => 'checkoutpro',
                    'fecha_creacion' => date('Y-m-d H:i:s'),
                    'estado' => 'pagado',
                    'carrito_generico' => true
                ];
                simple_log("Carrito genérico creado con monto: $monto_mp");
            }
        } else {
            // Para QR tradicional, es obligatorio tener datos previos
            simple_log("ERROR: Sin datos previos para QR - No se puede crear el pedido");
            echo json_encode(['error' => 'No previous order data found']);
            exit;
        }
    }
    
    // Verificar que tengamos carrito
    if (empty($orden_previa['carrito'])) {
        simple_log("ERROR: No hay carrito en los datos previos para $external_reference");
        echo json_encode(['error' => 'No cart data found']);
        exit;
    }
    
    simple_log("Carrito encontrado con " . count($orden_previa['carrito']) . " items");
    
    // HACER EXACTAMENTE LO MISMO QUE LA TECLA "C": POST a guardar_pedido.php
    // IMPORTANTE: Usar valores que coincidan con la BD
    $pedido_data = [
        'carrito' => $orden_previa['carrito'],
        'metodo_pago' => 'VIRTUAL', // BD solo acepta 'EFECTIVO' o 'VIRTUAL'
        'estado' => 'PREPARACION'   // BD solo acepta 'LISTO','PREPARACION','PENDIENTE','ENTREGADO','CANCELADO'
    ];
    
    simple_log("Guardando pedido directamente en BD (sin cURL)");
    
    // GUARDAR DIRECTAMENTE EN LA BD (como hace guardar_pedido.php)
    try {
        simple_log("Iniciando conexión PDO...");
        // Usar la misma conexión que guardar_pedido.php
        $pdo = new PDO('mysql:host=192.168.101.93;dbname=bg02;charset=utf8mb4', 'BG02', 'St2025#QkcwMg');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        simple_log("✅ Conexión PDO exitosa");
        
        $carrito = $orden_previa['carrito'];
        $metodo_pago = 'VIRTUAL';
        $estado = 'PREPARACION';
        $id_punto_venta = 2;
        
        // Obtener datos de reserva
        $es_reserva = intval($orden_previa['es_reserva'] ?? 0);
        // Solo asignar id_horario si es_reserva = 1
        $id_horario = ($es_reserva === 1 && !empty($orden_previa['id_horario'])) ? intval($orden_previa['id_horario']) : null;
        
        simple_log("Datos: carrito=" . count($carrito) . " items, metodo=$metodo_pago, estado=$estado");
        
        $pdo->beginTransaction();
        simple_log("✅ Transacción iniciada");
        
        // Calcular monto total (incluyendo personalizaciones)
        $monto_total = 0;
        foreach ($carrito as $item) {
            $precio_unitario = floatval($item['precio_unitario'] ?? 0);
            $cantidad = intval($item['cantidad'] ?? 1);
            $monto_total += ($precio_unitario * $cantidad);
            
            // Agregar personalizaciones si existen
            if (!empty($item['personalizaciones'])) {
                foreach ($item['personalizaciones'] as $pers) {
                    $precio_extra = floatval($pers['precio_extra'] ?? 0);
                    $monto_total += $precio_extra * $cantidad;
                }
            }
        }
        simple_log("Monto total calculado: $monto_total");
        
        // Generar número de pedido
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE id_punto_venta = 2");
        $row = $stmt->fetch();
        $numero_pedido = "K-" . str_pad($row['total'] + 1, 3, '0', STR_PAD_LEFT);
        simple_log("Número de pedido generado: $numero_pedido");
        
        // Insertar pedido
        $stmt = $pdo->prepare("INSERT INTO pedidos (numero_pedido, id_punto_venta, metodo_pago, monto_total, estado, es_reserva, id_horario) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$numero_pedido, $id_punto_venta, $metodo_pago, $monto_total, $estado, $es_reserva, $id_horario]);
        $id_pedido = $pdo->lastInsertId();
        simple_log("✅ Pedido insertado: ID=$id_pedido, resultado=" . ($result ? 'true' : 'false'));
        
        // Insertar items y personalizaciones
        $items_insertados = 0;
        foreach ($carrito as $item) {
            $precio_total_item = $item['precio_unitario'] * $item['cantidad'];
            
            // Agregar costo de personalizaciones al item
            if (!empty($item['personalizaciones'])) {
                foreach ($item['personalizaciones'] as $pers) {
                    $precio_total_item += ($pers['precio_extra'] ?? 0) * $item['cantidad'];
                }
            }
            
            // Insertar item
            $stmt = $pdo->prepare("INSERT INTO items_pedido (id_pedido, id_producto, cantidad, precio_unitario, precio_total_item, es_personalizable) VALUES (?, ?, ?, ?, ?, ?)");
            $result_item = $stmt->execute([
                $id_pedido,
                $item['id_producto'],
                $item['cantidad'],
                $item['precio_unitario'],
                $precio_total_item,
                !empty($item['personalizaciones']) ? 1 : 0
            ]);
            $id_item_pedido = $pdo->lastInsertId();
            $items_insertados++;
            
            // Insertar personalizaciones si existen
            if (!empty($item['personalizaciones'])) {
                foreach ($item['personalizaciones'] as $pers) {
                    $stmt = $pdo->prepare("INSERT INTO personalizaciones_item (id_opcion, id_item_pedido) VALUES (?, ?)");
                    $stmt->execute([
                        $pers['id_opcion'],
                        $id_item_pedido
                    ]);
                }
            }
        }
        simple_log("✅ Items insertados: $items_insertados");
        
        $pdo->commit();
        simple_log("✅ Transacción committed");
        
        simple_log("🎉 PEDIDO GUARDADO EN BD: ID=$id_pedido, Número=$numero_pedido, Monto=$monto_total");
        $guardar_response = json_encode(['success' => true, 'id_pedido' => $id_pedido, 'numero_pedido' => $numero_pedido]);
        $guardar_http_code = 200;
        
    } catch (Exception $e) {
        simple_log("❌ EXCEPCIÓN en guardar BD: " . $e->getMessage());
        simple_log("❌ Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
        if (isset($pdo)) {
            try {
                $pdo->rollBack();
                simple_log("✅ Rollback ejecutado");
            } catch (Exception $rb_e) {
                simple_log("❌ Error en rollback: " . $rb_e->getMessage());
            }
        }
        $guardar_response = json_encode(['success' => false, 'error' => $e->getMessage()]);
        $guardar_http_code = 500;
    }
    
    simple_log("Guardado en BD - HTTP: $guardar_http_code");
    
    if ($guardar_http_code !== 200) {
        simple_log("ERROR HTTP al guardar pedido: $guardar_http_code - Response: " . substr($guardar_response, 0, 200));
        echo json_encode(['error' => "guardar_pedido returned HTTP $guardar_http_code"]);
        exit;
    }
    
    $guardar_data = json_decode($guardar_response, true);
    
    if (!$guardar_data || !$guardar_data['success']) {
        simple_log("ERROR: guardar_pedido falló - " . ($guardar_data['error'] ?? 'Unknown error'));
        echo json_encode(['error' => 'Failed to save order: ' . ($guardar_data['error'] ?? 'Unknown')]);
        exit;
    }
    
    simple_log("¡PEDIDO GUARDADO EXITOSAMENTE! ID: " . $guardar_data['id_pedido'] . ", Número: " . $guardar_data['numero_pedido']);
    
    $numero_pedido = $guardar_data['numero_pedido'];
    $id_pedido = $guardar_data['id_pedido'];
    
    // Calcular total del carrito para el ticket
    $monto_total = 0;
    foreach ($orden_previa['carrito'] as $item) {
        $monto_total += ($item['precio_unitario'] * $item['cantidad']);
        if (!empty($item['personalizaciones'])) {
            foreach ($item['personalizaciones'] as $pers) {
                $monto_total += ($pers['precio_extra'] ?? 0) * $item['cantidad'];
            }
        }
    }
    
    // Crear datos finales con toda la información necesaria para el ticket
    $datos_finales = array_merge($orden_previa, [
        'status' => 'approved',
        'external_reference' => $external_reference,
        'fecha_pago' => date('Y-m-d H:i:s'),
        'webhook_id' => $webhook_id,
        'webhook_type' => $webhook_type,
        'order_status' => $order_status,
        // Datos del pedido guardado
        'numero_pedido' => $numero_pedido,
        'id_pedido' => $id_pedido,
        'metodo_pago' => 'VIRTUAL', // Guardado en BD como VIRTUAL
        'monto_total' => $monto_total,
        'estado_pedido' => 'PREPARACION', // Guardado en BD como PREPARACION
        // Datos para el ticket (identificar correctamente el tipo)
        'ticket_data' => [
            'numero_orden' => $numero_pedido,
            'metodo_pago' => $es_checkoutpro ? 'CheckoutPro Mercado Pago' : 'QR Mercado Pago',
            'ticket_items' => $orden_previa['carrito'],
            'ticket_total' => number_format($monto_total, 2)
        ]
    ]);
    
    // Guardar archivo final
    $status_file = __DIR__ . "/../ordenes_status/orden_{$external_reference}.json";
    $json_data = json_encode($datos_finales, JSON_PRETTY_PRINT);
    
    // Crear directorio si no existe
    $dir = dirname($status_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    // Intentar guardar
    $result = @file_put_contents($status_file, $json_data);
    
    if ($result !== false) {
        simple_log("ÉXITO COMPLETO: Pedido guardado en DB y archivo creado: $status_file");
        
        // También actualizar el archivo _pre para marcarlo como procesado
        $orden_previa['webhook_processed'] = true;
        $orden_previa['status'] = 'approved';
        $orden_previa['fecha_procesamiento'] = date('Y-m-d H:i:s');
        $orden_previa['numero_pedido_final'] = $numero_pedido;
        @file_put_contents($pre_file, json_encode($orden_previa, JSON_PRETTY_PRINT));
        simple_log("Archivo _pre actualizado como procesado");
        
        echo json_encode(['success' => true, 'external_reference' => $external_reference]);
    } else {
        simple_log("ERROR: No se pudo guardar archivo");
        
        // Intentar ubicación alternativa
        $alt_file = __DIR__ . "/orden_{$external_reference}.json";
        $alt_result = @file_put_contents($alt_file, $json_data);
        
        if ($alt_result !== false) {
            simple_log("ÉXITO: Guardado en ubicación alternativa: $alt_file");
            echo json_encode(['success' => true, 'external_reference' => $external_reference, 'alt_location' => true]);
        } else {
            simple_log("ERROR FATAL: No se pudo guardar en ninguna ubicación");
            echo json_encode(['error' => 'Could not save file']);
        }
    }
    
} catch (Exception $e) {
    simple_log("EXCEPCIÓN: " . $e->getMessage());
    echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
}

simple_log("=== WEBHOOK FINALIZADO ===");
?>
