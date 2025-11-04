<?php
// ejecutar_add_external_reference.php
// Script para agregar la columna external_reference a la tabla pedidos

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Agregar columna external_reference</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { background: #d4edda; border-left: 4px solid #00a650; padding: 15px; margin: 10px 0; border-radius: 4px; }
    .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; border-radius: 4px; }
    .info { background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; }
    pre { background: #f0f0f0; padding: 15px; border-radius: 4px; overflow-x: auto; }
    code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
</style>";

try {
    $pdo = new PDO(
        "mysql:host=192.168.101.93;dbname=bg02;charset=utf8",
        'BG02',
        'St2025#QkcwMg',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<div class='info'>";
    echo "ℹ️ <strong>Conectado a la base de datos</strong><br>";
    echo "Host: 192.168.101.93<br>";
    echo "Database: bg02<br>";
    echo "Tabla: pedidos";
    echo "</div>";
    
    // Verificar si la columna ya existe
    $check_sql = "SELECT COUNT(*) as existe 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = 'bg02' 
                  AND TABLE_NAME = 'pedidos' 
                  AND COLUMN_NAME = 'external_reference'";
    
    $stmt = $pdo->query($check_sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['existe'] > 0) {
        echo "<div class='success'>";
        echo "✅ <strong>La columna 'external_reference' YA EXISTE</strong><br>";
        echo "No es necesario agregarla nuevamente.";
        echo "</div>";
        
        // Mostrar información de la columna
        $info_sql = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, CHARACTER_MAXIMUM_LENGTH
                     FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = 'bg02' 
                     AND TABLE_NAME = 'pedidos' 
                     AND COLUMN_NAME = 'external_reference'";
        
        $stmt = $pdo->query($info_sql);
        $col_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<div class='info'>";
        echo "<h3>📋 Información de la columna:</h3>";
        echo "<ul>";
        echo "<li><strong>Nombre:</strong> {$col_info['COLUMN_NAME']}</li>";
        echo "<li><strong>Tipo:</strong> {$col_info['DATA_TYPE']}";
        if ($col_info['CHARACTER_MAXIMUM_LENGTH']) {
            echo "({$col_info['CHARACTER_MAXIMUM_LENGTH']})";
        }
        echo "</li>";
        echo "<li><strong>Permite NULL:</strong> " . ($col_info['IS_NULLABLE'] === 'YES' ? 'Sí' : 'No') . "</li>";
        echo "<li><strong>Índice:</strong> " . ($col_info['COLUMN_KEY'] ? $col_info['COLUMN_KEY'] : 'Ninguno') . "</li>";
        echo "</ul>";
        echo "</div>";
        
    } else {
        echo "<div class='info'>";
        echo "⚙️ <strong>La columna 'external_reference' NO EXISTE</strong><br>";
        echo "Procediendo a agregarla...";
        echo "</div>";
        
        // Agregar la columna
        $alter_sql = "ALTER TABLE pedidos 
                      ADD COLUMN external_reference VARCHAR(50) NULL 
                      AFTER numero_pedido";
        
        $pdo->exec($alter_sql);
        
        echo "<div class='success'>";
        echo "✅ <strong>Columna agregada exitosamente</strong><br>";
        echo "SQL ejecutado: <code>$alter_sql</code>";
        echo "</div>";
        
        // Crear índice
        echo "<div class='info'>";
        echo "⚙️ Creando índice para optimizar búsquedas...";
        echo "</div>";
        
        $index_sql = "CREATE INDEX idx_external_reference ON pedidos(external_reference)";
        
        try {
            $pdo->exec($index_sql);
            echo "<div class='success'>";
            echo "✅ <strong>Índice creado exitosamente</strong><br>";
            echo "SQL ejecutado: <code>$index_sql</code>";
            echo "</div>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "<div class='info'>";
                echo "ℹ️ El índice ya existe, continuando...";
                echo "</div>";
            } else {
                throw $e;
            }
        }
    }
    
    // Verificar la estructura final
    echo "<hr>";
    echo "<h2>📊 Estructura actual de la tabla 'pedidos'</h2>";
    
    $columns_sql = "SHOW COLUMNS FROM pedidos";
    $stmt = $pdo->query($columns_sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table style='width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>";
    echo "<tr style='background: #3A9A53; color: white;'>";
    echo "<th style='padding: 12px; text-align: left;'>Campo</th>";
    echo "<th style='padding: 12px; text-align: left;'>Tipo</th>";
    echo "<th style='padding: 12px; text-align: left;'>Null</th>";
    echo "<th style='padding: 12px; text-align: left;'>Key</th>";
    echo "<th style='padding: 12px; text-align: left;'>Default</th>";
    echo "</tr>";
    
    foreach ($columns as $col) {
        $highlight = ($col['Field'] === 'external_reference') ? 'background: #d4edda; font-weight: bold;' : '';
        echo "<tr style='$highlight'>";
        echo "<td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$col['Field']}</td>";
        echo "<td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$col['Type']}</td>";
        echo "<td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$col['Null']}</td>";
        echo "<td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$col['Key']}</td>";
        echo "<td style='padding: 10px; border-bottom: 1px solid #ddd;'>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<hr>";
    echo "<div class='success'>";
    echo "<h2>✅ Proceso completado exitosamente</h2>";
    echo "<p><strong>Próximos pasos:</strong></p>";
    echo "<ol>";
    echo "<li>✅ La columna <code>external_reference</code> está lista</li>";
    echo "<li>🔄 Ahora debes corregir el código del webhook para manejar el nuevo formato de MercadoPago</li>";
    echo "<li>🧪 Probar nuevamente el webhook con un pago real</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "❌ <strong>Error de base de datos:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h3>💡 Solución alternativa:</h3>";
    echo "<p>Si tienes acceso a phpMyAdmin o a la consola MySQL, ejecuta este comando manualmente:</p>";
    echo "<pre>ALTER TABLE pedidos ADD COLUMN external_reference VARCHAR(50) NULL AFTER numero_pedido;</pre>";
    echo "<pre>CREATE INDEX idx_external_reference ON pedidos(external_reference);</pre>";
    echo "</div>";
}

?>
