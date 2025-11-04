<?php
function getConnection() {
    // Deshabilitar la visualización de errores de PHP
    ini_set('display_errors', 'Off');
    error_reporting(0);
    
    // Configuración de la base de datos
    define('DB_HOST', '192.168.101.93');
    define('DB_NAME', 'bg02');
    define('DB_USER', 'BG02');
    define('DB_PASS', 'St2025#QkcwMg');

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Error de conexión a la base de datos");
        }
        
        // Establecer charset
        $conn->set_charset("utf8mb4");
        
        return $conn;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error de conexión al servidor']);
        exit;
    }
}
?>