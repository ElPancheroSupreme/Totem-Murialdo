<?php
// Script de prueba para verificar endpoints del mensaje del ticket

echo "=== PRUEBA DE ENDPOINTS DE MENSAJE DEL TICKET ===\n";

// Cambiar directorio a la raíz del proyecto
chdir('\\\\proyectos.ilm.murialdo.local\\proyectos\\certsite\\Totem_Murialdo');

echo "1. Verificando archivo JSON inicial...\n";
$configPath = 'backend/config/ticket_config.json';
if (file_exists($configPath)) {
    $contenido = file_get_contents($configPath);
    echo "✓ Archivo existe. Contenido: " . $contenido . "\n";
} else {
    echo "✗ Archivo no existe\n";
}

echo "\n2. Probando endpoint de guardado...\n";
$data = ['mensaje' => 'BUFFET MURIALDO TEST'];
$json = json_encode($data);

// Usar cURL para probar el endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/Totem_Murialdo/backend/admin/api/save_ticket_message.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$respuesta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Respuesta: $respuesta\n";

echo "\n3. Verificando archivo JSON después del guardado...\n";
if (file_exists($configPath)) {
    $contenidoDespues = file_get_contents($configPath);
    echo "✓ Contenido actualizado: " . $contenidoDespues . "\n";
} else {
    echo "✗ Archivo no existe\n";
}

echo "\n4. Probando endpoint de obtención...\n";
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'http://localhost/Totem_Murialdo/backend/admin/api/get_ticket_message.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);

$respuestaGet = curl_exec($ch2);
$httpCodeGet = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: $httpCodeGet\n";
echo "Respuesta: $respuestaGet\n";

echo "\n=== PRUEBA COMPLETADA ===\n";
?>