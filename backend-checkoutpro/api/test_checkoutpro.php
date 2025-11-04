<?php
// Test script para verificar que create_checkoutpro.php funciona correctamente

// Simular datos de prueba como los que envía checkoutpro.html
$test_data = [
    'items' => [
        [
            'title' => 'Agua Villavicencio 500ml',
            'quantity' => 1,
            'unit_price' => 1100,
            'currency_id' => 'ARS'
        ]
    ],
    'external_reference' => 'CP_' . time() . '_test123',
    'back_urls' => [
        'success' => 'https://ilm2025.webhop.net/Totem_Murialdo/frontend/views/Ticket.html',
        'failure' => 'https://ilm2025.webhop.net/Totem_Murialdo/frontend/views/checkoutpro.html?error=payment_failed',
        'pending' => 'https://ilm2025.webhop.net/Totem_Murialdo/frontend/views/checkoutpro.html?status=pending'
    ],
    'auto_return' => 'approved',
    'payment_methods' => [
        'excluded_payment_types' => []
    ],
    'notification_url' => 'https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/webhook.php?source=checkoutpro'
];

echo "=== TEST CREATE CHECKOUTPRO ===\n";
echo "Datos de prueba:\n";
echo json_encode($test_data, JSON_PRETTY_PRINT) . "\n\n";

// Simular POST request
$_SERVER['REQUEST_METHOD'] = 'POST';

// Capturar la salida
ob_start();

// Simular input JSON
file_put_contents('php://memory', json_encode($test_data));

try {
    // Include the main script
    include 'create_checkoutpro.php';
    $output = ob_get_contents();
} catch (Exception $e) {
    $output = "ERROR: " . $e->getMessage();
} finally {
    ob_end_clean();
}

echo "Resultado:\n";
echo $output . "\n";

// Verificar si se creó el log
if (file_exists(__DIR__ . '/checkoutpro_debug.log')) {
    echo "\n=== CONTENIDO DEL LOG ===\n";
    echo file_get_contents(__DIR__ . '/checkoutpro_debug.log');
} else {
    echo "\n❌ No se creó el archivo de log\n";
}
?>