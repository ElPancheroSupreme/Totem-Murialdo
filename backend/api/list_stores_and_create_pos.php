<?php
// Listar stores existentes para poder usar uno
header('Content-Type: application/json');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

try {
    \MercadoPago\MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
    
    echo json_encode(['step' => 1, 'message' => 'Listando stores existentes'], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Listar stores del usuario
    $stores_url = "https://api.mercadopago.com/users/" . MP_USER_ID . "/stores";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $stores_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $stores = json_decode($response, true);
    
    echo json_encode([
        'step' => 2,
        'message' => 'Stores obtenidos',
        'http_code' => $http_code,
        'stores' => $stores
    ], JSON_UNESCAPED_UNICODE) . "\n";
    
    if ($http_code === 200 && isset($stores['results']) && count($stores['results']) > 0) {
        // Hay stores existentes, usar el primero
        $first_store = $stores['results'][0];
        $store_id = $first_store['id'];
        
        echo json_encode([
            'step' => 3,
            'message' => 'Store encontrado, creando POS',
            'store_id' => $store_id,
            'store_name' => $first_store['name']
        ], JSON_UNESCAPED_UNICODE) . "\n";
        
        // Crear POS usando store existente
        $pos_data = [
            'name' => 'Totem Murialdo POS',
            'external_id' => MP_EXTERNAL_POS_ID,
            'store_id' => $store_id
        ];
        
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
            'step' => 4,
            'message' => 'Resultado creación POS con store existente',
            'http_code' => $pos_http_code,
            'result' => $pos_result
        ], JSON_UNESCAPED_UNICODE) . "\n";
        
        if ($pos_http_code === 201 && isset($pos_result['id'])) {
            echo json_encode([
                'success' => true,
                'message' => 'POS creado exitosamente usando store existente',
                'pos_id' => $pos_result['id'],
                'external_id' => $pos_result['external_id'],
                'store_used' => $store_id
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'error' => 'Error creando POS con store existente',
                'http_code' => $pos_http_code,
                'response' => $pos_result
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } else {
        echo json_encode([
            'step' => 3,
            'message' => 'No hay stores existentes',
            'recommendation' => 'Necesitas crear un store desde el panel web de Mercado Pago',
            'stores_count' => isset($stores['results']) ? count($stores['results']) : 0
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
