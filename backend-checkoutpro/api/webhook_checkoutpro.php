<?php
// webhook_checkoutpro.php - Dedicated webhook for CheckoutPro payments
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
error_reporting(E_ALL);

// Validación básica de seguridad del webhook
function validate_webhook_security() {
    // 1. Solo permitir POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // 2. Verificar User-Agent de MercadoPago
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($user_agent) || strpos($user_agent, 'MercadoPago') === false) {
        // Log del intento sospechoso
        error_log("WEBHOOK SECURITY: Invalid User-Agent: $user_agent from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    // 3. Verificar Content-Type
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($content_type, 'application/json') === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid content type']);
        exit;
    }
    
    // 4. Limitar tamaño del body (max 10KB)
    $content_length = $_SERVER['CONTENT_LENGTH'] ?? 0;
    if ($content_length > 10240) {
        http_response_code(413);
        echo json_encode(['error' => 'Payload too large']);
        exit;
    }
    
    return true;
}

// Validar firma del webhook (si está configurada)
function validate_webhook_signature($raw_body, $signature_header = null) {
    // MercadoPago envía la firma en X-Signature
    if (!$signature_header) {
        $signature_header = $_SERVER['HTTP_X_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE_ID'] ?? null;
    }
    
    // Si no hay firma, loggear pero continuar (por compatibilidad)
    if (!$signature_header) {
        error_log("WEBHOOK WARNING: No signature header found");
        return true; // Por ahora permitir sin firma
    }
    
    // Aquí podrías implementar validación de firma si tienes el secret
    // $webhook_secret = 'tu_webhook_secret';
    // $expected_signature = hash_hmac('sha256', $raw_body, $webhook_secret);
    // return hash_equals($expected_signature, $signature_header);
    
    return true; // Por ahora permitir
}

// Database logging instead of file logging
function log_to_db($pdo, $message, $type = 'INFO') {
    try {
        $stmt = $pdo->prepare("INSERT INTO webhook_logs (timestamp, type, message, source) VALUES (NOW(), ?, ?, 'checkoutpro')");
        $stmt->execute([$type, $message]);
    } catch (Exception $e) {
        // Fallback - ignore if table doesn't exist
    }
}

