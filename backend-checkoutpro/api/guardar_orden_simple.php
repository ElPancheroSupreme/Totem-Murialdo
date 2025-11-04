<?php
// guardar_orden_simple.php - Versión simplificada para guardar órdenes previas
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Solo método POST permitido');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

    $external_reference = $data['external_reference'] ?? '';
    if (empty($external_reference)) {
        throw new Exception('external_reference requerido');
    }

    // Crear directorio
    $ordenes_dir = __DIR__ . '/../ordenes_status';
    if (!is_dir($ordenes_dir)) {
        if (!mkdir($ordenes_dir, 0777, true)) {
            throw new Exception('No se pudo crear directorio ordenes_status');
        }
    }

    // Guardar archivo
    $filename = "orden_{$external_reference}_pre.json";
    $filepath = $ordenes_dir . '/' . $filename;
    
    // Agregar timestamp
    $data['timestamp_guardado'] = date('Y-m-d H:i:s');
    
    $result = file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        throw new Exception('Error escribiendo archivo');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Orden guardada exitosamente',
        'filename' => $filename,
        'path' => $filepath
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => __LINE__,
            'time' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>