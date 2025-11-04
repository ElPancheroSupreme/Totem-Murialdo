<?php
// Iniciar output buffering para evitar salida prematura
ob_start();

// Configuración de errores más segura para producción
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
error_reporting(E_ALL);

// Función de logging para depuración
function logDebug($message, $data = null) {
    $logFile = __DIR__ . '/debug_productos.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= ' - Data: ' . print_r($data, true);
    }
    $logMessage .= "\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Limpiar cualquier salida previa
ob_clean();

// Asegurarnos que la respuesta siempre sea JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar todos los errores como JSON
set_exception_handler(function($e) {
    ob_clean(); // Limpiar cualquier salida previa
    logDebug('Exception capturada', [
        'mensaje' => $e->getMessage(),
        'archivo' => $e->getFile(),
        'linea' => $e->getLine()
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
    exit;
});

set_error_handler(function($severity, $message, $file, $line) {
    // Solo loggear errores críticos, ignorar warnings menores
    if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
        logDebug('Error PHP crítico', [
            'severidad' => $severity,
            'mensaje' => $message,
            'archivo' => $file,
            'linea' => $line
        ]);
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return true; // Suprimir errores menores
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../config/pdo_connection.php';
require_once '../config/config_paths.php';

// Función para asegurar que existe el directorio base de imágenes
function ensureImagesDirectory() {
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images';
    if (!is_dir($baseDir)) {
        if (!@mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
            return false;
        }
    }
    return realpath($baseDir);
}

function error_response($msg, $code = 400) {
    // Limpiar cualquier salida previa
    if (ob_get_level()) {
        ob_clean();
    }
    
    logDebug('Enviando error_response', ['mensaje' => $msg, 'codigo' => $code]);
    
    // Asegurar headers JSON
    header('Content-Type: application/json');
    http_response_code($code);
    
    $response = json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    echo $response;
    
    logDebug('Error response enviado', $response);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

function get_categoria_nombre($pdo, $id_categoria) {
    $stmt = $pdo->prepare('SELECT nombre FROM categorias WHERE id_categoria = ?');
    $stmt->execute([$id_categoria]);
    $row = $stmt->fetch();
    return $row ? $row['nombre'] : 'SinCategoria';
}

function get_punto_venta_nombre($pdo, $id_punto_venta) {
    $stmt = $pdo->prepare('SELECT nombre FROM puntos_venta WHERE id_punto_venta = ?');
    $stmt->execute([$id_punto_venta]);
    $row = $stmt->fetch();
    return $row ? $row['nombre'] : 'Buffet';
}

try {
    if ($action === 'listar') {
        // Permitir ver todos los productos (incluyendo desactivados) con parámetro especial
        $mostrar_todos = isset($_GET['mostrar_todos']) && $_GET['mostrar_todos'] === '1';
        
        if ($mostrar_todos) {
            $stmt = $pdo->query('SELECT p.*, c.nombre as categoria_nombre, pv.nombre as punto_venta_nombre, pr.nombre_empresa as proveedor_nombre, pp.id_proveedor 
                                FROM productos p 
                                LEFT JOIN categorias c ON p.id_categoria = c.id_categoria 
                                LEFT JOIN puntos_venta pv ON p.id_punto_venta = pv.id_punto_venta 
                                LEFT JOIN producto_proveedor pp ON p.id_producto = pp.id_producto
                                LEFT JOIN proveedores pr ON pp.id_proveedor = pr.id_proveedor
                                ORDER BY p.estado DESC, p.id_producto DESC');
        } else {
            $stmt = $pdo->query('SELECT p.*, c.nombre as categoria_nombre, pv.nombre as punto_venta_nombre, pr.nombre_empresa as proveedor_nombre, pp.id_proveedor 
                                FROM productos p 
                                LEFT JOIN categorias c ON p.id_categoria = c.id_categoria 
                                LEFT JOIN puntos_venta pv ON p.id_punto_venta = pv.id_punto_venta 
                                LEFT JOIN producto_proveedor pp ON p.id_producto = pp.id_producto
                                LEFT JOIN proveedores pr ON pp.id_proveedor = pr.id_proveedor
                                WHERE p.estado = 1 
                                ORDER BY p.id_producto DESC');
        }
        
        $productos = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $productos], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'obtener') {
        $id = $_GET['id'] ?? $_POST['id'] ?? null;
        if (!$id) error_response('ID requerido');
        $stmt = $pdo->prepare('SELECT * FROM productos WHERE id_producto = ?');
        $stmt->execute([$id]);
        $producto = $stmt->fetch();
        if (!$producto) error_response('Producto no encontrado', 404);
        $stmt2 = $pdo->prepare('SELECT * FROM opciones_personalizacion WHERE id_producto = ?');
        $stmt2->execute([$id]);
        $producto['opciones_personalizacion'] = $stmt2->fetchAll();
        echo json_encode(['success' => true, 'data' => $producto], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'crear') {
        logDebug('Iniciando creación de producto', $_POST);
        
        $nombre = $_POST['nombre'] ?? null;
        $precio_venta = $_POST['precio_venta'] ?? null;
        $precio_lista = $_POST['precio_lista'] ?? null;
        $id_categoria = $_POST['id_categoria'] ?? null;
        $id_punto_venta = $_POST['id_punto_venta'] ?? null;
        $es_personalizable = isset($_POST['es_personalizable']) ? 1 : 0;
        $estado = 1;
        $url_imagen = null;
        
        // Validaciones básicas de campos requeridos
        if (!$nombre || trim($nombre) === '') {
            logDebug('Error: Nombre requerido');
            error_response('El nombre del producto es requerido.');
        }
        if (!$precio_venta || !is_numeric($precio_venta) || $precio_venta <= 0) {
            logDebug('Error: Precio inválido', $precio_venta);
            error_response('El precio de venta debe ser un número mayor a 0.');
        }
        if (!$id_categoria || !is_numeric($id_categoria)) {
            logDebug('Error: Categoría inválida', $id_categoria);
            error_response('Debe seleccionar una categoría válida.');
        }
        if (!$id_punto_venta || !is_numeric($id_punto_venta)) {
            logDebug('Error: Punto de venta inválido', $id_punto_venta);
            error_response('Debe seleccionar un punto de venta válido.');
        }
        // Imagen
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            
            // Validaciones básicas
            if (!$nombre || !$id_categoria || !$id_punto_venta) {
                error_response('Debe proporcionar nombre, categoría y punto de venta antes de subir imagen.');
            }
            
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
            
            // Usar validación por extensión y MIME type si está disponible
            $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($extension, $allowedExtensions)) {
                logDebug('Error: Extensión de archivo no permitida', $extension);
                error_response('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF, WEBP');
            }
            
            // Validación adicional por MIME type si finfo está disponible
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
                finfo_close($finfo);
                
                logDebug('Tipo de archivo detectado', $fileType);
                
                if (!in_array($fileType, $allowedTypes)) {
                    logDebug('Error: Tipo MIME no permitido', $fileType);
                    error_response('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF, WEBP');
                }
            } else {
                // Fallback: usar mime_content_type si está disponible
                if (function_exists('mime_content_type')) {
                    $fileType = mime_content_type($_FILES['imagen']['tmp_name']);
                    logDebug('Tipo de archivo detectado (mime_content_type)', $fileType);
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        logDebug('Error: Tipo MIME no permitido', $fileType);
                        error_response('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF, WEBP');
                    }
                } else {
                    // Solo validación por extensión
                    logDebug('Usando solo validación por extensión de archivo');
                }
            }
            
            // Validar tamaño (máximo 5MB)
            if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
                logDebug('Error: Archivo demasiado grande', $_FILES['imagen']['size']);
                error_response('El archivo es demasiado grande. Máximo 5MB');
            }
            
            $categoria_nombre = get_categoria_nombre($pdo, $id_categoria);
            $punto_venta_nombre = get_punto_venta_nombre($pdo, $id_punto_venta);
            
            logDebug('Nombres obtenidos', ['categoria' => $categoria_nombre, 'punto_venta' => $punto_venta_nombre]);
            
            // Sanitizar nombres para crear directorios seguros
            $categoria_sanitizada = preg_replace('/[^A-Za-z0-9_\-]/', '_', $categoria_nombre);
            $punto_venta_sanitizado = preg_replace('/[^A-Za-z0-9_\-]/', '_', $punto_venta_nombre);
            
            $base_dir = ($punto_venta_sanitizado === 'Kiosco' ? 'Kiosco' : 'Buffet') . DIRECTORY_SEPARATOR . $categoria_sanitizada;
            $abs_base_dir = ensureImagesDirectory();
            
            logDebug('Directorio base', $abs_base_dir);
            
            if (!$abs_base_dir) {
                logDebug('Error: No se pudo crear directorio base');
                error_response('No se pudo encontrar o crear el directorio de imágenes.');
            }
            
            $abs_base_dir = $abs_base_dir . DIRECTORY_SEPARATOR . $base_dir;
            
            logDebug('Directorio completo', $abs_base_dir);
            
            if (!is_dir($abs_base_dir)) {
                if (!@mkdir($abs_base_dir, 0755, true)) {
                    logDebug('Error: No se pudo crear subdirectorio');
                    error_response('No se pudo crear el directorio para las imágenes.');
                }
                logDebug('Subdirectorio creado exitosamente');
            }
            
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $nombre_archivo = strtolower(trim($nombre));
            $nombre_archivo = preg_replace('/\s+/', '_', $nombre_archivo);
            $nombre_archivo = preg_replace('/[^a-z0-9_-]/', '', $nombre_archivo);
            
            if (empty($nombre_archivo)) {
                $nombre_archivo = 'producto_' . uniqid();
            }
            
            $fileName = $nombre_archivo . '.' . $ext;
            $abs_dest = $abs_base_dir . DIRECTORY_SEPARATOR . $fileName;
            
            logDebug('Archivo destino', $abs_dest);
            
            // Si el archivo ya existe, agregar un número
            $counter = 1;
            while (file_exists($abs_dest)) {
                $fileName = $nombre_archivo . '_' . $counter . '.' . $ext;
                $abs_dest = $abs_base_dir . DIRECTORY_SEPARATOR . $fileName;
                $counter++;
            }
            
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $abs_dest)) {
                // Obtener información específica del error
                $error_details = [];
                $error_details[] = 'Archivo temporal: ' . $_FILES['imagen']['tmp_name'];
                $error_details[] = 'Destino: ' . $abs_dest;
                $error_details[] = 'Directorio existe: ' . (is_dir(dirname($abs_dest)) ? 'SÍ' : 'NO');
                $error_details[] = 'Directorio escribible: ' . (is_writable(dirname($abs_dest)) ? 'SÍ' : 'NO');
                $error_details[] = 'Archivo temporal existe: ' . (file_exists($_FILES['imagen']['tmp_name']) ? 'SÍ' : 'NO');
                
                $last_error = error_get_last();
                if ($last_error) {
                    $error_details[] = 'Último error PHP: ' . $last_error['message'];
                }
                
                error_response('Error al guardar la imagen: ' . implode(' | ', $error_details));
            }
            
            // Construir URL relativa (siempre usar barras normales para URLs web)
            $base_dir_url = str_replace(DIRECTORY_SEPARATOR, '/', $base_dir);
            $url_imagen = '/Totem_Murialdo/frontend/assets/images/' . $base_dir_url . '/' . $fileName;
            logDebug('URL imagen generada', $url_imagen);
        }
        $stmt = $pdo->prepare('INSERT INTO productos (nombre, precio_venta, precio_lista, url_imagen, es_personalizable, id_punto_venta, id_categoria, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$nombre, $precio_venta, $precio_lista, $url_imagen, $es_personalizable, $id_punto_venta, $id_categoria, $estado]);
        $id_producto = $pdo->lastInsertId();
        
        logDebug('Producto insertado con ID', $id_producto);
        
        // Opciones de personalización
        if (!empty($_POST['opciones_personalizacion'])) {
            $opciones = json_decode($_POST['opciones_personalizacion'], true);
            if (is_array($opciones)) {
                $stmtOpt = $pdo->prepare('INSERT INTO opciones_personalizacion (id_producto, nombre_opcion, precio_extra) VALUES (?, ?, ?)');
                foreach ($opciones as $op) {
                    $stmtOpt->execute([$id_producto, $op['nombre_opcion'], $op['precio_extra']]);
                }
                logDebug('Opciones de personalización insertadas', count($opciones));
            }
        }
        
        // Limpiar buffer antes de respuesta final
        if (ob_get_level()) {
            ob_clean();
        }
        
        logDebug('Producto creado exitosamente, enviando respuesta JSON');
        
        // Asegurar headers correctos
        header('Content-Type: application/json');
        
        $response = json_encode(['success' => true, 'id_producto' => $id_producto], JSON_UNESCAPED_UNICODE);
        echo $response;
        
        logDebug('Respuesta enviada', $response);
        exit;
    }
    if ($action === 'actualizar') {
        $id = $_GET['id'] ?? $_POST['id'] ?? null;
        if (!$id) error_response('ID requerido');
        $nombre = $_POST['nombre'] ?? null;
        $precio_venta = $_POST['precio_venta'] ?? null;
        $precio_lista = $_POST['precio_lista'] ?? null;
        $id_categoria = $_POST['id_categoria'] ?? null;
        $id_punto_venta = $_POST['id_punto_venta'] ?? null;
        $es_personalizable = isset($_POST['es_personalizable']) ? 1 : 0;
        $url_imagen = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            // Validaciones básicas
            if (!$nombre || !$id_categoria || !$id_punto_venta) {
                error_response('Debe proporcionar nombre, categoría y punto de venta antes de subir imagen.');
            }
            
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
            
            // Usar validación por extensión y MIME type si está disponible
            $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($extension, $allowedExtensions)) {
                error_response('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF, WEBP');
            }
            
            // Validación adicional por MIME type si finfo está disponible
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($fileType, $allowedTypes)) {
                    error_response('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF, WEBP');
                }
            } else {
                // Fallback: usar mime_content_type si está disponible
                if (function_exists('mime_content_type')) {
                    $fileType = mime_content_type($_FILES['imagen']['tmp_name']);
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        error_response('Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF, WEBP');
                    }
                }
            }
            
            // Validar tamaño (máximo 5MB)
            if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
                error_response('El archivo es demasiado grande. Máximo 5MB');
            }
            
            $categoria_nombre = get_categoria_nombre($pdo, $id_categoria);
            $punto_venta_nombre = get_punto_venta_nombre($pdo, $id_punto_venta);
            
            // Sanitizar nombres para crear directorios seguros
            $categoria_sanitizada = preg_replace('/[^A-Za-z0-9_\-]/', '_', $categoria_nombre);
            $punto_venta_sanitizado = preg_replace('/[^A-Za-z0-9_\-]/', '_', $punto_venta_nombre);
            
            $base_dir = ($punto_venta_sanitizado === 'Kiosco' ? 'Kiosco' : 'Buffet') . DIRECTORY_SEPARATOR . $categoria_sanitizada;
            $abs_base_dir = ensureImagesDirectory();
            
            if (!$abs_base_dir) {
                error_response('No se pudo encontrar o crear el directorio de imágenes.');
            }
            
            $abs_base_dir = $abs_base_dir . DIRECTORY_SEPARATOR . $base_dir;
            
            if (!is_dir($abs_base_dir)) {
                if (!@mkdir($abs_base_dir, 0755, true)) {
                    error_response('No se pudo crear el directorio para las imágenes.');
                }
            }
            
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $nombre_archivo = strtolower(trim($nombre));
            $nombre_archivo = preg_replace('/\s+/', '_', $nombre_archivo);
            $nombre_archivo = preg_replace('/[^a-z0-9_-]/', '', $nombre_archivo);
            
            if (empty($nombre_archivo)) {
                $nombre_archivo = 'producto_' . uniqid();
            }
            
            $fileName = $nombre_archivo . '.' . $ext;
            $abs_dest = $abs_base_dir . DIRECTORY_SEPARATOR . $fileName;
            
            // Si el archivo ya existe, agregar un número
            $counter = 1;
            while (file_exists($abs_dest)) {
                $fileName = $nombre_archivo . '_' . $counter . '.' . $ext;
                $abs_dest = $abs_base_dir . DIRECTORY_SEPARATOR . $fileName;
                $counter++;
            }
            
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $abs_dest)) {
                error_response('Error al guardar la imagen en el servidor.');
            }
            
            // Construir URL relativa (siempre usar barras normales para URLs web)
            $base_dir_url = str_replace(DIRECTORY_SEPARATOR, '/', $base_dir);
            $url_imagen = '/Totem_Murialdo/frontend/assets/images/' . $base_dir_url . '/' . $fileName;
        }
        $sql = 'UPDATE productos SET nombre=?, precio_venta=?, precio_lista=?, es_personalizable=?, id_punto_venta=?, id_categoria=?';
        $params = [$nombre, $precio_venta, $precio_lista, $es_personalizable, $id_punto_venta, $id_categoria];
        if ($url_imagen) {
            $sql .= ', url_imagen=?';
            $params[] = $url_imagen;
        }
        $sql .= ' WHERE id_producto=?';
        $params[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pdo->prepare('DELETE FROM opciones_personalizacion WHERE id_producto=?')->execute([$id]);
        if (!empty($_POST['opciones_personalizacion'])) {
            $opciones = json_decode($_POST['opciones_personalizacion'], true);
            if (is_array($opciones)) {
                $stmtOpt = $pdo->prepare('INSERT INTO opciones_personalizacion (id_producto, nombre_opcion, precio_extra) VALUES (?, ?, ?)');
                foreach ($opciones as $op) {
                    $stmtOpt->execute([$id, $op['nombre_opcion'], $op['precio_extra']]);
                }
            }
        }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'eliminar') {
        $id = $_GET['id'] ?? $_POST['id'] ?? null;
        if (!$id) error_response('ID requerido');
        
        try {
            // Verificar si el producto tiene pedidos asociados
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM items_pedido WHERE id_producto = ?');
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                // Si tiene pedidos asociados, realizar eliminación lógica
                $stmt = $pdo->prepare('UPDATE productos SET estado = 0, nombre = CONCAT(nombre, " (ELIMINADO)") WHERE id_producto = ?');
                $stmt->execute([$id]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Producto desactivado. No se puede eliminar completamente porque tiene pedidos asociados.'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // Si no tiene pedidos asociados, eliminación física
                // Obtener la ruta de la imagen antes de borrar el producto
                $stmt = $pdo->prepare('SELECT url_imagen FROM productos WHERE id_producto=?');
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if ($row && !empty($row['url_imagen'])) {
                    $img_path = $_SERVER['DOCUMENT_ROOT'] . $row['url_imagen'];
                    if (file_exists($img_path)) {
                        @unlink($img_path);
                    }
                }
                $pdo->prepare('DELETE FROM opciones_personalizacion WHERE id_producto=?')->execute([$id]);
                $pdo->prepare('DELETE FROM productos WHERE id_producto=?')->execute([$id]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Producto eliminado completamente.'
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (PDOException $e) {
            // Si hay error de base de datos, intentar eliminación lógica como fallback
            if (strpos($e->getMessage(), '1451') !== false || strpos($e->getMessage(), 'foreign key constraint') !== false) {
                try {
                    $stmt = $pdo->prepare('UPDATE productos SET estado = 0, nombre = CONCAT(nombre, " (ELIMINADO)") WHERE id_producto = ?');
                    $stmt->execute([$id]);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Producto desactivado debido a restricciones de base de datos.'
                    ], JSON_UNESCAPED_UNICODE);
                } catch (PDOException $e2) {
                    error_response('Error de base de datos: ' . $e2->getMessage());
                }
            } else {
                error_response('Error de base de datos: ' . $e->getMessage());
            }
        }
        exit;
    }
    if ($action === 'categorias') {
        $stmt = $pdo->query('SELECT * FROM categorias ORDER BY nombre');
        $categorias = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $categorias], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'puntos_venta') {
        $stmt = $pdo->query('SELECT * FROM puntos_venta ORDER BY nombre');
        $puntos = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $puntos], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'toggle_activo') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        if (!$id) error_response('ID requerido');
        $stmt = $pdo->prepare('UPDATE productos SET estado = IF(estado=1,0,1) WHERE id_producto=?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'activar') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        if (!$id) error_response('ID requerido');
        $stmt = $pdo->prepare('UPDATE productos SET estado = 1 WHERE id_producto=?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'desactivar') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        if (!$id) error_response('ID requerido');
        $stmt = $pdo->prepare('UPDATE productos SET estado = 0 WHERE id_producto=?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($action === 'restaurar') {
        $id = $_GET['id'] ?? $_POST['id'] ?? null;
        if (!$id) error_response('ID requerido');
        
        try {
            // Restaurar producto eliminado
            $stmt = $pdo->prepare('UPDATE productos SET estado = 1, nombre = REPLACE(nombre, " (ELIMINADO)", "") WHERE id_producto = ?');
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Producto restaurado exitosamente.'
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_response('Error de base de datos: ' . $e->getMessage());
        }
        exit;
    }
    if ($action === 'editar_campo') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $campo = $input['campo'] ?? null;
        $valor = $input['valor'] ?? null;
        $permitidos = ['nombre', 'precio_venta', 'precio_lista'];
        if (!$id || !$campo || !in_array($campo, $permitidos)) error_response('Campo no permitido');
        $stmt = $pdo->prepare("UPDATE productos SET $campo=? WHERE id_producto=?");
        $stmt->execute([$valor, $id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'importar') {
        $input = json_decode(file_get_contents('php://input'), true);
        $productos = $input['productos'] ?? [];
        $importados = 0;
        foreach ($productos as $prod) {
            $stmt = $pdo->prepare('INSERT INTO productos (nombre, precio_venta, precio_lista, es_personalizable, id_punto_venta, id_categoria, estado) VALUES (?, ?, ?, ?, (SELECT id_punto_venta FROM puntos_venta WHERE nombre=? LIMIT 1), (SELECT id_categoria FROM categorias WHERE nombre=? LIMIT 1), 1)');
            $stmt->execute([
                $prod['nombre'] ?? '',
                $prod['precio_venta'] ?? 0,
                $prod['precio_lista'] ?? null,
                (isset($prod['es_personalizable']) && ($prod['es_personalizable'] === 'Sí' || $prod['es_personalizable'] === '1')) ? 1 : 0,
                $prod['punto_venta'] ?? '',
                $prod['categoria'] ?? ''
            ]);
            $importados++;
        }
        echo json_encode(['success' => true, 'importados' => $importados], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Default: error
    error_response('Acción no válida', 400);
} catch (PDOException $e) {
    ob_clean(); // Limpiar buffer
    logDebug('Error PDO', $e->getMessage());
    error_response('Error de base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    ob_clean(); // Limpiar buffer
    logDebug('Error general', $e->getMessage());
    error_response('Error interno: ' . $e->getMessage(), 500);
} finally {
    // Asegurar que el buffer se limpia al final
    if (ob_get_level()) {
        ob_end_clean();
    }
}
?>