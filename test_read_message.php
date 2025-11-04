<?php
// Script para probar la lectura del mensaje dinámico en print_ticket.php

echo "=== PRUEBA DE LECTURA DE MENSAJE DINÁMICO ===\n";

// Simular la lógica de print_ticket.php
$mensajeTicket = 'TOTEM MURIALDO'; // Fallback por defecto
$configPath = __DIR__ . '/backend/config/ticket_config.json';

echo "Ruta del config: $configPath\n";

if (file_exists($configPath)) {
    echo "✓ Archivo de configuración existe\n";
    
    $configContent = file_get_contents($configPath);
    if ($configContent !== false) {
        echo "✓ Contenido leído: " . $configContent . "\n";
        
        $config = json_decode($configContent, true);
        if ($config && isset($config['mensaje']) && !empty(trim($config['mensaje']))) {
            $mensajeTicket = trim($config['mensaje']);
            echo "✓ Mensaje extraído del JSON: '$mensajeTicket'\n";
        } else {
            echo "✗ No se pudo extraer mensaje del JSON o está vacío\n";
        }
    } else {
        echo "✗ Error al leer contenido del archivo\n";
    }
} else {
    echo "✗ Archivo de configuración no existe\n";
}

echo "\n=== RESULTADO ===\n";
echo "Mensaje final que se usaría en el ticket: '$mensajeTicket'\n";

// Simular cómo se vería en el ticket
echo "\n=== SIMULACIÓN DE TICKET ===\n";
$ticket = "\x1B@"; // Inicializar la impresora
$ticket .= "\x1B!\x38"; // Texto en negrita y tamaño grande
$ticket .= $mensajeTicket . "\n";
$ticket .= "\x1B!\x00"; // Texto normal
$ticket .= "================================\n";
$ticket .= "Ticket No: K-123\n";
$ticket .= "Fecha: 2025-11-04\n";
$ticket .= "Hora: 10:30\n";
$ticket .= "================================\n";

// Mostrar solo la parte visible (sin códigos ESC/POS)
$ticketVisible = preg_replace('/\x1B[^\x40-\x7E]*[\x40-\x7E]/', '', $ticket);
echo $ticketVisible;
?>