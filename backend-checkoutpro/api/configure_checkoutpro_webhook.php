<?php
// configure_checkoutpro_webhook.php - Configurar webhook para CheckoutPro
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Cargar configuración
    require_once __DIR__ . '/../config/config.php';
    
    // Leer datos de la petición
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $webhook_url = $data['url'] ?? 'https://ilm2025.webhop.net/Totem_Murialdo/backend/api/webhook.php';
    
    // Usar credenciales específicas de CheckoutPro
    $credentials = get_mp_credentials(true);
    $access_token = $credentials['access_token'];
    
    if (empty($access_token)) {
        throw new Exception('Credenciales de CheckoutPro no configuradas');
    }
    
    // Test de conectividad primero
    $test_url = "https://api.mercadopago.com/users/me";
    $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Error de autenticación con MercadoPago: HTTP $http_code");
    }
    
    $user_data = json_decode($response, true);
    
    // Para aplicaciones de CheckoutPro, intentar configurar webhook usando diferentes endpoints
    
    // Método 1: Endpoint estándar de aplicaciones
    $endpoints_to_try = [
        "https://api.mercadopago.com/v1/webhooks",
        "https://api.mercadopago.com/webhooks",
        "https://api.mercadopago.com/v1/applications/{$credentials['client_id']}/webhooks"
    ];
    
    $webhook_configured = false;
    $results = [];
    
    foreach ($endpoints_to_try as $endpoint) {
        $webhook_data = [
            'url' => $webhook_url,
            'events' => [
                ['topic' => 'payment'],
                ['topic' => 'merchant_order']
            ]
        ];
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $results[] = [
            'endpoint' => $endpoint,
            'http_code' => $http_code,
            'response' => substr($response, 0, 200)
        ];
        
        if ($http_code === 201 || $http_code === 200) {
            $webhook_configured = true;
            break;
        }
    }
    
    if ($webhook_configured) {
        echo json_encode([
            'success' => true,
            'message' => 'Webhook configurado exitosamente',
            'user' => $user_data['email'] ?? 'Usuario TEST',
            'endpoint_used' => $endpoint,
            'webhook_url' => $webhook_url
        ]);
    } else {
        // En ambiente TEST, esto es normal. Ofrecer alternativa
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo configurar webhook automáticamente (normal en TEST)',
            'user' => $user_data['email'] ?? 'Usuario TEST',
            'alternative' => 'Los webhooks en ambiente TEST tienen limitaciones. Se implementará verificación por polling.',
            'results' => $results,
            'next_step' => 'implement_polling'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>