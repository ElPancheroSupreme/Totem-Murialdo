<?php
// Diagnóstico del mensaje personalizado del ticket
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico Mensaje Ticket</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🎫 Diagnóstico del Mensaje Personalizado del Ticket</h1>
    
    <?php
    echo "<h2>1. Backend Principal (Totem)</h2>";
    $horariosFile1 = __DIR__ . '/backend/config/horarios.json';
    echo "<p><strong>Archivo:</strong> $horariosFile1</p>";
    
    if (file_exists($horariosFile1)) {
        echo "<p class='success'>✅ El archivo existe</p>";
        
        $contenido1 = file_get_contents($horariosFile1);
        $horarios1 = json_decode($contenido1, true);
        
        if ($horarios1) {
            echo "<p class='success'>✅ JSON válido</p>";
            
            if (isset($horarios1['mensaje_ticket'])) {
                echo "<p class='success'>✅ Campo 'mensaje_ticket' existe</p>";
                echo "<p><strong>Valor:</strong> '" . htmlspecialchars($horarios1['mensaje_ticket']) . "'</p>";
            } else {
                echo "<p class='error'>❌ Campo 'mensaje_ticket' no existe</p>";
            }
        } else {
            echo "<p class='error'>❌ JSON inválido: " . json_last_error_msg() . "</p>";
        }
        
        echo "<h3>Contenido completo del archivo:</h3>";
        echo "<pre>" . htmlspecialchars($contenido1) . "</pre>";
        
    } else {
        echo "<p class='error'>❌ El archivo no existe</p>";
    }
    
    echo "<hr>";
    
    echo "<h2>2. Backend Checkout-Pro (Móvil)</h2>";
    $horariosFile2 = __DIR__ . '/backend-checkoutpro/config/horarios.json';
    echo "<p><strong>Archivo:</strong> $horariosFile2</p>";
    
    if (file_exists($horariosFile2)) {
        echo "<p class='success'>✅ El archivo existe</p>";
        
        $contenido2 = file_get_contents($horariosFile2);
        $horarios2 = json_decode($contenido2, true);
        
        if ($horarios2) {
            echo "<p class='success'>✅ JSON válido</p>";
            
            if (isset($horarios2['mensaje_ticket'])) {
                echo "<p class='success'>✅ Campo 'mensaje_ticket' existe</p>";
                echo "<p><strong>Valor:</strong> '" . htmlspecialchars($horarios2['mensaje_ticket']) . "'</p>";
            } else {
                echo "<p class='error'>❌ Campo 'mensaje_ticket' no existe</p>";
            }
        } else {
            echo "<p class='error'>❌ JSON inválido: " . json_last_error_msg() . "</p>";
        }
        
        echo "<h3>Contenido completo del archivo:</h3>";
        echo "<pre>" . htmlspecialchars($contenido2) . "</pre>";
        
    } else {
        echo "<p class='error'>❌ El archivo no existe</p>";
    }
    
    echo "<hr>";
    
    echo "<h2>3. Simulación de print_ticket.php</h2>";
    
    // Simular lectura desde backend principal
    echo "<h3>Backend Principal:</h3>";
    $horariosFile = __DIR__ . '/backend/config/horarios.json';
    $mensajeTicket = "GRACIAS POR SU COMPRA"; // Mensaje por defecto
    
    if (file_exists($horariosFile)) {
        $contenidoJson = file_get_contents($horariosFile);
        $horarios = json_decode($contenidoJson, true);
        
        if ($horarios && isset($horarios['mensaje_ticket'])) {
            $mensajeTicket = $horarios['mensaje_ticket'];
            echo "<p class='success'>✅ Mensaje leído correctamente: '" . htmlspecialchars($mensajeTicket) . "'</p>";
        } else {
            echo "<p class='error'>❌ No se pudo leer el mensaje personalizado</p>";
        }
    }
    
    echo "<p><strong>Mensaje que aparecería en el ticket:</strong> '" . htmlspecialchars(strtoupper($mensajeTicket)) . "'</p>";
    
    // Simular lectura desde backend checkout-pro
    echo "<h3>Backend Checkout-Pro:</h3>";
    $horariosFile = __DIR__ . '/backend-checkoutpro/config/horarios.json';
    $mensajeTicket = "GRACIAS POR SU COMPRA"; // Mensaje por defecto
    
    if (file_exists($horariosFile)) {
        $contenidoJson = file_get_contents($horariosFile);
        $horarios = json_decode($contenidoJson, true);
        
        if ($horarios && isset($horarios['mensaje_ticket'])) {
            $mensajeTicket = $horarios['mensaje_ticket'];
            echo "<p class='success'>✅ Mensaje leído correctamente: '" . htmlspecialchars($mensajeTicket) . "'</p>";
        } else {
            echo "<p class='error'>❌ No se pudo leer el mensaje personalizado</p>";
        }
    }
    
    echo "<p><strong>Mensaje que aparecería en el ticket:</strong> '" . htmlspecialchars(strtoupper($mensajeTicket)) . "'</p>";
    
    echo "<hr>";
    echo "<h2>4. Comparación</h2>";
    
    if (file_exists(__DIR__ . '/backend/config/horarios.json') && file_exists(__DIR__ . '/backend-checkoutpro/config/horarios.json')) {
        $horarios1 = json_decode(file_get_contents(__DIR__ . '/backend/config/horarios.json'), true);
        $horarios2 = json_decode(file_get_contents(__DIR__ . '/backend-checkoutpro/config/horarios.json'), true);
        
        $mensaje1 = isset($horarios1['mensaje_ticket']) ? $horarios1['mensaje_ticket'] : 'NO DEFINIDO';
        $mensaje2 = isset($horarios2['mensaje_ticket']) ? $horarios2['mensaje_ticket'] : 'NO DEFINIDO';
        
        echo "<p><strong>Backend Principal:</strong> '" . htmlspecialchars($mensaje1) . "'</p>";
        echo "<p><strong>Backend Checkout-Pro:</strong> '" . htmlspecialchars($mensaje2) . "'</p>";
        
        if ($mensaje1 === $mensaje2) {
            echo "<p class='success'>✅ Los mensajes coinciden</p>";
        } else {
            echo "<p class='warning'>⚠️ Los mensajes NO coinciden - esto puede causar inconsistencias</p>";
        }
    }
    ?>
    
    <hr>
    <p><em>Actualiza esta página después de hacer cambios en ConfigDash para ver los resultados.</em></p>
</body>
</html>