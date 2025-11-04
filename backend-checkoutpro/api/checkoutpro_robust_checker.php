<?php
/**
 * SISTEMA HÍBRIDO ROBUSTO PARA CHECKOUTPRO
 * 
 * Estrategia dual:
 * 1. Webhook (instantáneo cuando funciona)
 * 2. Polling inteligente (fallback garantizado)
 * 
 * Optimizado para minimizar latencia y maximizar confiabilidad
 */

header('Content-Type: application/json');

// CORS seguro - mismo sistema que create_checkoutpro.php
function set_secure_cors() {
    $allowed_origins = [
        'https://ilm2025.webhop.net',
        'https://www.ilm2025.webhop.net',
        'http://localhost:3000',
        'http://127.0.0.1:3000'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        error_log("CORS SECURITY: Unauthorized origin attempt: $origin from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Allow-Credentials: false');
    header('Access-Control-Max-Age: 86400');
}

set_secure_cors();

require_once __DIR__ . '/../config/config.php';

// Headers de seguridad adicionales
function set_security_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

set_security_headers();

function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] ROBUST_CHECKER: $message");
}

function get_checkoutpro_payment_status($external_reference) {
    log_message("Verificando estado de $external_reference");
    
    // 1. VERIFICAR ARCHIVO LOCAL PRIMERO (más rápido)
    $possible_dirs = [
        __DIR__ . "/../ordenes_status",
        "/tmp/ordenes_status",
        dirname(__DIR__) . "/ordenes_status"
    ];
    
    $status_file = null;
    foreach ($possible_dirs as $dir) {
        $file = $dir . "/orden_{$external_reference}.json";
        if (file_exists($file)) {
            $status_file = $file;
            break;
        }
    }
    
    if ($status_file) {
        $data = json_decode(file_get_contents($status_file), true);
        if ($data && isset($data['status']) && $data['status'] === 'approved') {
            log_message("✅ Pago YA procesado localmente: $external_reference");
            return [
                'success' => true,
                'status' => 'approved',
                'processed' => true,
                'source' => 'local_file',
                'data' => $data
            ];
        }
    }
    
    // 2. VERIFICAR DATOS PREVIOS
    $pre_file = null;
    foreach ($possible_dirs as $dir) {
        $file = $dir . "/orden_{$external_reference}_pre.json";
        if (file_exists($file)) {
            $pre_file = $file;
            break;
        }
    }
    
    if (!$pre_file) {
        log_message("❌ No existe archivo previo para $external_reference en ningún directorio");
        return [
            'success' => false,
            'error' => 'No previous order data found',
            'external_reference' => $external_reference
        ];
    }
    
    $orden_previa = json_decode(file_get_contents($pre_file), true);
    if (!$orden_previa) {
        log_message("❌ Error decodificando datos previos de $external_reference");
        return [
            'success' => false,
            'error' => 'Invalid previous order data',
            'external_reference' => $external_reference
        ];
    }
    
    // Obtener payment_id de la preferencia de MercadoPago si no está disponible localmente
    $payment_id = $orden_previa['payment_id'] ?? null;
    
    if (!$payment_id) {
        log_message("🔍 No hay payment_id local, buscando en MercadoPago...");
        
        // Buscar payment_id via merchant orders
        $search_url = "https://api.mercadopago.com/merchant_orders/search?external_reference=$external_reference";
        
        $ch = curl_init($search_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $search_response = curl_exec($ch);
        $search_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($search_http_code === 200) {
            $search_data = json_decode($search_response, true);
            $merchant_orders = $search_data['results'] ?? [];
            
            // Buscar el payment_id en las merchant orders
            foreach ($merchant_orders as $order) {
                $payments = $order['payments'] ?? [];
                if (!empty($payments)) {
                    $payment_id = $payments[0]['id'] ?? null;
                    if ($payment_id) {
                        log_message("✅ Payment ID encontrado: $payment_id");
                        
                        // Guardar el payment_id para futuras consultas
                        $orden_previa['payment_id'] = $payment_id;
                        @file_put_contents($pre_file, json_encode($orden_previa, JSON_PRETTY_PRINT));
                        break;
                    }
                }
            }
        }
    }
    
    if (!$payment_id) {
        log_message("❌ No se pudo obtener payment_id para $external_reference");
        return [
            'success' => false,
            'error' => 'No payment_id found for this order',
            'external_reference' => $external_reference
        ];
    }
    log_message("🔍 Consultando MercadoPago API para payment_id: $payment_id");
    
    // 3. CONSULTAR MERCADOPAGO API
    $access_token = get_mp_credentials(true)['access_token']; // CheckoutPro credentials
    $api_url = "https://api.mercadopago.com/v1/payments/$payment_id";
    
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        log_message("❌ cURL Error: $curl_error");
        return [
            'success' => false,
            'error' => "Network error: $curl_error",
            'external_reference' => $external_reference
        ];
    }
    
    if ($http_code !== 200) {
        log_message("❌ API HTTP $http_code - Response: " . substr($api_response, 0, 200));
        return [
            'success' => false,
            'error' => "MercadoPago API returned HTTP $http_code",
            'external_reference' => $external_reference,
            'http_code' => $http_code
        ];
    }
    
    $payment_data = json_decode($api_response, true);
    if (!$payment_data) {
        log_message("❌ JSON inválido de MercadoPago API");
        return [
            'success' => false,
            'error' => 'Invalid JSON from MercadoPago API',
            'external_reference' => $external_reference
        ];
    }
    
    $payment_status = $payment_data['status'] ?? 'unknown';
    log_message("💰 Payment status en MP: $payment_status");
    
    // 4. PROCESAR SEGÚN ESTADO
    if ($payment_status === 'approved') {
        log_message("🎉 ¡PAGO APROBADO! Procesando automáticamente...");
        
        // Simular webhook internamente para procesar el pago
        $result = process_approved_payment($external_reference, $payment_data, $orden_previa);
        
        log_message("📤 ENVIANDO RESPUESTA AL FRONTEND:");
        log_message("   - success: true");
        log_message("   - status: approved");  
        log_message("   - processed: " . ($result['success'] ?? false ? 'true' : 'false'));
        log_message("   - processing_result.numero_pedido: " . ($result['numero_pedido'] ?? 'UNDEFINED'));
        log_message("   - processing_result.id_pedido: " . ($result['id_pedido'] ?? 'UNDEFINED'));
        
        return [
            'success' => true,
            'status' => 'approved',
            'processed' => $result['success'] ?? false,
            'source' => 'api_polling',
            'processing_result' => $result,
            'external_reference' => $external_reference,
            'payment_id' => $payment_id
        ];
    } else {
        log_message("⏳ Pago aún pendiente: $payment_status");
        return [
            'success' => true,
            'status' => $payment_status,
            'processed' => false,
            'source' => 'api_polling',
            'external_reference' => $external_reference,
            'payment_id' => $payment_id
        ];
    }
}

