<?php
// Script para registrar un webhook automáticamente en MercadoPago
require_once __DIR__ . '/config/config.php';

echo "=== CONFIGURACIÓN AUTOMÁTICA DE WEBHOOK ===\n\n";

$webhook_url = MP_NOTIFICATION_URL;
$access_token = MP_ACCESS_TOKEN;

echo "1. Verificando webhooks existentes...\n";

// Obtener webhooks existentes
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/webhooks');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   - Código HTTP: $http_code\n";
if ($http_code === 200) {
    $webhooks = json_decode($response, true);
    echo "   - Webhooks existentes: " . count($webhooks) . "\n";
    
    // Verificar si ya existe un webhook con nuestra URL
    $existing_webhook = null;
    foreach ($webhooks as $webhook) {
        if ($webhook['url'] === $webhook_url) {
            $existing_webhook = $webhook;
            break;
        }
    }
    
    if ($existing_webhook) {
        echo "   ✓ Ya existe un webhook configurado para esta URL\n";
        echo "   - ID: " . $existing_webhook['id'] . "\n";
        echo "   - Eventos: " . implode(', ', $existing_webhook['events']) . "\n\n";
    } else {
        echo "   - No existe webhook para esta URL, creando uno nuevo...\n\n";
        
        // Crear nuevo webhook
        $webhook_data = [
            'url' => $webhook_url,
            'events' => ['merchant_order']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/webhooks');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $create_response = curl_exec($ch);
        $create_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "2. Resultado de la creación del webhook:\n";
        echo "   - Código HTTP: $create_http_code\n";
        
        if ($create_http_code === 201) {
            $new_webhook = json_decode($create_response, true);
            echo "   ✓ Webhook creado exitosamente\n";
            echo "   - ID: " . $new_webhook['id'] . "\n";
            echo "   - URL: " . $new_webhook['url'] . "\n";
            echo "   - Eventos: " . implode(', ', $new_webhook['events']) . "\n";
        } else {
            echo "   ✗ Error al crear el webhook\n";
            echo "   - Respuesta: $create_response\n";
        }
    }
} else {
    echo "   ✗ Error al obtener webhooks existentes\n";
    echo "   - Respuesta: $response\n";
}

echo "\n=== INSTRUCCIONES MANUALES ===\n";
echo "Si la configuración automática falló, puedes configurar manualmente:\n";
echo "1. Ve a: https://www.mercadopago.com.ar/developers/panel/webhooks\n";
echo "2. Haz clic en 'Agregar endpoint'\n";
echo "3. URL: $webhook_url\n";
echo "4. Eventos: Selecciona 'merchant_order'\n";
echo "5. Guarda la configuración\n\n";

echo "=== VERIFICACIÓN FINAL ===\n";
echo "Para verificar que funciona:\n";
echo "1. Genera un QR y realiza un pago de prueba\n";
echo "2. Verifica que aparezcan notificaciones en el log del webhook\n";
echo "3. Verifica que se cree el archivo de estado de la orden\n";
?>
