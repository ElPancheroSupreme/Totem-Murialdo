<?php
// Configuración de la base de datos
if (!defined('DB_HOST')) {
    define('DB_HOST', '192.168.101.93');
    define('DB_NAME', 'bg02');
    define('DB_USER', 'BG02');
    define('DB_PASS', 'St2025#QkcwMg');
}

// Función para obtener conexión PDO
function get_pdo_connection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        
        // Configurar el modo de error para que lance excepciones
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Asegurar que los resultados se devuelvan como arrays asociativos
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return $pdo;
        
    } catch(PDOException $e) {
        throw new Exception('Error de conexión a la base de datos: ' . $e->getMessage());
    }
}

// Crear conexión global para compatibilidad con código existente
try {
    $pdo = get_pdo_connection();
} catch(Exception $e) {
    // No usar exit aquí, dejar que el archivo que incluye esto maneje el error
    error_log("Error en pdo_connection.php: " . $e->getMessage());
    // La variable $pdo será null si hay error
    $pdo = null;
}
?>
