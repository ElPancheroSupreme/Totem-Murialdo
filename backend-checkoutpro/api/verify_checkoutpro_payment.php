<?php
// verify_checkoutpro_payment.php - Verificación de pagos CheckoutPro sin webhooks
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function log_verification($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] VERIFY_CP: $message\n";
    
    // Intentar múltiples ubicaciones para el log
    $log_paths = [
        __DIR__ . '/verify_checkoutpro.log',
        __DIR__ . '/../logs/verify_cp.log',
        '/tmp/verify_cp.log'
    ];
    
    foreach ($log_paths as $log_path) {
        @file_put_contents($log_path, $entry, FILE_APPEND | LOCK_EX);
    }
}

function log_webhook($pdo, $message, $type = 'INFO') {
    try {
        $stmt = $pdo->prepare("INSERT INTO webhook_logs (timestamp, type, message, source) VALUES (NOW(), ?, ?, 'verify_cp')");
        $stmt->execute([$type, $message]);
    } catch (Exception $e) {
        // Ignore if table doesn't exist
    }
}

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Leer datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }

    // Validar datos requeridos
    $external_reference = $data['external_reference'] ?? '';
    $preference_id = $data['preference_id'] ?? '';
    
    if (empty($external_reference) && empty($preference_id)) {
        throw new Exception('Se requiere external_reference o preference_id');
    }

    log_verification("Verificando pago - External ref: $external_reference, Preference: $preference_id");

    // Conectar a la base de datos
    $pdo = new PDO('mysql:host=192.168.101.93;dbname=bg02;charset=utf8mb4', 'BG02', 'St2025#QkcwMg');
    
    log_webhook($pdo, "Verificando pago - External ref: $external_reference, Preference: $preference_id");

    // Cargar configuración de MercadoPago
    require_once __DIR__ . '/../config/config.php';
    $credentials = get_mp_credentials(true);
    $access_token = $credentials['access_token'];
    
    // Si tenemos preference_id pero no external_reference, buscar en MercadoPago
    if ($preference_id && empty($external_reference)) {
        log_verification("Buscando external_reference para preference: $preference_id");
        
        // Buscar preference para obtener external_reference
        $pref_url = "https://api.mercadopago.com/checkout/preferences/$preference_id";
        
        $ch = curl_init($pref_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $pref_data = json_decode($response, true);
            $external_reference = $pref_data['external_reference'] ?? '';
            log_verification("External reference encontrado: $external_reference");
        }
    }
    
    if (empty($external_reference)) {
        log_verification("ERROR: No se pudo obtener external_reference", 'ERROR');
        echo json_encode(['success' => false, 'message' => 'No external reference found']);
        exit;
    }
    
    // Buscar archivo de orden previa (sistema actual del webhook)
    $ordenes_dirs = [
        __DIR__ . '/../ordenes_status',
        __DIR__ . '/ordenes_status',
        '/tmp/ordenes_status'
    ];
    
    $orden_file = null;
    $orden_data = null;
    
    foreach ($ordenes_dirs as $dir) {
        $pre_file = $dir . "/orden_{$external_reference}_pre.json";
        if (file_exists($pre_file)) {
            $orden_file = $pre_file;
            $orden_data = json_decode(file_get_contents($pre_file), true);
            log_verification("Archivo de orden encontrado: $pre_file");
            break;
        }
    }
    
    if (!$orden_data) {
        log_verification("ERROR: No se encontró archivo de orden para external_reference: $external_reference", 'ERROR');
        echo json_encode(['success' => false, 'message' => 'Order file not found']);
        exit;
    }
    
    // Verificar si ya fue procesado (tiene numero_pedido_final)
    if (!empty($orden_data['numero_pedido_final'])) {
        log_verification("Orden ya procesada - Número de pedido: {$orden_data['numero_pedido_final']}");
        
        // Buscar en base de datos por numero_pedido para verificar estado
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE numero_pedido = ?");
        $stmt->execute([$orden_data['numero_pedido_final']]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pedido) {
            echo json_encode([
                'success' => true,
                'status' => 'already_paid',
                'message' => 'Order already processed and saved',
                'order_id' => $pedido['id'],
                'numero_pedido' => $pedido['numero_pedido']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'status' => 'processed_but_not_found',
                'message' => 'Order marked as processed but not found in database'
            ]);
        }
        exit;
    }
    
    // USAR SISTEMA ROBUSTO OPTIMIZADO
    log_verification("Usando checker robusto para external_reference: $external_reference");
    
    // Obtener payment_id de datos previos (más eficiente)
    $payment_id = $orden_data['payment_id'] ?? null;
    $payment_found = false;
    
    if ($payment_id) {
        log_verification("Payment ID encontrado en datos locales: $payment_id");
        
        // Verificar directamente el payment específico (más rápido que buscar)
        $payment_url = "https://api.mercadopago.com/v1/payments/$payment_id";
        
        $ch = curl_init($payment_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Más rápido
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $payment_data = json_decode($response, true);
            $payment_status = $payment_data['status'] ?? 'unknown';
            
            log_verification("Payment status: $payment_status");
            
            if ($payment_status === 'approved') {
                $payment_found = true;
                log_verification("✅ Pago aprobado confirmado: $payment_id");
            }
        } else {
            log_verification("Error consultando payment API: HTTP $http_code");
        }
    } else {
        log_verification("No hay payment_id en datos locales - buscando en MercadoPago...");
        
        // Fallback: buscar en merchant orders (método anterior)
        $search_url = "https://api.mercadopago.com/merchant_orders/search?external_reference=$external_reference";
        
        $ch = curl_init($search_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $search_data = json_decode($response, true);
            $merchant_orders = $search_data['results'] ?? [];
            
            log_verification("Encontradas " . count($merchant_orders) . " merchant orders");
            
            // Buscar pagos aprobados en las merchant orders
            foreach ($merchant_orders as $order) {
                $payments = $order['payments'] ?? [];
                foreach ($payments as $payment) {
                    if ($payment['status'] === 'approved') {
                        $payment_found = true;
                        $payment_id = $payment['id'];
                        log_verification("Pago aprobado encontrado: $payment_id");
                        break 2;
                    }
                }
            }
        }
    }
    
    if ($payment_found) {
        // ¡PROCESAR EL PAGO! - Usar la misma lógica que el webhook
        log_verification("✅ Pago aprobado encontrado - Procesando orden...");
        
        // Incluir la lógica de guardado del webhook
        require_once __DIR__ . '/guardar_pedido.php';
        
        // Preparar datos para guardar_pedido.php siguiendo la estructura del webhook
        $carrito = $orden_data['carrito'] ?? [];
        $total = array_sum(array_map(function($item) {
            return ($item['precio'] ?? 0) * ($item['cantidad'] ?? 1);
        }, $carrito));
        
        $guardar_data = [
            'carrito' => $carrito,
            'total' => $total,
            'metodo_pago' => 'checkoutpro',
            'external_reference' => $external_reference,
            'payment_id' => $payment_id
        ];
        
        // Simular llamada interna a guardar_pedido.php
        $guardar_response = guardar_pedido_interno($guardar_data);
        
        if ($guardar_response['success']) {
            $numero_pedido = $guardar_response['numero_pedido'];
            
            // Actualizar archivo de orden como procesado
            $orden_data['numero_pedido_final'] = $numero_pedido;
            $orden_data['payment_id'] = $payment_id;
            $orden_data['fecha_procesamiento'] = date('Y-m-d H:i:s');
            @file_put_contents($orden_file, json_encode($orden_data, JSON_PRETTY_PRINT));
            
            log_verification("✅ Orden procesada exitosamente - Número: $numero_pedido, Payment ID: $payment_id", 'SUCCESS');
            log_webhook($pdo, "✅ Orden procesada por verificación - Número: $numero_pedido, Payment ID: $payment_id", 'SUCCESS');
            
            echo json_encode([
                'success' => true,
                'status' => 'payment_verified',
                'message' => 'Payment verified and order processed successfully',
                'order_id' => $guardar_response['id_pedido'],
                'numero_pedido' => $numero_pedido,
                'payment_id' => $payment_id
            ]);
        } else {
            log_verification("ERROR: Falló el guardado del pedido: " . ($guardar_response['error'] ?? 'Unknown error'), 'ERROR');
            echo json_encode([
                'success' => false,
                'error' => 'Payment found but failed to save order: ' . ($guardar_response['error'] ?? 'Unknown error')
            ]);
        }
    } else {
        log_verification("No se encontraron pagos aprobados para external_reference: $external_reference");
        echo json_encode([
            'success' => true,
            'status' => 'payment_pending',
            'message' => 'No approved payment found yet'
        ]);
    }

} catch (Exception $e) {
    log_verification("ERROR: " . $e->getMessage(), 'ERROR');
    if (isset($pdo)) {
        log_webhook($pdo, "ERROR: " . $e->getMessage(), 'ERROR');
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>