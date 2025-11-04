<?php
// Verificar tabla config_global
try {
    $host = '192.168.101.93';
    $dbname = 'bg02';
    $username = 'BG02';
    $password = 'St2025#QkcwMg';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si existe la tabla config_global
    $stmt = $pdo->query("SHOW TABLES LIKE 'config_global'");
    if ($stmt->rowCount() > 0) {
        echo "Tabla config_global existe\n";
        // Ver estructura
        $stmt = $pdo->query("DESCRIBE config_global");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "Columna: {$col['Field']} - Tipo: {$col['Type']}\n";
        }
        // Ver contenido actual
        $stmt = $pdo->query("SELECT * FROM config_global WHERE clave = 'admin_pin'");
        $pin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pin) {
            echo "PIN actual: {$pin['valor']}\n";
        } else {
            echo "No hay PIN guardado\n";
        }
    } else {
        echo "Tabla config_global NO existe - necesita ser creada\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>