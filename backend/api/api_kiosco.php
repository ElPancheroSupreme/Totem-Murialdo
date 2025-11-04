<?php
// Habilitar errores temporalmente para debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Asegurar que siempre enviemos JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once '../config/pdo_connection.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al cargar configuración: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos - PDO no está definido'], JSON_UNESCAPED_UNICODE);
    exit;
}

function error_response($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Deshabilitar errores solo para producción (comentado para debugging)
// error_reporting(0);
// ini_set('display_errors', 0);

// Si no se pasa 'action', devolver productos y categorías agrupados para el frontend del kiosco
if (!$action) {
    // Verificar si se solicita un punto de venta específico
    $punto_venta_filtro = isset($_GET['punto_venta']) ? intval($_GET['punto_venta']) : null;
    
    // 1. Obtener todas las categorías visibles ordenadas, filtradas por punto de venta
    try {
        if ($punto_venta_filtro !== null) {
            // Incluir categorías del punto de venta específico + categorías "Ambos" (id=3)
            $stmt = $pdo->prepare('SELECT * FROM categorias WHERE visible = 1 AND id_punto_venta IN (?, 3) ORDER BY orden ASC, nombre ASC');
            $stmt->execute([$punto_venta_filtro]);
        } else {
            $stmt = $pdo->query('SELECT * FROM categorias WHERE visible = 1 ORDER BY orden ASC, nombre ASC');
        }
        if (!$stmt) {
            throw new Exception('Error al ejecutar consulta de categorías');
        }
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error al obtener categorías: " . $e->getMessage());
        throw new Exception('Error al consultar categorías: ' . $e->getMessage());
    }

    // 2. Obtener todos los productos activos, filtrados por punto de venta si corresponde
    try {
        if ($punto_venta_filtro !== null) {
            // Filtrar por punto de venta específico + productos "Ambos" (id=3)
            // Esto permite que productos marcados como "Ambos" aparezcan en Buffet y Kiosco
            $stmt = $pdo->prepare('SELECT p.*, c.nombre as categoria_nombre FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.estado=1 AND c.visible = 1 AND p.id_punto_venta IN (?, 3) ORDER BY c.orden ASC, c.nombre ASC, p.nombre ASC');
            $stmt->execute([$punto_venta_filtro]);
        } else {
            // Sin filtro, mostrar todos
            $stmt = $pdo->query('SELECT p.*, c.nombre as categoria_nombre FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.estado=1 AND c.visible = 1 ORDER BY c.orden ASC, c.nombre ASC, p.nombre ASC');
        }
        if (!$stmt) {
            throw new Exception('Error al ejecutar consulta de productos');
        }
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error al obtener productos: " . $e->getMessage());
        throw new Exception('Error al consultar productos: ' . $e->getMessage());
    }

    // 3. Agrupar productos por categoría
    $productosPorCategoria = [];
    foreach ($categorias as $cat) {
        $productosPorCategoria[$cat['nombre']] = [];
    }
    foreach ($productos as $prod) {
        $cat = $prod['categoria_nombre'] ?? 'SinCategoria';
        $producto = [
            'id' => $prod['id_producto'],
            'nombre' => $prod['nombre'],
            'precio' => $prod['precio_venta'],
            'imagen' => $prod['url_imagen'],
            'es_personalizable' => $prod['es_personalizable'],
        ];
        // Si el producto es personalizable, agregar las opciones
        if ($prod['es_personalizable'] == 1) {
            $stmt2 = $pdo->prepare('SELECT * FROM opciones_personalizacion WHERE id_producto = ?');
            $stmt2->execute([$prod['id_producto']]);
            $producto['opciones_personalizacion'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        $productosPorCategoria[$cat][] = $producto;
    }

    echo json_encode([
        'success' => true,
        'productos' => $productosPorCategoria,
        'categorias' => $categorias
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
    if ($action === 'get_productos') {
        $stmt = $pdo->query('SELECT * FROM productos');
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'productos' => $productos], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'listar') {
        $stmt = $pdo->query('SELECT p.*, c.nombre as categoria_nombre, pv.nombre as punto_venta_nombre FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria LEFT JOIN puntos_venta pv ON p.id_punto_venta = pv.id_punto_venta ORDER BY p.id_producto DESC');
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
        $nombre = $_POST['nombre'] ?? null;
        $precio_venta = $_POST['precio_venta'] ?? null;
        $precio_lista = $_POST['precio_lista'] ?? null;
        $id_categoria = $_POST['id_categoria'] ?? null;
        $id_punto_venta = $_POST['id_punto_venta'] ?? null;
        $es_personalizable = isset($_POST['es_personalizable']) ? 1 : 0;
        $estado = 1;
        $url_imagen = null;
        // Imagen
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $categoria_nombre = get_categoria_nombre($pdo, $id_categoria);
            $punto_venta_nombre = get_punto_venta_nombre($pdo, $id_punto_venta);
            $categoria_nombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $categoria_nombre);
            $punto_venta_nombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $punto_venta_nombre);
            $base_dir = "Images/" . ($punto_venta_nombre === 'Kiosco' ? 'Kiosco' : 'Buffet') . "/$categoria_nombre";
            $abs_base_dir = __DIR__ . '/' . $base_dir;
            if (!is_dir($abs_base_dir)) {
                mkdir($abs_base_dir, 0777, true);
            }
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $nombre_archivo = isset($nombre) ? strtolower(trim($nombre)) : '';
            $nombre_archivo = preg_replace('/\s+/', '_', $nombre_archivo);
            $nombre_archivo = preg_replace('/[^a-z0-9_-]/', '', $nombre_archivo);
            $fileName = $nombre_archivo ? $nombre_archivo . '.' . $ext : uniqid('prod_') . '.' . $ext;
            $abs_dest = $abs_base_dir . '/' . $fileName;
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $abs_dest)) {
                error_response('Error al guardar la imagen en el servidor.');
            }
            $base_url = dirname($_SERVER['SCRIPT_NAME']);
            $base_url = preg_replace('#/admin$#', '', $base_url);
            $url_imagen = rtrim($base_url, '/') . '/' . ltrim($base_dir . '/' . $fileName, '/');
        }
        $stmt = $pdo->prepare('INSERT INTO productos (nombre, precio_venta, precio_lista, url_imagen, es_personalizable, id_punto_venta, id_categoria, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$nombre, $precio_venta, $precio_lista, $url_imagen, $es_personalizable, $id_punto_venta, $id_categoria, $estado]);
        $id_producto = $pdo->lastInsertId();
        // Opciones de personalización
        if (!empty($_POST['opciones_personalizacion'])) {
            $opciones = json_decode($_POST['opciones_personalizacion'], true);
            if (is_array($opciones)) {
                $stmtOpt = $pdo->prepare('INSERT INTO opciones_personalizacion (id_producto, nombre_opcion, precio_extra) VALUES (?, ?, ?)');
                foreach ($opciones as $op) {
                    $stmtOpt->execute([$id_producto, $op['nombre_opcion'], $op['precio_extra']]);
                }
            }
        }
        echo json_encode(['success' => true, 'id_producto' => $id_producto], JSON_UNESCAPED_UNICODE);
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
            $categoria_nombre = get_categoria_nombre($pdo, $id_categoria);
            $punto_venta_nombre = get_punto_venta_nombre($pdo, $id_punto_venta);
            $categoria_nombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $categoria_nombre);
            $punto_venta_nombre = preg_replace('/[^A-Za-z0-9_\-]/', '_', $punto_venta_nombre);
            $base_dir = "Images/" . ($punto_venta_nombre === 'Kiosco' ? 'Kiosco' : 'Buffet') . "/$categoria_nombre";
            $abs_base_dir = __DIR__ . '/' . $base_dir;
            if (!is_dir($abs_base_dir)) {
                mkdir($abs_base_dir, 0777, true);
            }
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $nombre_archivo = isset($nombre) ? strtolower(trim($nombre)) : '';
            $nombre_archivo = preg_replace('/\s+/', '_', $nombre_archivo);
            $nombre_archivo = preg_replace('/[^a-z0-9_-]/', '', $nombre_archivo);
            $fileName = $nombre_archivo ? $nombre_archivo . '.' . $ext : uniqid('prod_') . '.' . $ext;
            $abs_dest = $abs_base_dir . '/' . $fileName;
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $abs_dest)) {
                error_response('Error al guardar la imagen en el servidor.');
            }
            $base_url = dirname($_SERVER['SCRIPT_NAME']);
            $base_url = preg_replace('#/admin$#', '', $base_url);
            $url_imagen = rtrim($base_url, '/') . '/' . ltrim($base_dir . '/' . $fileName, '/');
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
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'categorias') {
        $punto_venta = isset($_GET['punto_venta']) ? intval($_GET['punto_venta']) : null;
        
        if ($punto_venta !== null) {
            // Incluir categorías del punto de venta específico + categorías "Ambos" (id=3)
            $stmt = $pdo->prepare('SELECT * FROM categorias WHERE id_punto_venta IN (?, 3) ORDER BY orden ASC, nombre ASC');
            $stmt->execute([$punto_venta]);
        } else {
            $stmt = $pdo->query('SELECT * FROM categorias ORDER BY orden ASC, nombre ASC');
        }
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
    error_log("API KIOSCO PDO ERROR: " . $e->getMessage());
    error_response('Error de base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("API KIOSCO GENERAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_response('Error interno: ' . $e->getMessage(), 500);
}
?>
