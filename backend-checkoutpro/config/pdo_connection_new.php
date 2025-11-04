<?php
// Configuración de la base de datos para producción
try {
    // Para debugging, habilitar errores temporalmente si es necesario
    // ini_set('display_errors', 1);
    // error_reporting(E_ALL);
    
    // Configuración de la base de datos
    define('DB_HOST', '192.168.101.93');
    define('DB_NAME', 'bg02');
    define('DB_USER', 'BG02');
    define('DB_PASS', 'St2025#QkcwMg');
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    // Crear conexión PDO con opciones adicionales
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::ATTR_TIMEOUT => 30
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch(PDOException $e) {
    // Log el error específico para debugging
    error_log("Error de conexión PDO: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error de conexión a la base de datos',
        'debug' => $e->getMessage() // Solo para debugging, remover en producción
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Función para obtener conexión (mantener compatibilidad)
function get_pdo_connection() {
    global $pdo;
    return $pdo;
}
?>
