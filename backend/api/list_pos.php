<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/config.php';

try {
    $accessToken = MP_ACCESS_TOKEN;
    $userId = MP_USER_ID;
    
    echo "=== LISTAR POS EXISTENTES ===\n";
    echo "Access Token: " . substr($accessToken, 0, 20) . "...\n";
    echo "User ID: $userId\n\n";
    
    // Listar POS existentes
    $posUrl = "https://api.mercadopago.com/pos?user_id=$userId";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $posUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "cURL Error: " . ($curlError ?: 'Ninguno') . "\n";
    echo "Response:\n";
    echo $response . "\n\n";
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['results'])) {
        echo "✅ POS encontrados:\n";
        foreach ($responseData['results'] as $pos) {
            echo "- POS ID: " . $pos['id'] . "\n";
            echo "  Nombre: " . $pos['name'] . "\n";
            echo "  External ID: " . $pos['external_id'] . "\n";
            echo "  Store ID: " . $pos['store_id'] . "\n";
            echo "  Status: " . $pos['status'] . "\n";
            echo "  QR Template: " . ($pos['qr']['template_id'] ?? 'No disponible') . "\n";
            echo "  QR Image: " . ($pos['qr']['image'] ?? 'No disponible') . "\n";
            echo "\n";
        }
        
        echo "=== CONFIGURACIÓN RECOMENDADA ===\n";
        if (!empty($responseData['results'])) {
            $primerPOS = $responseData['results'][0];
            echo "En config.php, usa:\n";
            echo "define('MP_EXTERNAL_POS_ID', '" . $primerPOS['external_id'] . "');\n";
            echo "// POS ID: " . $primerPOS['id'] . "\n";
        }
        
    } else {
        echo "❌ Error listando POS o no hay POS creados\n";
        if ($responseData) {
            echo "Detalles: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Excepción: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
?>
