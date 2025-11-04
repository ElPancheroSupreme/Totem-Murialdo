<?php
// filepath: backend/admin/api/get_ticket_message.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    // Ruta del archivo de configuración
    $configPath = __DIR__ . '/../../config/ticket_config.json';
    
    // Mensaje por defecto
    $mensajePorDefecto = 'TOTEM MURIALDO';
    
    if (!file_exists($configPath)) {
        // Si no existe el archivo, devolver mensaje por defecto
        echo json_encode([
            'success' => true,
            'mensaje' => $mensajePorDefecto,
            'es_defecto' => true
        ]);
        exit;
    }
    
    $contenido = file_get_contents($configPath);
    if ($contenido === false) {
        throw new Exception('Error al leer archivo de configuración');
    }
    
    $config = json_decode($contenido, true);
    if ($config === null) {
        throw new Exception('Error al parsear JSON de configuración');
    }
    
    $mensaje = isset($config['mensaje']) ? trim($config['mensaje']) : $mensajePorDefecto;
    
    if (empty($mensaje)) {
        $mensaje = $mensajePorDefecto;
    }
    
    echo json_encode([
        'success' => true,
        'mensaje' => $mensaje,
        'es_defecto' => ($mensaje === $mensajePorDefecto),
        'fecha_actualizacion' => isset($config['fecha_actualizacion']) ? $config['fecha_actualizacion'] : null
    ]);
    
} catch (Exception $e) {
    error_log("Error al obtener mensaje de ticket: " . $e->getMessage());
    
    // En caso de error, devolver mensaje por defecto
    echo json_encode([
        'success' => true,
        'mensaje' => 'TOTEM MURIALDO',
        'es_defecto' => true,
        'error_interno' => $e->getMessage()
    ]);
}
?>