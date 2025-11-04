<?php
// Crear punto de venta (POS) en Mercado Pago
header('Content-Type: application/json');

// Cargar dependencias y configuración
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

try {
    // Configurar SDK
    \MercadoPago\MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
    
    $user_id = MP_USER_ID;
    $external_pos_id = MP_EXTERNAL_POS_ID;
    
    // Primero verificar si el POS ya existe
    $get_url = "https://api.mercadopago.com/pos";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $get_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $existing_pos = json_decode($response, true);
    
    echo json_encode([
        'step' => 1,
        'message' => 'Verificando POS existentes',
        'http_code' => $http_code,
        'existing_pos' => $existing_pos
    ], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Buscar si ya existe nuestro POS
    $pos_exists = false;
    if ($http_code === 200 && isset($existing_pos['results'])) {
        foreach ($existing_pos['results'] as $pos) {
            if ($pos['external_id'] === $external_pos_id) {
                $pos_exists = true;
                echo json_encode([
                    'step' => 2,
                    'message' => 'POS ya existe',
                    'pos_data' => $pos
                ], JSON_UNESCAPED_UNICODE) . "\n";
                break;
            }
        }
    }
    
    if (!$pos_exists) {
        // Crear nuevo POS
        echo json_encode(['step' => 3, 'message' => 'Creando nuevo POS'], JSON_UNESCAPED_UNICODE) . "\n";
        
        $create_url = "https://api.mercadopago.com/pos";
        $pos_data = [
            'name' => 'Totem Murialdo - Kiosco Test',
            'category' => 621, // MCC válido para entorno de prueba (servicios financieros)
            'external_store_id' => 'store_test_001',
            'external_id' => $external_pos_id,
            'store_id' => 'totem_murialdo_test'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $create_url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($pos_data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . MP_ACCESS_TOKEN
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $create_response = curl_exec($ch);
        $create_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $create_result = json_decode($create_response, true);
        
        echo json_encode([
            'step' => 4,
            'message' => 'Resultado creación POS',
            'http_code' => $create_http_code,
            'result' => $create_result
        ], JSON_UNESCAPED_UNICODE) . "\n";
        
        if ($create_http_code === 201) {
            echo json_encode([
                'success' => true,
                'message' => 'POS creado exitosamente',
                'pos_id' => $create_result['id'],
                'external_id' => $create_result['external_id']
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'error' => 'Error al crear POS',
                'http_code' => $create_http_code,
                'response' => $create_result
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'POS ya existe, no necesita creación'
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
