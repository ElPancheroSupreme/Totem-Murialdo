<?php
// Deshabilitar la visualización de errores de PHP
ini_set('display_errors', 'Off');
error_reporting(0);

// Asegurarnos que la respuesta siempre sea JSON
header('Content-Type: application/json');

// Manejar todos los errores como JSON
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    exit;
});

require_once('../config/db_connection.php');

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
$usuario = $data['usuario'] ?? '';
$password = $data['password'] ?? '';

if (empty($usuario) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Usuario y contraseña son requeridos']);
    exit;
}

try {
    $conn = getConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Preparar consulta
    $stmt = $conn->prepare("
        SELECT e.id_empleado, e.nombre_completo, e.hash_contraseña,  
               r.nombre AS rol, r.id_rol
        FROM empleados e
        JOIN roles r ON e.id_rol = r.id_rol
        WHERE e.usuario = ?
    ");
    
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Usuario o contraseña incorrectos']);
        exit;
    }
    
    $empleado = $result->fetch_assoc();
    
    // Verificar contraseña
    if (!password_verify($password, $empleado['hash_contraseña'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Usuario o contraseña incorrectos']);
        exit;
    }
    
    // Iniciar sesión
    session_start();
    
    // Guardar datos en sesión
    $_SESSION['empleado'] = [
        'id_empleado' => $empleado['id_empleado'],
        'nombre_completo' => $empleado['nombre_completo'],
        'rol' => $empleado['rol'],
        'id_rol' => $empleado['id_rol']
    ];
    
    // Devolver respuesta exitosa
    echo json_encode([
        'success' => true,
        'empleado' => [
            'id_empleado' => $empleado['id_empleado'],
            'nombre_completo' => $empleado['nombre_completo'],
            'rol' => $empleado['rol'],
            'id_rol' => $empleado['id_rol']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
