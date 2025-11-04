<?php
// webhook_checkoutpro.php - Webhook exclusivo para flujo CheckoutPro

header('Content-Type: application/json');
error_reporting(E_ALL);

// Log específico para CheckoutPro
function checkoutpro_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] CHECKOUTPRO_WEBHOOK: $message\n";
    
    // Logs específicos para CheckoutPro
    $log_files = [
        __DIR__ . '/webhook_checkoutpro.log',
        __DIR__ . '/../logs/checkoutpro_webhook.log'
    ];
    
    foreach ($log_files as $log_file) {
        $dir = dirname($log_file);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
    }
}

try {
    checkoutpro_log("=== WEBHOOK CHECKOUTPRO INICIADO ===");
    
    // Obtener el raw body
    $input = file_get_contents('php://input');
    checkoutpro_log("Raw input recibido: " . substr($input, 0, 500));
    
    // Decodificar JSON
    $data = json_decode($input, true);
    
    if (!$data) {
        checkoutpro_log("ERROR: No se pudo decodificar el JSON");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    checkoutpro_log("JSON decodificado correctamente");
    
    // Verificar que es un pago
    if (!isset($data['type']) || $data['type'] !== 'payment') {
        checkoutpro_log("INFO: No es un pago, tipo: " . ($data['type'] ?? 'undefined'));
        echo json_encode(['status' => 'ok', 'message' => 'Not a payment notification']);
        exit;
    }
    
    $payment_id = $data['data']['id'] ?? null;
    
    if (!$payment_id) {
        checkoutpro_log("ERROR: No se encontró payment_id en el webhook");
        http_response_code(400);
        echo json_encode(['error' => 'No payment ID']);
        exit;
    }
    
    checkoutpro_log("Payment ID: $payment_id");
    
    // Incluir configuración específica de CheckoutPro
    require_once '../config/config.php';
    
    // Verificar el pago con MercadoPago
    $url = "https://api.mercadopago.com/v1/payments/$payment_id";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        checkoutpro_log("ERROR: HTTP $httpCode al consultar pago");
        http_response_code(500);
        echo json_encode(['error' => 'Payment verification failed']);
        exit;
    }
    
    $payment_data = json_decode($response, true);
    
    if (!$payment_data) {
        checkoutpro_log("ERROR: Respuesta de MP no válida");
        http_response_code(500);
        echo json_encode(['error' => 'Invalid MP response']);
        exit;
    }
    
    $status = $payment_data['status'] ?? 'unknown';
    $external_reference = $payment_data['external_reference'] ?? null;
    $payment_method = $payment_data['payment_method_id'] ?? 'unknown';
    
    checkoutpro_log("Status: $status, External reference: $external_reference, Method: $payment_method");
    
    // Solo procesar pagos aprobados
    if ($status === 'approved' && $external_reference) {
        
        // Conectar a la base de datos
        $config_db = require_once '../config/database.php';
        
        try {
            $pdo = new PDO(
                "mysql:host={$config_db['host']};dbname={$config_db['dbname']};charset=utf8",
                $config_db['username'],
                $config_db['password']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Actualizar el estado del pedido
            $stmt = $pdo->prepare("
                UPDATE pedidos 
                SET estado_pago = 'pagado', 
                    payment_id = ?, 
                    fecha_actualizacion = NOW(),
                    metodo_pago = 'CHECKOUTPRO'
                WHERE external_reference = ?
            ");
            
            $stmt->execute([$payment_id, $external_reference]);
            
            if ($stmt->rowCount() > 0) {
                checkoutpro_log("✅ Pedido actualizado exitosamente en BD");
                
                // Crear archivo de estado para el frontend
                $status_data = [
                    'payment_id' => $payment_id,
                    'status' => 'approved',
                    'external_reference' => $external_reference,
                    'timestamp' => time(),
                    'method' => 'CHECKOUTPRO',
                    'payment_method_id' => $payment_method
                ];
                
                $status_file = "../ordenes_status/orden_$external_reference.json";
                $status_dir = dirname($status_file);
                
                if (!file_exists($status_dir)) {
                    mkdir($status_dir, 0755, true);
                }
                
                file_put_contents($status_file, json_encode($status_data, JSON_PRETTY_PRINT));
                checkoutpro_log("✅ Archivo de estado creado: $status_file");
                
            } else {
                checkoutpro_log("⚠️ No se encontró pedido con external_reference: $external_reference");
            }
            
        } catch (Exception $e) {
            checkoutpro_log("ERROR BD: " . $e->getMessage());
        }
    }
    
    checkoutpro_log("=== WEBHOOK CHECKOUTPRO FINALIZADO ===");
    echo json_encode(['status' => 'ok']);
    
} catch (Exception $e) {
    checkoutpro_log("ERROR CRÍTICO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>