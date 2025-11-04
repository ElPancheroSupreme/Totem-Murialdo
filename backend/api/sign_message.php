<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Obtener el mensaje a firmar
$request = $_GET['request'] ?? '';

if (empty($request)) {
    http_response_code(400);
    echo json_encode(['error' => 'No request parameter provided']);
    exit;
}

// Buscar la clave privada en múltiples ubicaciones posibles
$possiblePaths = [
    __DIR__ . '/../config/private-key.pem',           // Ubicación configurada
    __DIR__ . '/../../config/private-key.pem',       // Un nivel más arriba
    __DIR__ . '/../private-key.pem',                 // Directorio backend
    __DIR__ . '/private-key.pem',                    // Directorio actual
    $_SERVER['DOCUMENT_ROOT'] . '/backend/config/private-key.pem',  // Desde root
    $_SERVER['DOCUMENT_ROOT'] . '/config/private-key.pem',          // Config en root
];

$privateKeyPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $privateKeyPath = $path;
        break;
    }
}

if (!$privateKeyPath) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Private key file not found',
        'searched_paths' => $possiblePaths,
        'current_dir' => __DIR__
    ]);
    exit;
}

$privateKey = file_get_contents($privateKeyPath);

if ($privateKey === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not read private key file: ' . $privateKeyPath]);
    exit;
}

try {
    // Cargar la clave privada
    $key = openssl_pkey_get_private($privateKey);
    
    if ($key === false) {
        throw new Exception('Could not load private key');
    }
    
    // Firmar el mensaje con SHA512
    $signature = '';
    $success = openssl_sign($request, $signature, $key, OPENSSL_ALGO_SHA512);
    
    // Liberar la clave de la memoria
    openssl_pkey_free($key);
    
    if (!$success) {
        throw new Exception('Could not sign message');
    }
    
    // Codificar la firma en base64
    $signatureBase64 = base64_encode($signature);
    
    // Devolver la firma con información adicional
    echo json_encode([
        'signature' => $signatureBase64,
        'success' => true,
        'key_path' => basename($privateKeyPath),
        'message_length' => strlen($request),
        'signature_length' => strlen($signatureBase64)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Signing failed: ' . $e->getMessage(),
        'key_path' => $privateKeyPath ?? 'not found',
        'openssl_available' => extension_loaded('openssl'),
        'current_dir' => __DIR__
    ]);
}
?>
