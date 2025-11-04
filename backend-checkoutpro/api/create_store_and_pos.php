<?php
// Crear Store y POS completo para QR Dinámico
header('Content-Type: application/json');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

try {
    \MercadoPago\MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
    
    $external_pos_id = MP_EXTERNAL_POS_ID;
    $external_store_id = 'store_totem_murialdo_001';
    
    echo json_encode(['step' => 1, 'message' => 'Creando store primero'], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Paso 1: Crear Store
    $store_data = [
        'name' => 'Totem Murialdo Store',
        'business_hours' => [
            'monday' => [['open' => '09:00', 'close' => '18:00']],
            'tuesday' => [['open' => '09:00', 'close' => '18:00']],
            'wednesday' => [['open' => '09:00', 'close' => '18:00']],
            'thursday' => [['open' => '09:00', 'close' => '18:00']],
            'friday' => [['open' => '09:00', 'close' => '18:00']]
        ],
        'location' => [
            'address_line' => 'Calle Ejemplo 123',
            'reference' => 'Colegio Murialdo',
            'latitude' => -34.6037,
            'longitude' => -58.3816
        ],
        'external_id' => $external_store_id
    ];
    
    $store_url = "https://api.mercadopago.com/users/" . MP_USER_ID . "/stores";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $store_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($store_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MP_ACCESS_TOKEN
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $store_response = curl_exec($ch);
    $store_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $store_result = json_decode($store_response, true);
    
    echo json_encode([
        'step' => 2,
        'message' => 'Resultado creación Store',
        'http_code' => $store_http_code,
        'result' => $store_result
    ], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Determinar store_id para usar en POS
    $store_id = null;
    if ($store_http_code === 201 && isset($store_result['id'])) {
        $store_id = $store_result['id'];
        echo json_encode(['step' => 3, 'message' => 'Store creado con ID: ' . $store_id], JSON_UNESCAPED_UNICODE) . "\n";
    } elseif ($store_http_code === 400 && isset($store_result['message']) && strpos($store_result['message'], 'already exists') !== false) {
        echo json_encode(['step' => 3, 'message' => 'Store ya existe, continuando con POS'], JSON_UNESCAPED_UNICODE) . "\n";
        // Store ya existe, usar external_store_id
    } else {
        echo json_encode(['step' => 3, 'message' => 'Error creando store, intentando POS solo con external_store_id'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    // Paso 2: Crear POS
    echo json_encode(['step' => 4, 'message' => 'Creando POS'], JSON_UNESCAPED_UNICODE) . "\n";
    
    $pos_data = [
        'name' => 'Totem Murialdo - Kiosco QR',
        'external_id' => $external_pos_id,
        'external_store_id' => $external_store_id
    ];
    
    // Si tenemos store_id, agregarlo
    if ($store_id) {
        $pos_data['store_id'] = $store_id;
    }
    
    $pos_url = "https://api.mercadopago.com/pos";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $pos_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($pos_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MP_ACCESS_TOKEN
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $pos_response = curl_exec($ch);
    $pos_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $pos_result = json_decode($pos_response, true);
    
    echo json_encode([
        'step' => 5,
        'message' => 'Resultado creación POS',
        'http_code' => $pos_http_code,
        'result' => $pos_result
    ], JSON_UNESCAPED_UNICODE) . "\n";
    
    if ($pos_http_code === 201 && isset($pos_result['id'])) {
        echo json_encode([
            'success' => true,
            'message' => 'POS creado exitosamente',
            'pos_id' => $pos_result['id'],
            'external_id' => $pos_result['external_id'],
            'qr_template_id' => $pos_result['qr']['template_id'] ?? 'No disponible',
            'next_step' => 'Ahora puedes probar back.php'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'error' => 'Error al crear POS',
            'http_code' => $pos_http_code,
            'response' => $pos_result,
            'store_creation' => $store_http_code === 201 ? 'exitosa' : 'falló'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>
