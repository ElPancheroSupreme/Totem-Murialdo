<?php
// guardar_orden_previa.php - Guardar datos de orden antes del pago
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Desactivar display de errores para que solo retorne JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log para debug
function log_debug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] ORDEN_PREVIA: $message\n";
    
    // Intentar múltiples ubicaciones para el log
    $log_paths = [
        __DIR__ . '/orden_previa_debug.log',
        __DIR__ . '/../logs/orden_previa.log',
        '/tmp/orden_previa.log'
    ];
    
    foreach ($log_paths as $log_path) {
        @file_put_contents($log_path, $entry, FILE_APPEND | LOCK_EX);
    }
}

log_debug("=== INICIO GUARDAR ORDEN PREVIA ===");

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Leer datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }

    log_debug("Datos recibidos: " . json_encode($data));

    // Validar datos requeridos
    if (empty($data['external_reference']) || empty($data['data'])) {
        throw new Exception('Faltan datos requeridos');
    }

    $external_reference = $data['external_reference'];
    $orden_data = $data['data'];

    // Crear directorio si no existe - múltiples ubicaciones
    $ordenes_dirs = [
        __DIR__ . '/../ordenes_status',
        __DIR__ . '/ordenes_status',
        '/tmp/ordenes_status'
    ];
    
    $ordenes_dir = null;
    foreach ($ordenes_dirs as $dir) {
        if (is_dir($dir) || @mkdir($dir, 0777, true)) {
            if (is_writable($dir)) {
                $ordenes_dir = $dir;
                break;
            }
        }
    }
    
    if (!$ordenes_dir) {
        throw new Exception('No se pudo encontrar/crear directorio escribible para órdenes');
    }
    
    log_debug("Usando directorio: $ordenes_dir");

    // Preparar archivo
    $filename = "orden_{$external_reference}_pre.json";
    $filepath = $ordenes_dir . '/' . $filename;

    // Agregar metadatos
    $orden_data['fecha_guardado'] = date('Y-m-d H:i:s');
    $orden_data['ip_cliente'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $orden_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // Guardar archivo
    $json_content = json_encode($orden_data, JSON_PRETTY_PRINT);
    $result = @file_put_contents($filepath, $json_content, LOCK_EX);

    if ($result === false) {
        // Intentar ubicación alternativa
        $alt_filepath = '/tmp/' . $filename;
        $result = @file_put_contents($alt_filepath, $json_content, LOCK_EX);
        
        if ($result === false) {
            throw new Exception('No se pudo guardar el archivo en ninguna ubicación');
        } else {
            log_debug("Archivo guardado en ubicación alternativa: $alt_filepath");
            $filepath = $alt_filepath;
        }
    }

    log_debug("Orden previa guardada exitosamente: $filename");

    // Responder con éxito
    echo json_encode([
        'success' => true,
        'message' => 'Orden previa guardada exitosamente',
        'external_reference' => $external_reference,
        'filename' => $filename
    ]);

} catch (Exception $e) {
    log_debug("ERROR: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => __FILE__,
            'line' => __LINE__,
            'time' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Throwable $e) {
    // Capturar cualquier error fatal
    log_debug("FATAL ERROR: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'debug_info' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

log_debug("=== FIN GUARDAR ORDEN PREVIA ===");
?>