<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/db_connection.php';
require_once '../config/config_paths.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    if (!isset($_FILES['imagen'])) {
        throw new Exception('No se proporcionó ninguna imagen');
    }
    
    $file = $_FILES['imagen'];
    
    // Validar que no hubo errores en la subida
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo: ' . $file['error']);
    }
    
    // Validar tipo de archivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF, WEBP');
    }
    
    // Validar tamaño (máximo 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('El archivo es demasiado grande. Máximo 5MB');
    }
    
    // Determinar el directorio según el punto de venta y la categoría
    $puntoVenta = $_POST['punto_venta'] ?? null;
    $categoria = $_POST['categoria'] ?? null;
    $nombreProducto = $_POST['nombre_producto'] ?? null;
    
    if (!$puntoVenta || !$categoria) {
        throw new Exception('Debe seleccionar punto de venta y categoría para subir la imagen');
    }
    
    // Sanitizar nombres para carpetas
    $puntoVentaFolder = ucfirst(strtolower(trim($puntoVenta))); // Buffet o Kiosco
    $categoriaFolder = ucwords(strtolower(trim($categoria))); // Primera letra mayúscula
    
    $absUploadDir = realpath(__DIR__ . '/../../../frontend/assets/images') . '/' . $puntoVentaFolder . '/' . $categoriaFolder . '/';
    if (!file_exists($absUploadDir)) {
        mkdir($absUploadDir, 0777, true);
    }
    
    // Generar nombre de archivo basado en el nombre del producto
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if ($nombreProducto) {
        // Sanitizar nombre del producto: reemplazar espacios con guiones bajos y quitar caracteres especiales
        $nombreArchivo = strtolower(trim($nombreProducto));
        $nombreArchivo = preg_replace('/\s+/', '_', $nombreArchivo); // espacios a guiones bajos
        $nombreArchivo = preg_replace('/[^a-z0-9_-]/', '', $nombreArchivo); // solo letras, números, guiones y guiones bajos
        $fileName = $nombreArchivo . '.' . $extension;
    } else {
        $fileName = time() . '_' . uniqid() . '.' . $extension;
    }
    
    $targetPath = $absUploadDir . $fileName;
    
    // Si el archivo ya existe, agregar un número al final
    $counter = 1;
    $originalFileName = pathinfo($fileName, PATHINFO_FILENAME);
    while (file_exists($targetPath)) {
        $fileName = $originalFileName . '_' . $counter . '.' . $extension;
        $targetPath = $absUploadDir . $fileName;
        $counter++;
    }
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Construir URL usando buildImageUrl
        $urlRelativa = buildImageUrl($puntoVentaFolder . '/' . $categoriaFolder . '/' . $fileName);
        // Log temporal para depuración
        file_put_contents('debug_upload.log', "POST: " . print_r($_POST, true) . "\nRuta final: $urlRelativa\n", FILE_APPEND);
        echo json_encode([
            'success' => true,
            'url' => $urlRelativa,
            'message' => 'Imagen subida exitosamente'
        ]);
    } else {
        throw new Exception('Error al mover el archivo subido');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 