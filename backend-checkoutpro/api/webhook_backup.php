<?php
// webhook.php - Recibe notificaciones de Mercado Pago y marca la orden como pagada
header('Content-Type: application/json');

// Mostrar errores solo para debug (quitar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar logging mejorado
$log_file = __DIR__ . '/../webhook_debug.log';
$log_dir = dirname($log_file);

// Crear directorio si no existe
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0777, true);
}

// Función para logging seguro
function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    // Intentar escribir siempre, sin verificar is_writable()
    $result = @file_put_contents($log_file, $logEntry, FILE_APPEND | LOCK_EX);
    if ($result === false) {
        // Si falla, intentar escribir en el directorio actual
        $fallback_log = __DIR__ . '/webhook_debug_fallback.log';
        @file_put_contents($fallback_log, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

logMessage("Webhook iniciado");

// Leer el body JSON        
$body = file_get_contents('php://input');
logMessage("Webhook body recibido: " . $body);
$payload = json_decode($body, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    logMessage("Error decodificando JSON: " . json_last_error_msg());
}

// Responder inmediatamente a notificaciones de prueba
if (isset($payload['type']) && $payload['type'] === 'test') {
    logMessage("Notificación de prueba recibida");
    echo json_encode(['success' => true, 'message' => 'Test webhook received successfully']);
    exit;
}

// Detectar merchant_order por topic o type
$isMerchantOrder = false;
$merchant_order_id = null;

if (
    (isset($payload['type']) && (strpos($payload['type'], 'merchant_order') !== false)) ||
    (isset($payload['topic']) && strpos($payload['topic'], 'merchant_order') !== false)
) {
    // Puede venir en data.id o id
    $merchant_order_id = $payload['data']['id'] ?? $payload['id'] ?? null;
    $isMerchantOrder = true;
    
    logMessage("Procesando merchant_order ID: $merchant_order_id");
}

if ($isMerchantOrder && $merchant_order_id) {
    require_once __DIR__ . '/../config/config.php';
    $access_token = MP_ACCESS_TOKEN;
    $url = "https://api.mercadopago.com/merchant_orders/$merchant_order_id";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for development
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disable hostname verification
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    logMessage("API Response: HTTP $http_code - $response");
    if ($curl_error) {
        logMessage("cURL Error: $curl_error");
    }
    
    if ($curl_error) {
        echo json_encode(['error' => 'cURL error: ' . $curl_error]);
        exit;
    }

    $order = json_decode($response, true);

    // NUEVO: Verificar el estado de la orden comercial para QR Dinámico
    if (isset($order['order_status']) && in_array($order['order_status'], ['paid', 'partially_paid'])) {
        $external_reference = $order['external_reference'] ?? null;
        
        logMessage("Orden pagada encontrada: $external_reference con estado: " . $order['order_status']);
        
        if ($external_reference) {
            // Leer datos previos de la orden
            $preFile = __DIR__ . "/../ordenes_status/orden_{$external_reference}_pre.json";
            $orden_data = [];
            if (file_exists($preFile)) {
                $orden_data = json_decode(file_get_contents($preFile), true);
                logMessage("Datos previos encontrados para: $external_reference");
            } else {
                logMessage("ADVERTENCIA: No se encontró archivo previo: $preFile");
            }
            
            // Guardar el estado de la orden como pagada
            $statusFile = __DIR__ . "/../ordenes_status/orden_{$external_reference}.json";
            
            // Obtener fecha y hora en GMT-3 (Argentina)
            $dt = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
            $fecha_pago = $dt->format('Y-m-d H:i:s');
            
            $dataToSave = array_merge($orden_data, [
                'status' => 'approved',
                'external_reference' => $external_reference,
                'fecha_pago' => $fecha_pago,
                'merchant_order_id' => $merchant_order_id,
                'order_status' => $order['order_status']
            ]);
            
            // Crear directorio si no existe
            $ordenes_dir = __DIR__ . "/../ordenes_status";
            if (!is_dir($ordenes_dir)) {
                $mkdir_result = @mkdir($ordenes_dir, 0777, true);
                if ($mkdir_result) {
                    logMessage("Directorio ordenes_status creado");
                } else {
                    logMessage("ERROR: No se pudo crear directorio ordenes_status");
                }
            }
            
            // Intentar guardar el archivo sin verificar permisos
            $write_result = @file_put_contents($statusFile, json_encode($dataToSave, JSON_PRETTY_PRINT));
            if ($write_result !== false) {
                logMessage("Archivo de estado guardado exitosamente: $statusFile (bytes: $write_result)");
            } else {
                logMessage("ERROR: No se pudo guardar el archivo: $statusFile");
                // Intentar en directorio alternativo
                $fallback_file = __DIR__ . "/orden_{$external_reference}.json";
                $fallback_result = @file_put_contents($fallback_file, json_encode($dataToSave, JSON_PRETTY_PRINT));
                if ($fallback_result !== false) {
                    logMessage("Archivo guardado en ubicación alternativa: $fallback_file");
                }
            }
        }
        echo json_encode(['success' => true, 'message' => "Orden $merchant_order_id pagada (QR Dinámico)"]);
        exit;
    } else {
        logMessage("Estado de orden no pagada: " . ($order['order_status'] ?? 'no_status'));
    }
}

// Si no es relevante, responde OK igual
logMessage("Webhook procesado - no se encontró merchant_order pagada");
echo json_encode(['success' => true]);