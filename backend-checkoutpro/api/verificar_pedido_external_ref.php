<?php
// verificar_pedido_external_ref.php - Buscar pedido por external_reference
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Verificar External Reference</title></head><body>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
    .success { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #00a650; }
    .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #0c5460; }
    input { padding: 10px; width: 400px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px; }
    button { padding: 10px 20px; background: #3A9A53; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
    table { width: 100%; border-collapse: collapse; background: white; margin: 10px 0; }
    th { background: #3A9A53; color: white; padding: 12px; text-align: left; }
    td { padding: 10px; border-bottom: 1px solid #ddd; }
</style>";

echo "<h1>🔍 Buscar Pedido por External Reference</h1>";

// External reference del log más reciente
$external_ref_default = 'CP_1761260203128_p9bfw9ybt';

if (isset($_GET['external_ref'])) {
    $external_ref = $_GET['external_ref'];
    
    try {
        $pdo = new PDO(
            "mysql:host=192.168.101.93;dbname=bg02;charset=utf8",
            'BG02',
            'St2025#QkcwMg',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        echo "<div class='info'>";
        echo "🔍 Buscando pedido con external_reference: <code>$external_ref</code>";
        echo "</div>";
        
        // Buscar pedido
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE external_reference = ?");
        $stmt->execute([$external_ref]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pedido) {
            echo "<div class='success'>";
            echo "✅ <strong>PEDIDO ENCONTRADO</strong><br>";
            echo "ID: {$pedido['id_pedido']} | Número: {$pedido['numero_pedido']}<br>";
            echo "Estado: {$pedido['estado']} | Método: {$pedido['metodo_pago']}";
            echo "</div>";
            
            echo "<h2>📋 Detalles del Pedido</h2>";
            echo "<table>";
            foreach ($pedido as $key => $value) {
                echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
            }
            echo "</table>";
            
        } else {
            echo "<div class='error'>";
            echo "❌ <strong>PEDIDO NO ENCONTRADO</strong><br>";
            echo "No existe ningún pedido con external_reference: <code>$external_ref</code>";
            echo "</div>";
            
            // Buscar pedidos recientes para comparar
            echo "<h2>📊 Últimos 10 pedidos en la base de datos</h2>";
            $stmt = $pdo->query("SELECT id_pedido, numero_pedido, external_reference, metodo_pago, estado, creado_en FROM pedidos ORDER BY id_pedido DESC LIMIT 10");
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($pedidos)) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Número</th><th>External Ref</th><th>Método</th><th>Estado</th><th>Creado</th></tr>";
                foreach ($pedidos as $p) {
                    $ext_ref = $p['external_reference'] ?? '<span style="color:#888;">NULL</span>';
                    echo "<tr>";
                    echo "<td>{$p['id_pedido']}</td>";
                    echo "<td><strong>{$p['numero_pedido']}</strong></td>";
                    echo "<td><code>$ext_ref</code></td>";
                    echo "<td>{$p['metodo_pago']}</td>";
                    echo "<td>{$p['estado']}</td>";
                    echo "<td><small>{$p['creado_en']}</small></td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                echo "<div class='info'>";
                echo "ℹ️ <strong>Observación:</strong><br>";
                $con_external = array_filter($pedidos, function($p) { return !empty($p['external_reference']); });
                echo "Pedidos con external_reference: <strong>" . count($con_external) . "/10</strong><br>";
                if (count($con_external) === 0) {
                    echo "<span style='color:#dc3545;'>⚠️ Ningún pedido tiene external_reference. El código de create_checkoutpro.php NO está funcionando.</span>";
                }
                echo "</div>";
            }
        }
        
    } catch (PDOException $e) {
        echo "<div class='error'>";
        echo "❌ <strong>Error de BD:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    
} else {
    echo "<div class='info'>";
    echo "ℹ️ Ingresa el <code>external_reference</code> que quieres buscar.<br>";
    echo "Este es el último external_reference del webhook: <code>$external_ref_default</code>";
    echo "</div>";
}

echo "<form method='GET'>";
echo "<h2>🔍 Buscar Pedido</h2>";
echo "<input type='text' name='external_ref' placeholder='Ej: CP_1761260203128_p9bfw9ybt' value='" . ($external_ref ?? $external_ref_default) . "'>";
echo "<button type='submit'>Buscar</button>";
echo "</form>";

echo "<hr>";
echo "<div class='info'>";
echo "<h3>🔗 Enlaces útiles</h3>";
echo "<a href='test_webhook_logs.php' style='display:inline-block; background:#0066cc; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; margin:5px;'>Ver Logs</a>";
echo "<a href='ver_ultimo_pedido.php' style='display:inline-block; background:#3A9A53; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; margin:5px;'>Último Pedido</a>";
echo "</div>";

echo "</body></html>";
?>
