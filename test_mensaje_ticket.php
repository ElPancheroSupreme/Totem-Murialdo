<?php
// Script de prueba para verificar lectura del mensaje personalizado

echo "=== PRUEBA DE LECTURA DE MENSAJE PERSONALIZADO ===\n\n";

// Archivo del backend principal
$horariosFile1 = __DIR__ . '/../backend/config/horarios.json';
echo "1. Archivo backend principal: $horariosFile1\n";
echo "   Existe: " . (file_exists($horariosFile1) ? 'SÍ' : 'NO') . "\n";

if (file_exists($horariosFile1)) {
    $contenido1 = file_get_contents($horariosFile1);
    $horarios1 = json_decode($contenido1, true);
    
    if ($horarios1) {
        echo "   JSON válido: SÍ\n";
        echo "   Campo mensaje_ticket existe: " . (isset($horarios1['mensaje_ticket']) ? 'SÍ' : 'NO') . "\n";
        
        if (isset($horarios1['mensaje_ticket'])) {
            echo "   Valor: '" . $horarios1['mensaje_ticket'] . "'\n";
        }
    } else {
        echo "   JSON válido: NO\n";
        echo "   Error JSON: " . json_last_error_msg() . "\n";
    }
}

echo "\n";

// Archivo del backend checkout-pro
$horariosFile2 = __DIR__ . '/../backend-checkoutpro/config/horarios.json';
echo "2. Archivo backend checkout-pro: $horariosFile2\n";
echo "   Existe: " . (file_exists($horariosFile2) ? 'SÍ' : 'NO') . "\n";

if (file_exists($horariosFile2)) {
    $contenido2 = file_get_contents($horariosFile2);
    $horarios2 = json_decode($contenido2, true);
    
    if ($horarios2) {
        echo "   JSON válido: SÍ\n";
        echo "   Campo mensaje_ticket existe: " . (isset($horarios2['mensaje_ticket']) ? 'SÍ' : 'NO') . "\n";
        
        if (isset($horarios2['mensaje_ticket'])) {
            echo "   Valor: '" . $horarios2['mensaje_ticket'] . "'\n";
        }
    } else {
        echo "   JSON válido: NO\n";
        echo "   Error JSON: " . json_last_error_msg() . "\n";
    }
}

echo "\n=== SIMULACIÓN DE LECTURA DESDE print_ticket.php ===\n\n";

// Simular la lógica de print_ticket.php (backend principal)
$horariosFile = __DIR__ . '/../backend/config/horarios.json';
$mensajeTicket = "GRACIAS POR SU COMPRA"; // Mensaje por defecto

echo "Simulando backend principal:\n";
echo "Archivo: $horariosFile\n";
echo "Archivo existe: " . (file_exists($horariosFile) ? 'SÍ' : 'NO') . "\n";

if (file_exists($horariosFile)) {
    $contenidoJson = file_get_contents($horariosFile);
    $horarios = json_decode($contenidoJson, true);
    
    echo "JSON decodificado correctamente: " . ($horarios ? 'SÍ' : 'NO') . "\n";
    
    if ($horarios && isset($horarios['mensaje_ticket'])) {
        $mensajeTicket = $horarios['mensaje_ticket'];
        echo "Mensaje leído desde JSON: '$mensajeTicket'\n";
    } else {
        echo "Campo mensaje_ticket no encontrado en JSON\n";
    }
}

echo "Mensaje final que se imprimiría: '$mensajeTicket'\n";
echo "Mensaje en mayúsculas: '" . strtoupper($mensajeTicket) . "'\n";

echo "\n";

// Simular la lógica de print_ticket.php (backend checkout-pro)
$horariosFile = __DIR__ . '/../backend-checkoutpro/config/horarios.json';
$mensajeTicket = "GRACIAS POR SU COMPRA"; // Mensaje por defecto

echo "Simulando backend checkout-pro:\n";
echo "Archivo: $horariosFile\n";
echo "Archivo existe: " . (file_exists($horariosFile) ? 'SÍ' : 'NO') . "\n";

if (file_exists($horariosFile)) {
    $contenidoJson = file_get_contents($horariosFile);
    $horarios = json_decode($contenidoJson, true);
    
    echo "JSON decodificado correctamente: " . ($horarios ? 'SÍ' : 'NO') . "\n";
    
    if ($horarios && isset($horarios['mensaje_ticket'])) {
        $mensajeTicket = $horarios['mensaje_ticket'];
        echo "Mensaje leído desde JSON: '$mensajeTicket'\n";
    } else {
        echo "Campo mensaje_ticket no encontrado en JSON\n";
    }
}

echo "Mensaje final que se imprimiría: '$mensajeTicket'\n";
echo "Mensaje en mayúsculas: '" . strtoupper($mensajeTicket) . "'\n";

echo "\n=== FIN DE PRUEBA ===\n";
?>