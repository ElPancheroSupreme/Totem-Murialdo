<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Error handling simple
set_error_handler(function($severity, $message, $file, $line) {
    throw new Exception("Error: $message in $file on line $line");
});

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Solo POST permitido');
    }

    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Body vacío');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON error: ' . json_last_error_msg());
    }

    if (empty($data['external_reference'])) {
        throw new Exception('external_reference requerido');
    }

    $external_reference = $data['external_reference'];
    $timestamp = date('Y-m-d H:i:s');
    
    // Agregar timestamp
    $data['saved_at'] = $timestamp;
    
    // Intentar directorio principal
    $dir1 = __DIR__ . '/../ordenes_status';
    $dir2 = dirname(__DIR__) . '/ordenes_status';
    $dir3 = '/tmp/ordenes_status';
    
    $success = false;
    $used_dir = '';
    
    foreach ([$dir1, $dir2, $dir3] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        
        if (is_dir($dir) && is_writable($dir)) {
            $filename = "orden_{$external_reference}_pre.json";
            $filepath = $dir . '/' . $filename;
            
            $result = @file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
            if ($result !== false) {
                $success = true;
                $used_dir = $dir;
                break;
            }
        }
    }
    
    if (!$success) {
        throw new Exception('No se pudo escribir en ningún directorio');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Guardado exitoso',
        'external_reference' => $external_reference,
        'directory' => $used_dir,
        'timestamp' => $timestamp
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>