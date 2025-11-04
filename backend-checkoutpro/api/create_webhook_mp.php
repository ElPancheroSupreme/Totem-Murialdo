<?php
// create_webhook_mp.php - Crear webhook programáticamente en MercadoPago
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
    $events = $data['events'] ?? ['payment', 'merchant_order'];
    
    // Primero, listar webhooks existentes
    $list_url = "https://api.mercadopago.com/v1/webhooks";
    $ch = curl_init($list_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $existing_webhooks = [];
    if ($http_code === 200) {
        $webhooks_data = json_decode($response, true);
        $existing_webhooks = $webhooks_data['data'] ?? [];
    }
    
    // Verificar si ya existe un webhook con nuestra URL
    $webhook_exists = false;
    foreach ($existing_webhooks as $webhook) {
        if ($webhook['url'] === $webhook_url) {
            $webhook_exists = true;
            break;
        }
    }
    
    if ($webhook_exists) {
        echo json_encode([
            'success' => true,
            'message' => 'Webhook ya existe',
            'existing_webhooks' => $existing_webhooks
        ]);
        exit;
    }
    
    // Crear nuevo webhook
    $create_url = "https://api.mercadopago.com/v1/webhooks";
    $webhook_data = [
        'url' => $webhook_url,
        'events' => [
            ['topic' => 'payment'],
            ['topic' => 'merchant_order']
        ]
    ];
    
    $ch = curl_init($create_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 201) {
        $created_webhook = json_decode($response, true);
        echo json_encode([
            'success' => true,
            'message' => 'Webhook creado exitosamente',
            'webhook' => $created_webhook,
            'existing_webhooks' => $existing_webhooks
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error creando webhook',
            'http_code' => $http_code,
            'response' => $response,
            'existing_webhooks' => $existing_webhooks
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>