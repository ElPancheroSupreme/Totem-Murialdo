<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    
    // Crear tabla si no existe
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
    
} catch (PDOException $e) {
    // Si no se puede conectar a la BD, usar PIN por defecto
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['success' => true, 'pin' => '1233']);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare('SELECT valor FROM config_global WHERE clave = ? LIMIT 1');
        $stmt->execute(['admin_pin']);
        $row = $stmt->fetch();
        $pin = $row ? $row['valor'] : '1233'; // PIN por defecto
        echo json_encode(['success' => true, 'pin' => $pin]);
    } catch (Exception $e) {
        // Si hay error, usar PIN por defecto
        echo json_encode(['success' => true, 'pin' => '1233']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos']);
            exit;
        }
        
        $newPin = isset($data['pin']) ? trim($data['pin']) : '';
        
        if ($newPin === '') {
            echo json_encode(['success' => false, 'error' => 'PIN vacío']);
            exit;
        }
        
        // Verificar si el registro existe
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM config_global WHERE clave = ?');
        $stmt->execute(['admin_pin']);
        $exists = $stmt->fetch()['count'] > 0;
        
        if ($exists) {
            // Actualizar registro existente
            $stmt = $pdo->prepare('UPDATE config_global SET valor = ? WHERE clave = ?');
            $stmt->execute([$newPin, 'admin_pin']);
        } else {
            // Insertar nuevo registro
            $stmt = $pdo->prepare('INSERT INTO config_global (clave, valor) VALUES (?, ?)');
            $stmt->execute(['admin_pin', $newPin]);
        }
        
        echo json_encode(['success' => true, 'message' => 'PIN actualizado correctamente']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar PIN']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
?>