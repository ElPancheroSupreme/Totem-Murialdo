<?php
// verificar_estructura_items_pedido.php
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Estructura items_pedido</title></head><body>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    table { width: 100%; border-collapse: collapse; background: white; margin: 10px 0; }
    th { background: #3A9A53; color: white; padding: 12px; text-align: left; }
    td { padding: 10px; border-bottom: 1px solid #ddd; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
</style>";

echo "<h1>📋 Estructura de la tabla 'items_pedido'</h1>";

try {
    $pdo = new PDO(
        "mysql:host=192.168.101.93;dbname=bg02;charset=utf8",
        'BG02',
        'St2025#QkcwMg',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->query("DESCRIBE items_pedido");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar si tiene la columna personalizaciones_json
    $tiene_pers_json = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'personalizaciones_json') {
            $tiene_pers_json = true;
        }
    }
    
    if (!$tiene_pers_json) {
        echo "<div class='error'>";
        echo "❌ <strong>PROBLEMA:</strong> La tabla 'items_pedido' NO tiene la columna 'personalizaciones_json'<br>";
        echo "El código de create_checkoutpro.php intenta insertar en esta columna, lo que causaría un error.";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
