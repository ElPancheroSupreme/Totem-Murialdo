<?php
// test_webhook_simulation.php - Simular webhook de MercadoPago para testing
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Simulador de Webhook CheckoutPro</title></head><body>";
echo "<h1>🧪 Simulador de Webhook CheckoutPro</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    button { background: #3A9A53; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
    button:hover { background: #2d7a40; }
    pre { background: #f0f0f0; padding: 15px; border-radius: 4px; overflow-x: auto; }
    .success { background: #d4edda; border-left: 4px solid #00a650; padding: 15px; margin: 10px 0; border-radius: 4px; }
    .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; border-radius: 4px; }
    .info { background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; }
</style>";

// Función para simular webhook
function simular_webhook($tipo = 'payment') {
    // Generar external_reference realista
    $timestamp = time() * 1000; // Milisegundos
    $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 9);
    $external_reference = "CP_{$timestamp}_{$random}";
    
    // IDs aleatorios pero realistas
    $payment_id = rand(1000000000, 9999999999);
    $merchant_order_id = rand(10000000, 99999999);
    
    // Payload según el tipo
    if ($tipo === 'merchant_order') {
        $payload = [
            'type' => 'merchant_order',
            'action' => 'payment.created',
            'data' => [
                'id' => (string)$merchant_order_id
            ]
        ];
    } else {
        $payload = [
            'type' => 'payment',
            'action' => 'payment.created',
            'data' => [
                'id' => (string)$payment_id
            ]
        ];
    }
    
    // URL del webhook
    $webhook_url = 'https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/webhook_checkoutpro.php';
    
    // Simular request de MercadoPago
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: MercadoPago-Webhook/1.0',
        'X-Request-Id: test-' . time()
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'payload' => $payload,
        'external_reference' => $external_reference,
        'payment_id' => $payment_id,
        'merchant_order_id' => $merchant_order_id,
        'response' => $response,
        'http_code' => $http_code,
        'webhook_url' => $webhook_url
    ];
}

// Verificar si se está ejecutando la simulación
if (isset($_GET['ejecutar'])) {
    $tipo = $_GET['tipo'] ?? 'payment';
    
    echo "<div class='section'>";
    echo "<h2>🚀 Ejecutando simulación de webhook tipo: <strong>$tipo</strong></h2>";
    
    $resultado = simular_webhook($tipo);
    
    echo "<h3>📤 Payload enviado:</h3>";
    echo "<pre>" . json_encode($resultado['payload'], JSON_PRETTY_PRINT) . "</pre>";
    
    echo "<h3>🔗 URL del webhook:</h3>";
    echo "<pre>{$resultado['webhook_url']}</pre>";
    
    echo "<h3>📥 Respuesta del webhook:</h3>";
    
    if ($resultado['http_code'] === 200) {
        echo "<div class='success'>";
        echo "✅ <strong>HTTP {$resultado['http_code']}</strong> - Webhook respondió correctamente";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "❌ <strong>HTTP {$resultado['http_code']}</strong> - El webhook no respondió correctamente";
        echo "</div>";
    }
    
    echo "<h4>Body de la respuesta:</h4>";
    echo "<pre>" . htmlspecialchars($resultado['response']) . "</pre>";
    
    echo "<div class='info'>";
    echo "ℹ️ <strong>Nota:</strong> Esta es una simulación. Para una prueba real, debes:<br>";
    echo "1. Crear un pedido en el sistema<br>";
    echo "2. Usar el <code>external_reference</code> generado: <code>{$resultado['external_reference']}</code><br>";
    echo "3. Realizar un pago real con MercadoPago<br>";
    echo "4. MercadoPago enviará una notificación real al webhook";
    echo "</div>";
    
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>📊 Ver logs del webhook</h3>";
    echo "<a href='test_webhook_logs.php' style='display: inline-block; background: #0066cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px;'>Ver Logs</a>";
    echo "</div>";
    
} else {
    // Mostrar formulario
    echo "<div class='section'>";
    echo "<h2>Selecciona el tipo de webhook a simular:</h2>";
    echo "<p>Este script enviará una notificación simulada al webhook de CheckoutPro para verificar que está funcionando.</p>";
    
    echo "<form method='GET'>";
    echo "<input type='hidden' name='ejecutar' value='1'>";
    echo "<label style='display: block; margin: 15px 0;'>";
    echo "<input type='radio' name='tipo' value='payment' checked> Payment (notificación de pago individual)";
    echo "</label>";
    echo "<label style='display: block; margin: 15px 0;'>";
    echo "<input type='radio' name='tipo' value='merchant_order'> Merchant Order (orden completa con pagos)";
    echo "</label>";
    echo "<button type='submit'>🚀 Ejecutar Simulación</button>";
    echo "</form>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>ℹ️ Información sobre el webhook</h3>";
    echo "<ul>";
    echo "<li><strong>URL:</strong> <code>https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/webhook_checkoutpro.php</code></li>";
    echo "<li><strong>Método:</strong> POST</li>";
    echo "<li><strong>Content-Type:</strong> application/json</li>";
    echo "<li><strong>User-Agent esperado:</strong> MercadoPago*</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>🔍 Ver logs existentes</h3>";
    echo "<a href='test_webhook_logs.php' style='display: inline-block; background: #0066cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px;'>Ver Logs del Webhook</a>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>⚙️ Verificar configuración en MercadoPago</h3>";
    echo "<p>Para que el webhook funcione en producción, debes configurarlo en tu cuenta de MercadoPago:</p>";
    echo "<ol>";
    echo "<li>Ve a <a href='https://www.mercadopago.com.ar/developers/panel/app' target='_blank'>Panel de Desarrolladores</a></li>";
    echo "<li>Selecciona tu aplicación</li>";
    echo "<li>Ve a <strong>Webhooks</strong></li>";
    echo "<li>Agrega la URL: <code>https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/webhook_checkoutpro.php</code></li>";
    echo "<li>Selecciona los eventos: <code>payment</code> y <code>merchant_orders</code></li>";
    echo "</ol>";
    echo "</div>";
}

echo "</body></html>";
?>
