<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Configuración de la base de datos
$host = '192.168.101.93';
$dbname = 'bg02';
$username = 'BG02';
$password = 'St2025#QkcwMg';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Crear tabla config_global si no existe
    $createTable = "
    CREATE TABLE IF NOT EXISTS config_global (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clave VARCHAR(100) NOT NULL UNIQUE,
        valor TEXT,
        descripcion TEXT,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createTable);
    
    // Insertar PIN por defecto si no existe
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM config_global WHERE clave = 'admin_pin'");
    $stmt->execute();
    $exists = $stmt->fetch()['count'] > 0;
    
    if (!$exists) {
        $stmt = $pdo->prepare("INSERT INTO config_global (clave, valor, descripcion) VALUES ('admin_pin', '1233', 'PIN de administrador del sistema')");
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Tabla creada y PIN inicializado con 1233']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Tabla ya existe', 'setup' => 'ready']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>