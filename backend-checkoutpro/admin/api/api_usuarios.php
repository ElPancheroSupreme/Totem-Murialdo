<?php
// Habilitar la visualización de errores de PHP para debug
ini_set('display_errors', 'On');
error_reporting(E_ALL);

// Asegurarnos que la respuesta siempre sea JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar todos los errores como JSON
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../config/pdo_connection.php';
require_once '../config/config_paths.php';

function error_response($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    if ($action === 'listar') {
        // Listar todos los usuarios/empleados
        $mostrar_todos = isset($_GET['mostrar_todos']) ? (bool)$_GET['mostrar_todos'] : false;
        
        $sql = "SELECT 
                    e.id_empleado,
                    e.usuario,
                    e.nombre_completo,
                    '' as email,
                    '' as telefono,
                    CASE 
                        WHEN e.id_rol = 1 THEN 'administrador'
                        WHEN e.id_rol = 2 THEN 'supervisor'
                        WHEN e.id_rol = 3 THEN 'empleado'
                        ELSE 'empleado'
                    END as rol,
                    1 as activo,
                    e.creado_en as fecha_creacion,
                    NULL as ultimo_acceso
                FROM empleados e";
        
        $sql .= " ORDER BY e.nombre_completo ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $usuarios,
            'count' => count($usuarios)
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'estadisticas') {
        // Obtener estadísticas de usuarios
        
        // Total de usuarios
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM empleados");
        $stmt->execute();
        $total = $stmt->fetchColumn();
        
        // Usuarios activos (todos por ahora ya que no hay campo de estado)
        $activos = $total;
        
        // Nuevos usuarios este mes
        $stmt = $pdo->prepare("SELECT COUNT(*) as nuevos_mes FROM empleados WHERE creado_en >= DATE_FORMAT(NOW(), '%Y-%m-01')");
        $stmt->execute();
        $nuevos_mes = $stmt->fetchColumn();
        
        // Distribución por roles
        $stmt = $pdo->prepare("SELECT 
            CASE 
                WHEN id_rol = 1 THEN 'administrador'
                WHEN id_rol = 2 THEN 'supervisor'
                WHEN id_rol = 3 THEN 'empleado'
                ELSE 'empleado'
            END as rol, 
            COUNT(*) as cantidad 
            FROM empleados 
            GROUP BY id_rol");
        $stmt->execute();
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total' => (int)$total,
                'activos' => (int)$activos,
                'nuevos_mes' => (int)$nuevos_mes,
                'roles' => $roles
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'obtener') {
        // Obtener un usuario específico
        $id = $_GET['id'] ?? null;
        if (!$id) {
            error_response('ID de usuario requerido');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id_empleado = ?");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            error_response('Usuario no encontrado', 404);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $usuario
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'crear') {
        // Crear nuevo usuario
        $input = json_decode(file_get_contents('php://input'), true);
        
        $usuario = $input['usuario'] ?? '';
        $nombre_completo = $input['nombre_completo'] ?? '';
        $id_rol = $input['id_rol'] ?? 3; // Por defecto empleado
        $password = $input['password'] ?? '';
        
        if (empty($usuario) || empty($nombre_completo) || empty($password)) {
            error_response('Usuario, nombre completo y contraseña son requeridos');
        }
        
        // Verificar si el usuario ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM empleados WHERE usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetchColumn() > 0) {
            error_response('El usuario ya existe');
        }
        
        // Hash de la contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Usar los nombres de campos correctos de la tabla empleados
        $stmt = $pdo->prepare("INSERT INTO empleados (usuario, hash_contraseña, nombre_completo, id_rol) VALUES (?, ?, ?, ?)");
        $resultado = $stmt->execute([$usuario, $password_hash, $nombre_completo, $id_rol]);
        
        if ($resultado) {
            echo json_encode([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'id' => $pdo->lastInsertId()
            ], JSON_UNESCAPED_UNICODE);
        } else {
            error_response('Error al crear usuario');
        }
        
    } elseif ($action === 'editar') {
        // Editar usuario existente
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? null;
        $usuario = $input['usuario'] ?? '';
        $nombre_completo = $input['nombre_completo'] ?? '';
        $id_rol = $input['id_rol'] ?? 2; // Empleado por defecto
        
        if (!$id || empty($usuario) || empty($nombre_completo)) {
            error_response('ID, usuario y nombre completo son requeridos');
        }
        
        // Verificar si el usuario ya existe (excepto el actual)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM empleados WHERE usuario = ? AND id_empleado != ?");
        $stmt->execute([$usuario, $id]);
        if ($stmt->fetchColumn() > 0) {
            error_response('El usuario ya existe');
        }
        
        // Si se proporciona nueva contraseña, la hasheamos
        $params = [$usuario, $nombre_completo, $id_rol, $id];
        $sql = "UPDATE empleados SET usuario = ?, nombre_completo = ?, id_rol = ?, actualizado_en = NOW() WHERE id_empleado = ?";
        
        if (!empty($input['password'])) {
            $hash_password = password_hash($input['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE empleados SET usuario = ?, nombre_completo = ?, hash_contraseña = ?, id_rol = ?, actualizado_en = NOW() WHERE id_empleado = ?";
            $params = [$usuario, $nombre_completo, $hash_password, $id_rol, $id];
        }
        
        $stmt = $pdo->prepare($sql);
        $resultado = $stmt->execute($params);
        
        if ($resultado) {
            echo json_encode([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            error_response('Error al actualizar usuario');
        }
        
    } elseif ($action === 'eliminar') {
        // Eliminar usuario definitivamente
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
        
        if (!$id) {
            error_response('ID de usuario requerido');
        }
        
        $stmt = $pdo->prepare("DELETE FROM empleados WHERE id_empleado = ?");
        $resultado = $stmt->execute([$id]);
        
        if ($resultado) {
            echo json_encode([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            error_response('Error al eliminar usuario');
        }
        
    } elseif ($action === 'cambiar_password') {
        // Cambiar contraseña
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? null;
        $password = $input['password'] ?? '';
        
        if (!$id || empty($password)) {
            error_response('ID y contraseña son requeridos');
        }
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE empleados SET password = ? WHERE id_empleado = ?");
        $resultado = $stmt->execute([$password_hash, $id]);
        
        if ($resultado) {
            echo json_encode([
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            error_response('Error al actualizar contraseña');
        }
        
    } else {
        error_response('Acción no válida');
    }
    
} catch (PDOException $e) {
    error_response('Error de base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_response('Error del servidor: ' . $e->getMessage(), 500);
}
?>
