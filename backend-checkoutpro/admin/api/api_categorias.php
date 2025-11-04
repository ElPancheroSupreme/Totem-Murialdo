<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir configuración y conexión
require_once '../../config/config.php';
require_once '../../config/pdo_connection.php';

session_start();


// Verificar autenticación y permisos usando la estructura de $_SESSION['empleado']
if (!isset($_SESSION['empleado']) || !isset($_SESSION['empleado']['id_empleado']) || !isset($_SESSION['empleado']['id_rol'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$empleado_id = $_SESSION['empleado']['id_empleado'];
$empleado_rol = $_SESSION['empleado']['id_rol'];

// Verificar permisos (solo admin y supervisor pueden gestionar categorías)
if (!in_array($empleado_rol, [1, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permisos para gestionar categorías']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        case 'PATCH':
            handlePatch();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

function handleGet() {
    global $pdo;
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';
            
            $sql = "SELECT id_categoria, nombre, descripcion, icono, color, visible, orden, 
                           creado_en, modificado_en, eliminado,
                           (SELECT COUNT(*) FROM productos p WHERE p.id_categoria = c.id_categoria AND p.eliminado = 0) as total_productos
                    FROM categorias c";
            
            if (!$includeInactive) {
                $sql .= " WHERE visible = 1";
            }
            
            $sql .= " ORDER BY orden ASC, nombre ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'categorias' => $categorias]);
            break;
            
        case 'stats':
            $stmt = $pdo->prepare("SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN visible = 1 THEN 1 END) as activas,
                COUNT(CASE WHEN visible = 0 THEN 1 END) as inactivas
                FROM categorias");
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
}

function handlePost() {
    global $pdo, $empleado_rol;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos']);
        return;
    }
    
    $nombre = trim($input['nombre'] ?? '');
    $descripcion = trim($input['descripcion'] ?? '');
    $icono = trim($input['icono'] ?? '📦');
    $color = trim($input['color'] ?? '#3B82F6');
    $visible = isset($input['visible']) ? (bool)$input['visible'] : true;
    
    if (empty($nombre)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
        return;
    }
    
    // Verificar que no exista una categoría con el mismo nombre
    $stmt = $pdo->prepare("SELECT id_categoria FROM categorias WHERE nombre = ?");
    $stmt->execute([$nombre]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ya existe una categoría con ese nombre']);
        return;
    }
    
    // Obtener el próximo orden
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(orden), 0) + 1 as siguiente_orden FROM categorias");
    $stmt->execute();
    $orden = $stmt->fetch(PDO::FETCH_ASSOC)['siguiente_orden'];
    
    $stmt = $pdo->prepare("INSERT INTO categorias (nombre, descripcion, icono, color, visible, orden, creado_en, modificado_en) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
    
    if ($stmt->execute([$nombre, $descripcion, $icono, $color, $visible, $orden])) {
        $id_categoria = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id_categoria' => $id_categoria, 'message' => 'Categoría creada exitosamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al crear la categoría']);
    }
}

function handlePut() {
    global $pdo, $empleado_rol;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id_categoria'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de categoría requerido']);
        return;
    }
    
    $id_categoria = $input['id_categoria'];
    $nombre = trim($input['nombre'] ?? '');
    $descripcion = trim($input['descripcion'] ?? '');
    $icono = trim($input['icono'] ?? '📦');
    $color = trim($input['color'] ?? '#3B82F6');
    $visible = isset($input['visible']) ? (bool)$input['visible'] : true;
    
    if (empty($nombre)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
        return;
    }
    
    // Verificar que la categoría existe
    $stmt = $pdo->prepare("SELECT id_categoria FROM categorias WHERE id_categoria = ?");
    $stmt->execute([$id_categoria]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Categoría no encontrada']);
        return;
    }
    
    // Verificar que no exista otra categoría con el mismo nombre
    $stmt = $pdo->prepare("SELECT id_categoria FROM categorias WHERE nombre = ? AND id_categoria != ?");
    $stmt->execute([$nombre, $id_categoria]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ya existe otra categoría con ese nombre']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE categorias 
                          SET nombre = ?, descripcion = ?, icono = ?, color = ?, visible = ?, modificado_en = NOW()
                          WHERE id_categoria = ?");
    
    if ($stmt->execute([$nombre, $descripcion, $icono, $color, $visible, $id_categoria])) {
        echo json_encode(['success' => true, 'message' => 'Categoría actualizada exitosamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al actualizar la categoría']);
    }
}

function handleDelete() {
    global $pdo, $empleado_rol;
    
    // Solo administradores pueden eliminar categorías
    if ($empleado_rol != 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Solo los administradores pueden eliminar categorías']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id_categoria'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de categoría requerido']);
        return;
    }
    
    $id_categoria = $input['id_categoria'];
    
    // Verificar que la categoría existe
    $stmt = $pdo->prepare("SELECT id_categoria, nombre FROM categorias WHERE id_categoria = ?");
    $stmt->execute([$id_categoria]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$categoria) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Categoría no encontrada']);
        return;
    }
    
    // Verificar si tiene productos asociados
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM productos WHERE id_categoria = ? AND eliminado = 0");
    $stmt->execute([$id_categoria]);
    $total_productos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($total_productos > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => "No se puede eliminar la categoría '{$categoria['nombre']}' porque tiene {$total_productos} producto(s) asociado(s). Elimina o reasigna los productos primero."
        ]);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM categorias WHERE id_categoria = ?");
    
    if ($stmt->execute([$id_categoria])) {
        echo json_encode(['success' => true, 'message' => 'Categoría eliminada exitosamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al eliminar la categoría']);
    }
}

function handlePatch() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción requerida']);
        return;
    }
    
    $action = $input['action'];
    
    switch ($action) {
        case 'toggle_visibility':
            $id_categoria = $input['id_categoria'] ?? null;
            
            if (!$id_categoria) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de categoría requerido']);
                return;
            }
            
            $stmt = $pdo->prepare("UPDATE categorias SET visible = NOT visible, modificado_en = NOW() WHERE id_categoria = ?");
            
            if ($stmt->execute([$id_categoria])) {
                // Obtener el nuevo estado
                $stmt = $pdo->prepare("SELECT visible FROM categorias WHERE id_categoria = ?");
                $stmt->execute([$id_categoria]);
                $visible = $stmt->fetch(PDO::FETCH_ASSOC)['visible'];
                
                echo json_encode([
                    'success' => true, 
                    'visible' => (bool)$visible,
                    'message' => 'Visibilidad actualizada exitosamente'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al actualizar visibilidad']);
            }
            break;
            
        case 'reorder':
            $categorias = $input['categorias'] ?? [];
            
            if (empty($categorias)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Lista de categorías requerida']);
                return;
            }
            
            try {
                $pdo->beginTransaction();
                
                foreach ($categorias as $index => $categoria) {
                    $id_categoria = $categoria['id_categoria'];
                    $orden = $index + 1;
                    
                    $stmt = $pdo->prepare("UPDATE categorias SET orden = ?, modificado_en = NOW() WHERE id_categoria = ?");
                    $stmt->execute([$orden, $id_categoria]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Orden actualizado exitosamente']);
                
            } catch (Exception $e) {
                $pdo->rollback();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al actualizar el orden']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
}
?>
