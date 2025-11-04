<?php
// Script de prueba completa de endpoints

echo "=== PRUEBA COMPLETA DE ENDPOINTS ===\n";

// 1. Verificar estado inicial
echo "1. Estado inicial del archivo JSON:\n";
$configPath = __DIR__ . '/backend/config/ticket_config.json';
if (file_exists($configPath)) {
    echo file_get_contents($configPath) . "\n";
}

// 2. Probar endpoint GET
echo "\n2. Probando endpoint GET:\n";
$getResponse = file_get_contents('http://localhost/Totem_Murialdo/backend/admin/api/get_ticket_message.php');
echo "Respuesta GET: " . $getResponse . "\n";

// 3. Probar endpoint POST con mensaje nuevo
echo "\n3. Probando endpoint POST con mensaje nuevo:\n";
$postData = json_encode(['mensaje' => 'CANTINA ESCOLAR MURIALDO']);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $postData
    ]
]);

$postResponse = file_get_contents('http://localhost/Totem_Murialdo/backend/admin/api/save_ticket_message.php', false, $context);
echo "Respuesta POST: " . $postResponse . "\n";

// 4. Verificar que el archivo se actualizó
echo "\n4. Estado del archivo JSON después del POST:\n";
if (file_exists($configPath)) {
    echo file_get_contents($configPath) . "\n";
}

// 5. Probar endpoint GET de nuevo para confirmar cambio
echo "\n5. Probando endpoint GET después del cambio:\n";
$getResponse2 = file_get_contents('http://localhost/Totem_Murialdo/backend/admin/api/get_ticket_message.php');
echo "Respuesta GET (después): " . $getResponse2 . "\n";

// 6. Simular print_ticket.php con el nuevo mensaje
echo "\n6. Simulando print_ticket.php con el nuevo mensaje:\n";
$mensajeTicket = 'TOTEM MURIALDO'; // Fallback
if (file_exists($configPath)) {
    $configContent = file_get_contents($configPath);
    if ($configContent !== false) {
        $config = json_decode($configContent, true);
        if ($config && isset($config['mensaje']) && !empty(trim($config['mensaje']))) {
            $mensajeTicket = trim($config['mensaje']);
        }
    }
}

echo "Mensaje que usaría print_ticket.php: '$mensajeTicket'\n";

// 7. Simular ticket completo
echo "\n7. Simulación de ticket final:\n";
$ticketSimulado = $mensajeTicket . "\n";
$ticketSimulado .= "================================\n";
$ticketSimulado .= "Ticket No: K-999\n";
$ticketSimulado .= "Fecha: " . date('Y-m-d') . "\n";
$ticketSimulado .= "Hora: " . date('H:i') . "\n";
$ticketSimulado .= "================================\n";
$ticketSimulado .= "Hamburguesa Completa  x1   $2,500\n";
$ticketSimulado .= "Gaseosa                x1   $1,200\n";
$ticketSimulado .= "================================\n";
$ticketSimulado .= "TOTAL:                      $3,700\n";
$ticketSimulado .= "PAGO: QR MERCADO PAGO\n";
$ticketSimulado .= "================================\n";
$ticketSimulado .= "GRACIAS POR SU COMPRA\n";

echo $ticketSimulado;

echo "\n=== PRUEBA COMPLETA FINALIZADA ===\n";
?>