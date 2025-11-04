<?php
// view_webhook_logs.php - View webhook activity logs
header('Content-Type: text/html; charset=utf-8');

try {
    // Connect to database
    $pdo = new PDO('mysql:host=192.168.101.93;dbname=bg02;charset=utf8mb4', 'BG02', 'St2025#QkcwMg');
    
    // Get recent logs
    $stmt = $pdo->query("
        SELECT * FROM webhook_logs 
        WHERE source = 'checkoutpro' 
        ORDER BY timestamp DESC 
        LIMIT 20
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>CheckoutPro Webhook Logs (Últimos 20)</h2>";
    echo "<p>Hora actual: " . date('Y-m-d H:i:s') . "</p>";
    echo "<a href='?' style='margin-bottom: 10px; display: inline-block;'>🔄 Recargar</a>";
    
    if (empty($logs)) {
        echo "<p>No hay logs de CheckoutPro aún.</p>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='width: 100%; font-family: monospace;'>";
        echo "<tr><th>Timestamp</th><th>Type</th><th>Message</th></tr>";
        
        foreach ($logs as $log) {
            $color = '';
            switch ($log['type']) {
                case 'ERROR': $color = 'background-color: #ffcccc;'; break;
                case 'SUCCESS': $color = 'background-color: #ccffcc;'; break;
                case 'INFO': $color = 'background-color: #ccf3ff;'; break;
            }
            
            echo "<tr style='$color'>";
            echo "<td>" . htmlspecialchars($log['timestamp']) . "</td>";
            echo "<td>" . htmlspecialchars($log['type']) . "</td>";
            echo "<td>" . htmlspecialchars($log['message']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Also show general stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM webhook_logs WHERE source = 'checkoutpro'");
    $total = $stmt->fetchColumn();
    
    echo "<p><strong>Total de logs CheckoutPro:</strong> $total</p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>