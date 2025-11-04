<?php
// backend/admin/api/pin_admin.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../config/pdo_connection.php';
    $pdo = get_pdo_connection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $stmt = $pdo->prepare('SELECT valor FROM config_global WHERE clave = ? LIMIT 1');
            $stmt->execute(['admin_pin']);
            $row = $stmt->fetch();
            $pin = $row ? $row['valor'] : '';
            echo json_encode(['success' => true, 'pin' => $pin]);
            exit;
        } catch (Exception $e) {
            error_log("Error GET PIN: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al obtener PIN']);
            exit;
        }
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
            exit;
            
        } catch (Exception $e) {
            error_log("Error POST PIN: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error al guardar PIN: ' . $e->getMessage()]);
            exit;
        }
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    
} catch (Exception $e) {
    error_log("Error general PIN: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
