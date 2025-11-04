<?php
// setup_webhook_logs.php - Create webhook logs table if needed
header('Content-Type: application/json');

try {
    // Connect to database
    $db_config = [
        'host' => '192.168.101.93',
        'dbname' => 'bg02',
        'username' => 'BG02',
        'password' => 'St2025#QkcwMg'
    ];
    
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8",
        $db_config['username'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "1. Conectado a la base de datos\n";
    
    // Check if webhook_logs table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'webhook_logs'");
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
        echo "2. Creando tabla webhook_logs...\n";
        
        $create_sql = "
        CREATE TABLE webhook_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME NOT NULL,
            type VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            source VARCHAR(50) NOT NULL,
            INDEX idx_timestamp (timestamp),
            INDEX idx_source (source)
        ) ENGINE=InnoDB;
        ";
        
        $pdo->exec($create_sql);
        echo "✓ Tabla webhook_logs creada exitosamente\n";
    } else {
        echo "2. ✓ Tabla webhook_logs ya existe\n";
    }
    
    // Test inserting a log entry
    $stmt = $pdo->prepare("INSERT INTO webhook_logs (timestamp, type, message, source) VALUES (NOW(), ?, ?, ?)");
    $stmt->execute(['TEST', 'Configuración de webhook logs completada', 'setup']);
    
    echo "3. ✓ Test de inserción exitoso\n";
    
    // Show recent log entries
    $stmt = $pdo->query("SELECT * FROM webhook_logs ORDER BY timestamp DESC LIMIT 5");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "4. Últimos logs registrados:\n";
    foreach ($logs as $log) {
        echo "   [{$log['timestamp']}] {$log['type']}: {$log['message']} (source: {$log['source']})\n";
    }
    
    echo "\n✅ Configuración completada exitosamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>