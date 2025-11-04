<?php
// Verificar si QR Dinámico está habilitado en la cuenta
header('Content-Type: application/json');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../config/config.php';

try {
    \MercadoPago\MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
    
    $user_id = MP_USER_ID;
    
    echo json_encode(['step' => 1, 'message' => 'Verificando capacidades de la cuenta'], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Verificar información de la cuenta
    $account_url = "https://api.mercadopago.com/users/me";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $account_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $account_info = json_decode($response, true);
    
    echo json_encode([
        'step' => 2,
        'message' => 'Información de cuenta obtenida',
        'http_code' => $http_code,
        'account_type' => $account_info['user_type'] ?? 'desconocido',
        'country' => $account_info['country_id'] ?? 'desconocido',
        'site' => $account_info['site_id'] ?? 'desconocido'
    ], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Verificar POS existentes
    $pos_url = "https://api.mercadopago.com/pos";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $pos_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MP_ACCESS_TOKEN
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $pos_response = curl_exec($ch);
    $pos_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $pos_data = json_decode($pos_response, true);
    
    echo json_encode([
        'step' => 3,
        'message' => 'Verificación de POS',
        'pos_http_code' => $pos_http_code,
        'pos_access' => $pos_http_code === 200 ? 'PERMITIDO' : 'DENEGADO',
        'existing_pos_count' => $pos_data['paging']['total'] ?? 0
    ], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Verificar capacidades de QR
    if ($pos_http_code === 200) {
        echo json_encode([
            'success' => true,
            'message' => 'QR Dinámico está habilitado',
            'recommendation' => 'Puedes crear POS y generar QRs',
            'next_step' => 'Crear un POS válido'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'warning' => 'QR Dinámico puede no estar habilitado',
            'message' => 'La cuenta no tiene acceso a la API de POS',
            'recommendation' => 'Contacta a Mercado Pago para habilitar QR Dinámico',
            'pos_response' => $pos_data
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
