<?php
/**
 * API para subir iconos SVG de categorías
 * 
 * Endpoint: POST /backend/admin/api/upload_icono_categoria.php
 * 
 * Acepta: multipart/form-data con un archivo SVG
 * Retorna: JSON con la ruta del archivo subido
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir configuración y conexión
require_once '../../config/config.php';

session_start();

// Verificar autenticación
if (!isset($_SESSION['empleado']) || !isset($_SESSION['empleado']['id_empleado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Verificar permisos (solo admin y supervisor pueden subir iconos)
$empleado_rol = $_SESSION['empleado']['id_rol'];
if (!in_array($empleado_rol, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permisos para subir iconos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    // Verificar que se envió un archivo
    if (!isset($_FILES['icono']) || $_FILES['icono']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió ningún archivo o hubo un error en la carga');
    }

    $archivo = $_FILES['icono'];
    $nombre_temporal = $archivo['tmp_name'];
    $nombre_original = $archivo['name'];
    $tamano = $archivo['size'];
    $tipo_mime = $archivo['type'];

    // Validar tamaño (máximo 500KB)
    $tamano_maximo = 500 * 1024; // 500KB
    if ($tamano > $tamano_maximo) {
        throw new Exception('El archivo es demasiado grande. Máximo 500KB permitido');
    }

    // Validar extensión
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    if ($extension !== 'svg') {
        throw new Exception('Solo se permiten archivos SVG');
    }

    // Validar tipo MIME
    $tipos_mime_validos = ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml'];
    if (!in_array($tipo_mime, $tipos_mime_validos)) {
        throw new Exception('Tipo de archivo no válido. Solo se permiten archivos SVG');
    }

    // Validar contenido del archivo (debe ser XML válido y contener <svg>)
    $contenido = file_get_contents($nombre_temporal);
    if ($contenido === false) {
        throw new Exception('No se pudo leer el archivo');
    }

    // Verificar que contiene la etiqueta <svg>
    if (stripos($contenido, '<svg') === false) {
        throw new Exception('El archivo no es un SVG válido');
    }

    // Sanitizar el nombre del archivo
    $nombre_base = pathinfo($nombre_original, PATHINFO_FILENAME);
    $nombre_base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombre_base);
    
    // Generar nombre único con timestamp para evitar colisiones
    $timestamp = time();
    $nombre_archivo = 'Icono_' . $nombre_base . '_' . $timestamp . '.svg';

    // Definir ruta de destino (ruta absoluta desde la raíz del proyecto)
    $directorio_destino = dirname(__DIR__, 3) . '/frontend/assets/images/Iconos/';
    
    // Verificar que el directorio existe, si no, crearlo
    if (!is_dir($directorio_destino)) {
        if (!mkdir($directorio_destino, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de destino');
        }
    }

    $ruta_destino = $directorio_destino . $nombre_archivo;

    // Mover el archivo al destino
    if (!move_uploaded_file($nombre_temporal, $ruta_destino)) {
        throw new Exception('Error al guardar el archivo en el servidor');
    }

    // Establecer permisos del archivo
    chmod($ruta_destino, 0644);

    // Retornar solo el nombre del archivo (no la ruta completa)
    echo json_encode([
        'success' => true,
        'icono' => $nombre_archivo,
        'message' => 'Icono subido exitosamente',
        'size' => $tamano,
        'original_name' => $nombre_original
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