try {
    // 1. VALIDACIONES DE SEGURIDAD INICIALES
    validate_webhook_security();
    
    // Connect to database first for logging
    $db_config = [
        'host' => '192.168.101.93',
        'dbname' => 'bg02',
        'username' => 'BG02',
        'password' => 'St2025#QkcwMg'
    ];
    
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8",
        $db_config['username'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Log información de seguridad
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    log_to_db($pdo, "=== WEBHOOK CHECKOUTPRO INICIADO === IP: $remote_ip, UA: $user_agent");
    
    // Leer body
    $raw_body = file_get_contents('php://input');
    log_to_db($pdo, "Body recibido: " . substr($raw_body, 0, 200));
    
    if (empty($raw_body)) {
        log_to_db($pdo, "ERROR: Body vacío", 'ERROR');
        echo json_encode(['error' => 'Empty body']);
        exit;
    }
    
    // 2. VALIDAR FIRMA DEL WEBHOOK
    if (!validate_webhook_signature($raw_body)) {
        log_to_db($pdo, "ERROR: Firma de webhook inválida", 'SECURITY');
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
    
    // Decodificar JSON
    $body = json_decode($raw_body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_to_db($pdo, "ERROR: JSON inválido - " . json_last_error_msg(), 'ERROR');
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    log_to_db($pdo, "JSON decodificado: " . json_encode($body));
    
    // Extraer datos del webhook - MercadoPago usa dos formatos diferentes:
    // Formato 1 (antiguo): {"type": "payment", "data": {"id": "123"}}
    // Formato 2 (nuevo): {"topic": "payment", "resource": "123"}
    
    $webhook_type = $body['type'] ?? $body['topic'] ?? '';
    $webhook_id = $body['data']['id'] ?? $body['resource'] ?? '';
    
    // Si resource es una URL, extraer el ID
    if (is_string($webhook_id) && strpos($webhook_id, 'http') === 0) {
        // Ejemplo: "https://api.mercadolibre.com/merchant_orders/35002666618"
        $webhook_id = basename(parse_url($webhook_id, PHP_URL_PATH));
        log_to_db($pdo, "ID extraído de URL: $webhook_id");
    }
    
    // 3. VALIDACIONES ESTRICTAS DE DATOS
    if (empty($webhook_id)) {
        log_to_db($pdo, "ERROR: ID vacío - Body completo: " . json_encode($body), 'ERROR');
        echo json_encode(['error' => 'Empty ID']);
        exit;
    }
    
    // Validar que webhook_id sea numérico (formato esperado de MercadoPago)
    if (!is_numeric($webhook_id)) {
        log_to_db($pdo, "ERROR: ID no numérico: $webhook_id", 'SECURITY');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ID format']);
        exit;
    }
    
    // Validar tipo de webhook esperado
    $valid_types = ['payment', 'merchant_order'];
    if (!empty($webhook_type) && !in_array($webhook_type, $valid_types)) {
        log_to_db($pdo, "WARNING: Tipo de webhook no reconocido: $webhook_type", 'WARNING');
        // No salir, pero loggear
    }
    
    log_to_db($pdo, "Tipo: $webhook_type, ID: $webhook_id");
    
    // Cargar configuración para MercadoPago
    require_once __DIR__ . '/../config/config.php';
    
    if (!defined('MP_ACCESS_TOKEN') || empty(MP_ACCESS_TOKEN)) {
        log_to_db($pdo, "ERROR: Token MP no configurado", 'ERROR');
        echo json_encode(['error' => 'MP token not configured']);
        exit;
    }
    
    // Obtener detalles del pago desde MercadoPago
    // Para CheckoutPro, probamos primero merchant_orders y luego payments
    $api_url_merchant = "https://api.mercadopago.com/merchant_orders/$webhook_id";
    $api_url_payment = "https://api.mercadopago.com/v1/payments/$webhook_id";
    
    log_to_db($pdo, "Intentando merchant_orders: $api_url_merchant");
    
    // Primero intentar merchant_orders
    $ch = curl_init($api_url_merchant);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    log_to_db($pdo, "Merchant Orders API HTTP Code: $http_code");
    
    if ($http_code === 200) {
        $payment_data = json_decode($api_response, true);
        log_to_db($pdo, "Merchant Order obtenida correctamente");
        
        // Para merchant_order, buscar el pago dentro de la orden
        $payments = $payment_data['payments'] ?? [];
        if (empty($payments)) {
            log_to_db($pdo, "ERROR: No hay pagos en merchant order", 'ERROR');
            echo json_encode(['error' => 'No payments in merchant order']);
            exit;
        }
        
        // Tomar el primer pago aprobado
        $approved_payment = null;
        foreach ($payments as $payment) {
            if ($payment['status'] === 'approved') {
                $approved_payment = $payment;
                break;
            }
        }
        
        if (!$approved_payment) {
            log_to_db($pdo, "No hay pagos aprobados en merchant order");
            echo json_encode(['message' => 'No approved payments in merchant order']);
            exit;
        }
        
        $status = $approved_payment['status'];
        $external_reference = $payment_data['external_reference'] ?? '';
        $payment_id = $approved_payment['id'] ?? $webhook_id;
        
    } else {
        // Si falla merchant_orders, intentar payments
        log_to_db($pdo, "Merchant Order falló, intentando payments API: $api_url_payment");
        
        $ch = curl_init($api_url_payment);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        log_to_db($pdo, "Payments API HTTP Code: $http_code");
        
        if ($http_code !== 200) {
            log_to_db($pdo, "ERROR: Ambas APIs fallaron - HTTP $http_code - Response: " . substr($api_response, 0, 200), 'ERROR');
            echo json_encode(['error' => "Both APIs failed with HTTP $http_code"]);
            exit;
        }
        
        $payment_data = json_decode($api_response, true);
        $status = $payment_data['status'] ?? '';
        $external_reference = $payment_data['external_reference'] ?? '';
        $payment_id = $webhook_id;
    }
    
    if (json_last_error() !== JSON_ERROR_NONE || empty($payment_data)) {
        log_to_db($pdo, "ERROR: Respuesta API inválida", 'ERROR');
        echo json_encode(['error' => 'Invalid API response']);
        exit;
    }
    
    log_to_db($pdo, "Datos obtenidos - Status: $status, External ref: $external_reference, Payment ID: $payment_id");
    
    if ($status !== 'approved') {
        log_to_db($pdo, "Pago no aprobado - Status: $status");
        echo json_encode(['message' => "Payment status: $status"]);
        exit;
    }
    
    // 4. VALIDACIÓN ROBUSTA DE EXTERNAL_REFERENCE
    if (empty($external_reference)) {
        log_to_db($pdo, "ERROR: External reference vacío", 'ERROR');
        echo json_encode(['error' => 'Empty external reference']);
        exit;
    }
    
    // Validar formato estricto: CP_timestamp_randomstring
    if (!preg_match('/^CP_\d{13}_[a-z0-9]{9}$/', $external_reference)) {
        log_to_db($pdo, "ERROR: Formato de external reference inválido: $external_reference", 'SECURITY');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid external reference format']);
        exit;
    }
    
    // Validar que el timestamp sea razonable (últimos 30 días)
    $parts = explode('_', $external_reference);
    $timestamp = intval($parts[1]);
    $current_time = time() * 1000; // Convertir a milisegundos
    $thirty_days_ago = $current_time - (30 * 24 * 60 * 60 * 1000);
    
    if ($timestamp < $thirty_days_ago || $timestamp > $current_time + 86400000) { // +1 día de tolerancia
        log_to_db($pdo, "ERROR: Timestamp de external reference fuera de rango: $external_reference", 'SECURITY');
        http_response_code(400);
        echo json_encode(['error' => 'External reference timestamp out of range']);
        exit;
    }
    
    log_to_db($pdo, "Pago aprobado - External reference: $external_reference");
    
    // Buscar el pedido previo usando external_reference
    $stmt = $pdo->prepare("SELECT id_pedido, numero_pedido, estado FROM pedidos WHERE external_reference = ?");
    $stmt->execute([$external_reference]);
    $pedido_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pedido_existente) {
        $pedido_id = $pedido_existente['id_pedido'];
        log_to_db($pdo, "✅ Pedido encontrado - ID: $pedido_id, Número: " . $pedido_existente['numero_pedido']);
        
        // Actualizar estado si aún está como temporal
        $current_estado = $pedido_existente['estado'];
        $numero_actual = $pedido_existente['numero_pedido'];
        
        // Si el número es temporal, generar el número definitivo
        if (strpos($numero_actual, 'CP-TEMP-') === 0) {
            // Obtener siguiente número de pedido
            $stmt_max = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(numero_pedido, 3) AS UNSIGNED)) as max_num FROM pedidos WHERE numero_pedido LIKE 'K-%'");
            $stmt_max->execute();
            $max_row = $stmt_max->fetch(PDO::FETCH_ASSOC);
            $next_num = ($max_row['max_num'] ?? 0) + 1;
            $numero_definitivo = 'K-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
            
            // Actualizar con número definitivo
            $update_stmt = $pdo->prepare("UPDATE pedidos SET numero_pedido = ?, estado = 'PREPARACION' WHERE id_pedido = ?");
            $update_stmt->execute([$numero_definitivo, $pedido_id]);
            
            log_to_db($pdo, "✅ Pedido actualizado - Número definitivo: $numero_definitivo, Estado: PREPARACION", 'SUCCESS');
        } else {
            // Solo actualizar estado si es necesario
            if ($current_estado === 'PENDIENTE') {
                $update_stmt = $pdo->prepare("UPDATE pedidos SET estado = 'PREPARACION' WHERE id_pedido = ?");
                $update_stmt->execute([$pedido_id]);
                log_to_db($pdo, "✅ Estado actualizado a PREPARACION", 'SUCCESS');
            } else {
                log_to_db($pdo, "Pedido ya procesado - Estado actual: $current_estado");
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'CheckoutPro payment processed successfully', 
            'pedido_id' => $pedido_id,
            'numero_pedido' => $numero_actual,
            'payment_id' => $payment_id
        ]);
        
    } else {
        log_to_db($pdo, "❌ ERROR: No se encontró pedido con external_reference: $external_reference", 'ERROR');
        
        // Buscar pedidos recientes para debug
        $debug_stmt = $pdo->prepare("SELECT id_pedido, numero_pedido, external_reference, creado_en FROM pedidos ORDER BY creado_en DESC LIMIT 5");
        $debug_stmt->execute();
        $recent_pedidos = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
        log_to_db($pdo, "Últimos 5 pedidos: " . json_encode($recent_pedidos), 'DEBUG');
        
        echo json_encode([
            'error' => 'Order not found', 
            'external_reference' => $external_reference,
            'payment_id' => $payment_id,
            'suggestion' => 'Verify that create_checkoutpro.php saved the order before creating preference'
        ]);
    }
        
} catch (PDOException $e) {
    if (isset($pdo)) {
        log_to_db($pdo, "ERROR BD: " . $e->getMessage(), 'ERROR');
    }
    echo json_encode(['error' => 'Database error']);
} catch (Exception $e) {
    if (isset($pdo)) {
        log_to_db($pdo, "ERROR: " . $e->getMessage(), 'ERROR');
    }
    echo json_encode(['error' => $e->getMessage()]);
}

if (isset($pdo)) {
    log_to_db($pdo, "=== WEBHOOK CHECKOUTPRO FINALIZADO ===");
}
?>