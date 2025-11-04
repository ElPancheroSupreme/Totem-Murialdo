<?php
// create_checkoutpro.php - API para crear preferencias de CheckoutPro
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log para debug
function log_debug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] CHECKOUTPRO: $message\n";
    @file_put_contents(__DIR__ . '/checkoutpro_debug.log', $entry, FILE_APPEND | LOCK_EX);
}

log_debug("=== INICIO CREATE CHECKOUTPRO ===");

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

    log_debug("Datos recibidos: " . json_encode($data));

    // Validar datos requeridos
    if (empty($data['items']) || empty($data['external_reference'])) {
        throw new Exception('Faltan datos requeridos (items o external_reference)');
    }

    // Cargar configuración de MercadoPago
    require_once __DIR__ . '/../config/config.php';
    
    // Usar credenciales específicas de CheckoutPro
    $credentials = get_mp_credentials(true);
    $access_token = $credentials['access_token'];
    
    if (empty($access_token) || $access_token === 'TEST-4841458334764350-090119-your-test-access-token-here') {
        throw new Exception('Credenciales de CheckoutPro no configuradas. Configura MP_CHECKOUTPRO_ACCESS_TOKEN en config.php');
    }

    // Preparar datos para la preferencia
    $preference_data = [
        'items' => $data['items'],
        'external_reference' => $data['external_reference'],
        'back_urls' => $data['back_urls'],
        'auto_return' => $data['auto_return'] ?? 'approved',
        'payment_methods' => $data['payment_methods'] ?? [],
        'notification_url' => $data['notification_url'] ?? null,
        'expires' => false, // No expira automáticamente
        'purpose' => 'wallet_purchase'
    ];

    log_debug("Datos preparados para MP: " . json_encode($preference_data));
    log_debug("Notification URL enviada: " . ($preference_data['notification_url'] ?? 'NULL'));

    // Llamar a la API de MercadoPago
    $mp_url = 'https://api.mercadopago.com/checkout/preferences';
    
    $ch = curl_init($mp_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preference_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception("Error cURL: $curl_error");
    }

    log_debug("MP HTTP Code: $http_code");
    log_debug("MP Response: " . substr($response, 0, 500));

    if ($http_code !== 201) {
        throw new Exception("MercadoPago API error. HTTP: $http_code. Response: " . substr($response, 0, 200));
    }

    $mp_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || empty($mp_data['id'])) {
        throw new Exception('Respuesta inválida de MercadoPago');
    }

    // Extraer datos importantes
    $preference_id = $mp_data['id'];
    $init_point = $mp_data['init_point'];
    $sandbox_init_point = $mp_data['sandbox_init_point'] ?? null;

    log_debug("Preferencia creada exitosamente - ID: $preference_id");

    // Responder con éxito
    echo json_encode([
        'success' => true,
        'preference_id' => $preference_id,
        'init_point' => $init_point,
        'sandbox_init_point' => $sandbox_init_point,
        'external_reference' => $data['external_reference']
    ]);

} catch (Exception $e) {
    log_debug("ERROR: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

log_debug("=== FIN CREATE CHECKOUTPRO ===");
?>