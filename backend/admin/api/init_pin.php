<?php
// Script para inicializar el PIN de administrador en la base de datos
// Ejecutar una sola vez para establecer el PIN por defecto

try {
    require_once __DIR__ . '/../../config/pdo_connection.php';
    $pdo = get_pdo_connection();
    
    // Verificar si ya existe un PIN
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM config_global WHERE clave = ?');
    $stmt->execute(['admin_pin']);
    $exists = $stmt->fetch()['count'] > 0;
    
    if (!$exists) {
        // Insertar PIN por defecto
        $defaultPin = '1233';
        $stmt = $pdo->prepare('INSERT INTO config_global (clave, valor) VALUES (?, ?)');
        $stmt->execute(['admin_pin', $defaultPin]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'PIN por defecto inicializado correctamente',
            'pin' => $defaultPin
        ]);
    } else {
        // Obtener PIN actual
        $stmt = $pdo->prepare('SELECT valor FROM config_global WHERE clave = ?');
        $stmt->execute(['admin_pin']);
        $currentPin = $stmt->fetch()['valor'];
        
        echo json_encode([
            'success' => true, 
            'message' => 'PIN ya existe en la base de datos',
            'pin' => $currentPin
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>