function process_approved_payment($external_reference, $payment_data, $orden_previa) {
    log_message("🔄 Procesando pago aprobado internamente para $external_reference");
    
    try {
        // Conectar a BD
        $pdo = new PDO('mysql:host=192.168.101.93;dbname=bg02;charset=utf8mb4', 'BG02', 'St2025#QkcwMg');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $carrito = $orden_previa['carrito'] ?? [];
        if (empty($carrito)) {
            throw new Exception("Sin carrito en datos previos");
        }
        
        // Obtener datos de reserva
        $es_reserva = intval($orden_previa['es_reserva'] ?? 0);
        // Solo asignar id_horario si es_reserva = 1
        $id_horario = ($es_reserva === 1 && !empty($orden_previa['id_horario'])) ? intval($orden_previa['id_horario']) : null;
        
        $pdo->beginTransaction();
        
        // Calcular monto total
        $monto_total = 0;
        foreach ($carrito as $item) {
            $precio_unitario = floatval($item['precio_unitario'] ?? 0);
            $cantidad = intval($item['cantidad'] ?? 1);
            $monto_total += ($precio_unitario * $cantidad);
            
            if (!empty($item['personalizaciones'])) {
                foreach ($item['personalizaciones'] as $pers) {
                    $precio_extra = floatval($pers['precio_extra'] ?? 0);
                    $monto_total += $precio_extra * $cantidad;
                }
            }
        }
        
        // Generar número de pedido único y consistente
        // Usar una transacción para evitar números duplicados
        $numero_intentos = 0;
        do {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE DATE(creado_en) = CURDATE()");
            $row = $stmt->fetch();
            $pedidos_hoy = $row['total'] + 1 + $numero_intentos;
            $numero_pedido = "K-" . str_pad($pedidos_hoy, 3, '0', STR_PAD_LEFT);
            
            // Verificar si ya existe
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE numero_pedido = ?");
            $stmt_check->execute([$numero_pedido]);
            $existe = $stmt_check->fetchColumn() > 0;
            
            $numero_intentos++;
        } while ($existe && $numero_intentos < 10);
        
        log_message("📋 Insertando pedido: numero=$numero_pedido, monto=$monto_total");
        
        // Insertar pedido
        $stmt = $pdo->prepare("INSERT INTO pedidos (numero_pedido, id_punto_venta, metodo_pago, monto_total, estado, es_reserva, id_horario) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $resultado = $stmt->execute([$numero_pedido, 2, 'VIRTUAL', $monto_total, 'PREPARACION', $es_reserva, $id_horario]);
        
        if (!$resultado) {
            throw new Exception("Error al insertar pedido en BD");
        }
        
        $id_pedido = $pdo->lastInsertId();
        log_message("✅ Pedido insertado en BD con ID: $id_pedido");
        
        // Insertar items
        foreach ($carrito as $item) {
            $precio_total_item = $item['precio_unitario'] * $item['cantidad'];
            
            if (!empty($item['personalizaciones'])) {
                foreach ($item['personalizaciones'] as $pers) {
                    $precio_total_item += ($pers['precio_extra'] ?? 0) * $item['cantidad'];
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO items_pedido (id_pedido, id_producto, cantidad, precio_unitario, precio_total_item, es_personalizable) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id_pedido,
                $item['id_producto'],
                $item['cantidad'],
                $item['precio_unitario'],
                $precio_total_item,
                !empty($item['personalizaciones']) ? 1 : 0
            ]);
            $id_item_pedido = $pdo->lastInsertId();
            
            if (!empty($item['personalizaciones'])) {
                foreach ($item['personalizaciones'] as $pers) {
                    $stmt = $pdo->prepare("INSERT INTO personalizaciones_item (id_opcion, id_item_pedido) VALUES (?, ?)");
                    $stmt->execute([$pers['id_opcion'], $id_item_pedido]);
                }
            }
        }
        
        $pdo->commit();
        
        // Crear archivo final de confirmación
        $datos_finales = array_merge($orden_previa, [
            'status' => 'approved',
            'external_reference' => $external_reference,
            'fecha_pago' => date('Y-m-d H:i:s'),
            'numero_pedido' => $numero_pedido,
            'id_pedido' => $id_pedido,
            'metodo_pago' => 'VIRTUAL',
            'monto_total' => $monto_total,
            'estado_pedido' => 'PREPARACION',
            'processed_by' => 'robust_checker',
            'mp_payment_data' => $payment_data,
            // DATOS ESPECÍFICOS PARA EL TICKET
            'ticket_data' => [
                'numero_orden' => $numero_pedido,
                'metodo_pago' => 'CheckoutPro Mercado Pago', // ← CORREGIDO
                'ticket_items' => $orden_previa['carrito'],
                'ticket_total' => number_format($monto_total, 2)
            ]
        ]);
        
        // Usar el mismo directorio donde se encontró el archivo _pre
        $status_file = str_replace('_pre.json', '.json', $pre_file);
        file_put_contents($status_file, json_encode($datos_finales, JSON_PRETTY_PRINT));
        
        log_message("✅ Pedido procesado exitosamente: ID=$id_pedido, Número=$numero_pedido");
        
        return [
            'success' => true,
            'id_pedido' => $id_pedido,
            'numero_pedido' => $numero_pedido,
            'monto_total' => $monto_total
        ];
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        log_message("❌ Error procesando pago: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ENDPOINT PRINCIPAL
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $external_reference = $_GET['external_reference'] ?? null;
    
    if (!$external_reference) {
        echo json_encode([
            'success' => false,
            'error' => 'external_reference required'
        ]);
        exit;
    }
    
    // VALIDACIÓN ROBUSTA DEL EXTERNAL_REFERENCE
    // 1. Validar formato estricto: CP_timestamp_randomstring  
    if (!preg_match('/^CP_\d{13}_[a-z0-9]{9}$/', $external_reference)) {
        log_message("SECURITY: Invalid external_reference format: $external_reference from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        echo json_encode([
            'success' => false,
            'error' => 'Invalid CheckoutPro external_reference format'
        ]);
        exit;
    }
    
    // 2. Validar que el timestamp sea razonable
    $parts = explode('_', $external_reference);
    $timestamp = intval($parts[1]);
    $current_time = time() * 1000;
    $thirty_days_ago = $current_time - (30 * 24 * 60 * 60 * 1000);
    
    if ($timestamp < $thirty_days_ago || $timestamp > $current_time + 86400000) {
        log_message("SECURITY: External reference timestamp out of range: $external_reference");
        echo json_encode([
            'success' => false,
            'error' => 'External reference timestamp out of valid range'
        ]);
        exit;
    }
    
    // 3. Sanitizar para evitar path traversal
    $external_reference = basename($external_reference);
    
    $result = get_checkoutpro_payment_status($external_reference);
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Only GET method supported'
    ]);
}
?>