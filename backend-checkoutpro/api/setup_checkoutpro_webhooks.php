<?php
// setup_checkoutpro_webhooks.php - Configurar webhooks para CheckoutPro
header('Content-Type: text/html; charset=utf-8');

try {
    require_once __DIR__ . '/../config/config.php';
    
    echo "<h2>🔧 Configuración de Webhooks para CheckoutPro</h2>";
    
    // Verificar credenciales de CheckoutPro
    $credentials = get_mp_credentials(true);
    $access_token = $credentials['access_token'];
    
    echo "<h3>📋 Verificación de Credenciales:</h3>";
    
    if (empty($access_token) || $access_token === 'TEST-4841458334764350-090119-your-test-access-token-here') {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; margin: 10px 0;'>";
        echo "❌ <strong>Credenciales de CheckoutPro NO configuradas</strong><br><br>";
        echo "Para configurar CheckoutPro correctamente, necesitas:<br>";
        echo "1. Ir a <a href='https://www.mercadopago.com.ar/developers/panel/app' target='_blank'>Panel de Desarrolladores</a><br>";
        echo "2. Crear una nueva aplicación para CheckoutPro<br>";
        echo "3. Obtener las credenciales TEST<br>";
        echo "4. Actualizar config.php con estas credenciales:<br><br>";
        echo "<pre>";
        echo "define('MP_CHECKOUTPRO_ACCESS_TOKEN', 'TEST-tu-access-token-aqui');\n";
        echo "define('MP_CHECKOUTPRO_PUBLIC_KEY', 'TEST-tu-public-key-aqui');\n";
        echo "define('MP_CHECKOUTPRO_CLIENT_ID', 'tu-app-id');\n";
        echo "define('MP_CHECKOUTPRO_CLIENT_SECRET', 'tu-client-secret');";
        echo "</pre>";
        echo "</div>";
        exit;
    }
    
    // Verificar si es token de test
    $is_test = strpos($access_token, 'TEST') !== false;
    
    echo "<div style='background: " . ($is_test ? '#fff3cd' : '#d4edda') . "; padding: 10px; border-radius: 4px;'>";
    echo "<strong>Access Token:</strong> " . substr($access_token, 0, 20) . "...<br>";
    echo "<strong>Ambiente:</strong> " . ($is_test ? 'TEST (Correcto para pruebas)' : 'PRODUCCIÓN') . "<br>";
    echo "</div>";
    
    // Test de conectividad
    echo "<h3>🔗 Test de Conectividad:</h3>";
    
    $test_url = "https://api.mercadopago.com/users/me";
    $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $user_data = json_decode($response, true);
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 4px;'>";
        echo "✅ <strong>Conexión exitosa</strong><br>";
        echo "Usuario: " . ($user_data['first_name'] ?? 'N/A') . " " . ($user_data['last_name'] ?? 'N/A') . "<br>";
        echo "Email: " . ($user_data['email'] ?? 'N/A') . "<br>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 4px;'>";
        echo "❌ <strong>Error de conexión - HTTP $http_code</strong><br>";
        echo "Verifica que las credenciales sean correctas.";
        echo "</div>";
        exit;
    }
    
    // Para CheckoutPro en ambiente TEST, los webhooks se configuran diferente
    echo "<h3>⚠️ Información Importante sobre Webhooks en TEST:</h3>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 4px;'>";
    echo "<strong>En ambiente de prueba (TEST), MercadoPago tiene limitaciones:</strong><br>";
    echo "• Los webhooks pueden no enviarse automáticamente<br>";
    echo "• Las notificaciones pueden tener retrasos<br>";
    echo "• Algunos eventos pueden no dispararse en TEST<br><br>";
    echo "<strong>Soluciones:</strong><br>";
    echo "1. Usar el simulador de webhooks de MercadoPago<br>";
    echo "2. Implementar verificación por polling como respaldo<br>";
    echo "3. Probar en ambiente de producción con pagos reales pequeños<br>";
    echo "</div>";
    
    // Botón para configurar webhook de todos modos
    echo "<h3>🔧 Configurar Webhook (Experimental):</h3>";
    echo "<button onclick='configureWebhook()' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Intentar Configurar Webhook</button>";
    echo "<div id='webhook-result' style='margin-top: 10px;'></div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 4px;'>";
    echo "❌ Error: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

<script>
function configureWebhook() {
    const result = document.getElementById('webhook-result');
    result.innerHTML = '<div style="background: #e3f2fd; padding: 10px; border-radius: 4px;">⏳ Configurando webhook...</div>';
    
    fetch('configure_checkoutpro_webhook.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            url: 'https://ilm2025.webhop.net/Totem_Murialdo/backend/api/webhook.php'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            result.innerHTML = '<div style="background: #d4edda; padding: 10px; border-radius: 4px;">✅ ' + data.message + '</div>';
        } else {
            result.innerHTML = '<div style="background: #f8d7da; padding: 10px; border-radius: 4px;">❌ ' + data.message + '</div>';
        }
    })
    .catch(error => {
        result.innerHTML = '<div style="background: #f8d7da; padding: 10px; border-radius: 4px;">❌ Error: ' + error + '</div>';
    });
}
</script>