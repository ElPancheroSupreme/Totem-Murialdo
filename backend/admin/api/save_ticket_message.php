<?php
// filepath: backend/admin/api/save_ticket_message.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    // Leer datos del POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['mensaje'])) {
        throw new Exception('Falta el campo "mensaje"');
    }
    
    $mensaje = trim($data['mensaje']);
    
    // Validaciones
    if (empty($mensaje)) {
        throw new Exception('El mensaje no puede estar vacío');
    }
    
    if (strlen($mensaje) > 80) {
        throw new Exception('El mensaje no puede exceder 80 caracteres');
    }
    
    // Sanitizar caracteres problemáticos para impresión térmica
    $mensaje = preg_replace('/[^\x20-\x7E\xC0-\xFF]/', '', $mensaje);
    
    // Ruta del archivo de configuración
    $configPath = __DIR__ . '/../../config/ticket_config.json';
    
    // Preparar datos
    $config = [
        'mensaje' => $mensaje,
        'fecha_actualizacion' => date('Y-m-d H:i:s')
    ];
    
    // Escribir archivo atomically (write temp + rename)
    $tempPath = $configPath . '.tmp';
    
    if (file_put_contents($tempPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new Exception('Error al escribir archivo temporal');
    }
    
    if (!rename($tempPath, $configPath)) {
        unlink($tempPath); // Limpiar temporal si falla rename
        throw new Exception('Error al actualizar archivo de configuración');
    }
    
    // Log del cambio
    error_log("Mensaje de ticket actualizado: '{$mensaje}'");
    
    echo json_encode([
        'success' => true, 
        'mensaje' => 'Mensaje actualizado correctamente',
        'nuevo_mensaje' => $mensaje
    ]);
    
} catch (Exception $e) {
    error_log("Error al guardar mensaje de ticket: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>