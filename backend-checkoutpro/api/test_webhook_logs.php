<?php
// test_webhook_logs.php - Ver últimos logs del webhook
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Logs Webhook CheckoutPro</title></head><body>";

try {
    $pdo = new PDO(
        "mysql:host=192.168.101.93;dbname=bg02;charset=utf8",
        'BG02',
        'St2025#QkcwMg',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<h1>🔍 Últimos 20 logs del webhook CheckoutPro</h1>";
    echo "<style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        th { background: #3A9A53; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .INFO { color: #0066cc; }
        .SUCCESS { color: #00a650; font-weight: bold; }
        .ERROR { color: #ff4444; font-weight: bold; }
        .WARNING { color: #ff9800; font-weight: bold; }
        .SECURITY { color: #9c27b0; font-weight: bold; }
        pre { background: #f0f0f0; padding: 8px; border-radius: 4px; overflow-x: auto; }
    </style>";
    
    $stmt = $pdo->query("SELECT * FROM webhook_logs WHERE source = 'checkoutpro' ORDER BY timestamp DESC LIMIT 20");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "<p style='background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ff9800;'>";
        echo "⚠️ <strong>No hay logs del webhook CheckoutPro</strong><br>";
        echo "Esto significa que el webhook NO ha recibido ninguna notificación de MercadoPago.<br><br>";
        echo "<strong>Verifica:</strong><br>";
        echo "1. Que el webhook esté configurado en MercadoPago: <code>https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/webhook_checkoutpro.php</code><br>";
        echo "2. Que hayas realizado un pago de prueba<br>";
        echo "3. Que MercadoPago esté enviando notificaciones";
        echo "</p>";
        echo "</body></html>";
        exit;
    }
    
    echo "<p style='background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #00a650;'>";
    echo "✅ Se encontraron <strong>" . count($logs) . "</strong> registros de webhook";
    echo "</p>";
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Timestamp</th><th>Tipo</th><th>Mensaje</th></tr>";
    
    foreach ($logs as $log) {
        $tipo_class = strtoupper($log['type']);
        echo "<tr>";
        echo "<td><strong>{$log['id']}</strong></td>";
        echo "<td><small>{$log['timestamp']}</small></td>";
        echo "<td><span class='$tipo_class'>$tipo_class</span></td>";
        echo "<td><pre>" . htmlspecialchars($log['message']) . "</pre></td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Análisis de los logs
    echo "<hr><h2>📊 Análisis de logs</h2>";
    
    $total_inicios = 0;
    $total_errores = 0;
    $total_exitosos = 0;
    
    foreach ($logs as $log) {
        if (strpos($log['message'], 'INICIADO') !== false) $total_inicios++;
        if ($log['type'] === 'ERROR') $total_errores++;
        if ($log['type'] === 'SUCCESS') $total_exitosos++;
    }
    
    echo "<ul style='background: white; padding: 20px; border-radius: 8px;'>";
    echo "<li><strong>Webhooks recibidos:</strong> $total_inicios</li>";
    echo "<li><strong>Procesados exitosamente:</strong> <span style='color: #00a650;'>$total_exitosos</span></li>";
    echo "<li><strong>Con errores:</strong> <span style='color: #ff4444;'>$total_errores</span></li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;'>";
    echo "❌ <strong>Error de conexión a base de datos:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>
