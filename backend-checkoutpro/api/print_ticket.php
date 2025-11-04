<?php
// filepath: c:\xampp\htdocs\Totem_Murialdo\backend\api\print_ticket.php

// Verificar si se recibieron datos por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Leer los datos enviados en formato JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validar que los datos necesarios estén presentes
    if (!isset($data['numeroOrden'], $data['fecha'], $data['hora'], $data['metodoPago'], $data['totalPagado'], $data['items'])) {
        http_response_code(400);
        echo "Datos incompletos para generar el ticket.";
        exit;
    }

    // Extraer los datos
    $numeroOrden = $data['numeroOrden'];
    $fecha = $data['fecha'];
    $hora = $data['hora'];
    $metodoPago = $data['metodoPago'];
    $totalPagado = $data['totalPagado'];
    $items = $data['items'];
    
    // Normalizar método de pago
    if ($metodoPago === 'QR_VIRTUAL') {
        $metodoPago = 'QR Mercado Pago';
    }
    
    // Normalizar número de orden a formato K-000 (preservando número del backend)
    error_log("Número original recibido: " . $numeroOrden);
    
    if (strpos($numeroOrden, 'QR-') === 0) {
        $soloNumero = str_replace('QR-', '', $numeroOrden);
        $numLimpio = intval($soloNumero);
        $numeroOrden = 'K-' . str_pad($numLimpio, 3, '0', STR_PAD_LEFT);
    } elseif (strpos($numeroOrden, 'K') === 0 && strpos($numeroOrden, '-') === false) {
        // Si viene como K133 (formato backend), convertir a K-133
        $soloNumero = preg_replace('/[^\d]/', '', substr($numeroOrden, 1));
        if ($soloNumero) {
            $numLimpio = intval($soloNumero);
            $numeroOrden = 'K-' . str_pad($numLimpio, 3, '0', STR_PAD_LEFT);
        }
    } elseif (preg_match('/^\d+$/', $numeroOrden)) {
        // Si viene como número puro (ej: "133"), convertir a K-133
        $numLimpio = intval($numeroOrden);
        $numeroOrden = 'K-' . str_pad($numLimpio, 3, '0', STR_PAD_LEFT);
    }
    
    error_log("Número normalizado final: " . $numeroOrden);

    // Generar el contenido del ticket en formato ESC/POS
    $ticket = "\x1B@"; // Inicializar la impresora
    $ticket .= "\x1B!\x38"; // Texto en negrita y tamaño grande
    $ticket .= "TOTEM MURIALDO\n";
    $ticket .= "\x1B!\x00"; // Texto normal
    $ticket .= "================================\n";
    $ticket .= "Ticket No: $numeroOrden\n";
    $ticket .= "Fecha: $fecha\n";
    $ticket .= "Hora: $hora\n";
    $ticket .= "================================\n";

    foreach ($items as $item) {
        $nombre = $item['nombre'];
        $cantidadTexto = $item['cantidad']; // Ejemplo: "3" o "x3"
        $precioTexto = $item['precio']; // Ejemplo: "$1,200" or "$400"
        
        // Limpiar cantidad (remover 'x' si existe)
        $cantidad = intval(str_replace('x', '', $cantidadTexto));
        
        // Mejorar limpieza de precio - mantener puntos decimales
        $precioLimpio = preg_replace('/[^\d,.]/', '', $precioTexto);
        $precioLimpio = str_replace(',', '', $precioLimpio); // Remover comas separadoras de miles
        $precioTotal = floatval($precioLimpio);
        
        // Validar que tenemos valores válidos
        if ($cantidad <= 0) $cantidad = 1;
        if ($precioTotal <= 0) {
            // Si el precio total es 0, intentar usar el precio como viene
            $precioTotal = floatval(filter_var($precioTexto, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
        }
        
        // Calcular precio unitario
        $precioUnitario = ($cantidad > 0) ? ($precioTotal / $cantidad) : $precioTotal;
        
        // Mostrar el nombre del producto
        $ticket .= strtoupper($nombre) . "\n";
        
        // Solo mostrar cantidad si es mayor a 1
        if ($cantidad > 1) {
            $ticket .= sprintf("%d x $%s = $%s\n", $cantidad, number_format($precioUnitario, 0, '.', ','), number_format($precioTotal, 0, '.', ','));
        } else {
            $ticket .= sprintf("$%s\n", number_format($precioTotal, 0, '.', ','));
        }
    }

    $ticket .= "================================\n";
    
    // Mejorar limpieza del total pagado
    $totalLimpio = preg_replace('/[^\d,.]/', '', $totalPagado);
    $totalLimpio = str_replace(',', '', $totalLimpio); // Remover comas separadoras
    $totalNumerico = floatval($totalLimpio);
    
    // Si el total sigue siendo 0, intentar otra forma de limpiar
    if ($totalNumerico <= 0) {
        $totalNumerico = floatval(filter_var($totalPagado, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
    }
    
    $totalFormateado = '$' . number_format($totalNumerico, 0, '.', ',');
    
    $ticket .= sprintf("%-25s %15s\n", "TOTAL:", $totalFormateado);
    $ticket .= sprintf("%-25s %15s\n", "PAGO:", strtoupper($metodoPago));
    $ticket .= "================================\n";
    $ticket .= "GRACIAS POR SU COMPRA\n";
    $ticket .= "\n\n\n"; // Espacios para cortar el papel
    $ticket .= "\x1DVA0"; // Cortar el papel

    // Ruta del puerto de la impresora compartida
    $printer_port = "\\\\localhost\\EPSON_TM_T88V"; // Ruta de la impresora compartida

    // Guardar el contenido en un archivo temporal
    $file = tempnam(sys_get_temp_dir(), 'ticket');
    file_put_contents($file, $ticket);

    // Enviar el archivo a la impresora
    exec("copy $file $printer_port");
    unlink($file);

    echo "Ticket enviado correctamente a la impresora.";
} else {
    http_response_code(405);
    echo "Método no permitido.";
}
?>
