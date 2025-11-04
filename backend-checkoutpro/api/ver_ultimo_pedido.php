<?php
// ver_ultimo_pedido.php - Ver el último pedido de CheckoutPro
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Último Pedido CheckoutPro</title></head><body>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    table { width: 100%; border-collapse: collapse; background: white; margin: 10px 0; }
    th { background: #3A9A53; color: white; padding: 12px; text-align: left; }
    td { padding: 10px; border-bottom: 1px solid #ddd; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #00a650; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #0c5460; }
</style>";

echo "<h1>🔍 Último Pedido de CheckoutPro</h1>";

try {
    $pdo = new PDO(
        "mysql:host=192.168.101.93;dbname=bg02;charset=utf8",
        'BG02',
        'St2025#QkcwMg',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Obtener último pedido con external_reference (CheckoutPro)
    $stmt = $pdo->query("
        SELECT 
            p.*,
            COUNT(ip.id) as total_items
        FROM pedidos p
        LEFT JOIN items_pedido ip ON p.id = ip.id_pedido
        WHERE p.external_reference IS NOT NULL
        GROUP BY p.id
        ORDER BY p.id DESC
        LIMIT 1
    ");
    
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        echo "<div class='info'>";
        echo "ℹ️ <strong>No hay pedidos de CheckoutPro todavía</strong><br>";
        echo "Crea un pedido usando CheckoutPro para ver los datos aquí.";
        echo "</div>";
        exit;
    }
    
    echo "<div class='success'>";
    echo "✅ <strong>Pedido encontrado</strong><br>";
    echo "ID: {$pedido['id']} | Número: {$pedido['numero_pedido']}<br>";
    echo "External Reference: <code>{$pedido['external_reference']}</code>";
    echo "</div>";
    
    echo "<h2>📋 Información del Pedido</h2>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    echo "<tr><td><strong>ID</strong></td><td>{$pedido['id']}</td></tr>";
    echo "<tr><td><strong>Número de Pedido</strong></td><td><strong style='color:#3A9A53; font-size:18px;'>{$pedido['numero_pedido']}</strong></td></tr>";
    echo "<tr><td><strong>External Reference</strong></td><td><code>{$pedido['external_reference']}</code></td></tr>";
    echo "<tr><td><strong>Monto Total</strong></td><td>\$" . number_format($pedido['monto_total'], 2) . "</td></tr>";
    echo "<tr><td><strong>Método de Pago</strong></td><td>{$pedido['metodo_pago']}</td></tr>";
    echo "<tr><td><strong>Estado</strong></td><td>{$pedido['estado']}</td></tr>";
    echo "<tr><td><strong>Estado de Pago</strong></td><td><strong style='color:" . ($pedido['estado_pago'] === 'PAGADO' ? '#00a650' : '#ff9800') . ";'>{$pedido['estado_pago']}</strong></td></tr>";
    echo "<tr><td><strong>Payment ID</strong></td><td>" . ($pedido['payment_id'] ?? 'N/A') . "</td></tr>";
    echo "<tr><td><strong>Total Items</strong></td><td>{$pedido['total_items']}</td></tr>";
    echo "<tr><td><strong>Fecha Creación</strong></td><td>{$pedido['creado_en']}</td></tr>";
    echo "</table>";
    
    // Obtener items del pedido
    $stmt_items = $pdo->prepare("
        SELECT 
            ip.*,
            p.nombre as producto_nombre
        FROM items_pedido ip
        LEFT JOIN productos p ON ip.id_producto = p.id
        WHERE ip.id_pedido = ?
    ");
    $stmt_items->execute([$pedido['id']]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($items)) {
        echo "<h2>🛒 Items del Pedido</h2>";
        echo "<table>";
        echo "<tr><th>Producto</th><th>Cantidad</th><th>Precio Unit.</th><th>Subtotal</th><th>Personalizaciones</th></tr>";
        
        foreach ($items as $item) {
            $subtotal = $item['cantidad'] * $item['precio_unitario'];
            $personalizaciones = $item['personalizaciones_json'] 
                ? json_decode($item['personalizaciones_json'], true) 
                : null;
            
            $pers_str = 'Ninguna';
            if ($personalizaciones && is_array($personalizaciones)) {
                $pers_str = implode(', ', array_column($personalizaciones, 'nombre_opcion'));
            }
            
            echo "<tr>";
            echo "<td>{$item['producto_nombre']}</td>";
            echo "<td style='text-align:center;'>{$item['cantidad']}</td>";
            echo "<td>\$" . number_format($item['precio_unitario'], 2) . "</td>";
            echo "<td><strong>\$" . number_format($subtotal, 2) . "</strong></td>";
            echo "<td><small>$pers_str</small></td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    echo "<hr>";
    echo "<div class='info'>";
    echo "<h3>🔄 Acciones Rápidas</h3>";
    echo "<a href='test_webhook_logs.php' style='display:inline-block; background:#0066cc; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; margin:5px;'>Ver Logs del Webhook</a>";
    echo "<a href='ver_ultimo_pedido.php' style='display:inline-block; background:#3A9A53; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; margin:5px;'>Actualizar</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background:#f8d7da; padding:20px; border-radius:4px;'>";
    echo "❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>
