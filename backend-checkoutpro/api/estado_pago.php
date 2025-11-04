<?php<?php

// estado_pago_checkoutpro.php - API de estado exclusiva para flujo CheckoutPro// estado_pago_checkoutpro.php - API de estado exclusiva para flujo CheckoutPro



header('Content-Type: application/json');header('Content-Type: application/json');



// Función de logging específica para CheckoutPro// Función de logging específica para CheckoutPro

function log_checkoutpro_estado($message) {function log_checkoutpro_estado($message) {

    $timestamp = date('Y-m-d H:i:s');    $timestamp = date('Y-m-d H:i:s');

    $entry = "[$timestamp] CHECKOUTPRO_ESTADO: $message\n";    $entry = "[$timestamp] CHECKOUTPRO_ESTADO: $message\n";

        

    $log_files = [    $log_files = [

        __DIR__ . '/estado_pago_checkoutpro.log',        __DIR__ . '/estado_pago_checkoutpro.log',

        __DIR__ . '/../logs/checkoutpro_estado.log'        __DIR__ . '/../logs/checkoutpro_estado.log'

    ];    ];

        

    foreach ($log_files as $log_file) {    foreach ($log_files as $log_file) {

        @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);        @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);

    }    }

}}



$external_reference = $_GET['external_reference'] ?? null;$external_reference = $_GET['external_reference'] ?? null;

if (!$external_reference) {if (!$external_reference) {

    log_checkoutpro_estado("ERROR: Falta external_reference en la solicitud");    log_checkoutpro_estado("ERROR: Falta external_reference en la solicitud");

    echo json_encode(['error' => 'Falta external_reference para CheckoutPro']);    echo json_encode(['error' => 'Falta external_reference para CheckoutPro']);

    exit;    exit;

}}



// Verificar que sea una referencia CheckoutPro (no QR)// Verificar que sea una referencia CheckoutPro (no QR)

if (strpos($external_reference, 'CP_') !== 0) {if (strpos($external_reference, 'CP_') !== 0) {

    log_checkoutpro_estado("ERROR: Referencias QR no soportadas aquí: $external_reference");    log_checkoutpro_estado("ERROR: Referencias QR no soportadas aquí: $external_reference");

    echo json_encode(['error' => 'Este endpoint solo maneja pagos CheckoutPro']);    echo json_encode(['error' => 'Este endpoint solo maneja pagos CheckoutPro']);

    exit;    exit;

}}



log_checkoutpro_estado("Consultando estado CheckoutPro para: $external_reference");log_checkoutpro_estado("Consultando estado CheckoutPro para: $external_reference");



// Verificar archivo de estado local primero// Buscar archivo de datos previos CheckoutPro

$statusFile = __DIR__ . "/../ordenes_status/orden_{$external_reference}.json";$pre_file = __DIR__ . "/../ordenes_status/orden_{$external_reference}_pre.json";

if (file_exists($statusFile)) {$orden_previa = null;

    log_checkoutpro_estado("Estado CheckoutPro encontrado localmente: $external_reference");

    $data = json_decode(file_get_contents($statusFile), true);if (file_exists($pre_file)) {

    echo json_encode($data);    $orden_previa = json_decode(file_get_contents($pre_file), true);

    exit;    log_checkoutpro_estado("Datos previos CheckoutPro encontrados");

}}



// Usar checkoutpro_robust_checker para consultar estado// Verificar archivo de estado local

require_once __DIR__ . '/checkoutpro_robust_checker.php';$statusFile = __DIR__ . "/../ordenes_status/orden_{$external_reference}.json";

if (file_exists($statusFile)) {

try {    log_checkoutpro_estado("Estado CheckoutPro encontrado localmente: $external_reference");

    log_checkoutpro_estado("Usando checkoutpro_robust_checker para consultar estado");    $data = json_decode(file_get_contents($statusFile), true);

        echo json_encode($data);

    // Llamar al robust checker que maneja toda la lógica CheckoutPro    exit;

    $result = get_checkoutpro_payment_status($external_reference);}

    

    if ($result['success']) {log_checkoutpro_estado("Estado CheckoutPro no encontrado localmente, usando checkoutpro_robust_checker");

        log_checkoutpro_estado("Estado CheckoutPro obtenido exitosamente");

        echo json_encode($result);// Usar checkoutpro_robust_checker para consultar estado

    } else {require_once __DIR__ . '/checkoutpro_robust_checker.php';

        log_checkoutpro_estado("Error obteniendo estado: " . ($result['error'] ?? 'Unknown error'));

        echo json_encode(['status' => 'pending', 'error' => $result['error'] ?? 'Unknown error']);try {

    }    log_checkoutpro_estado("Usando checkoutpro_robust_checker para consultar estado");

        

} catch (Exception $e) {    // Llamar al robust checker que maneja toda la lógica CheckoutPro

    log_checkoutpro_estado("EXCEPCIÓN en estado_pago CheckoutPro: " . $e->getMessage());    $result = get_checkoutpro_payment_status($external_reference);

    echo json_encode(['status' => 'pending', 'error' => $e->getMessage()]);    

}    if ($result['success']) {

?>        log_checkoutpro_estado("Estado CheckoutPro obtenido exitosamente");
        echo json_encode($result);
    } else {
        log_checkoutpro_estado("Error obteniendo estado: " . ($result['error'] ?? 'Unknown error'));
        echo json_encode(['status' => 'pending', 'error' => $result['error'] ?? 'Unknown error']);
    }
    
} catch (Exception $e) {
    log_checkoutpro_estado("EXCEPCIÓN en estado_pago CheckoutPro: " . $e->getMessage());
    echo json_encode(['status' => 'pending', 'error' => $e->getMessage()]);
}
?>
    
    // Para cuentas de prueba, intentar múltiples endpoints
    $search_endpoints = [
        // Endpoint principal para merchant orders
        "https://api.mercadopago.com/merchant_orders/search?external_reference=" . urlencode($external_reference),
        // Endpoint para payments (útil en cuentas de prueba)
        "https://api.mercadopago.com/v1/payments/search?external_reference=" . urlencode($external_reference),
    ];
    
    $found_payment = false;
    
    foreach ($search_endpoints as $endpoint_index => $search_url) {
        log_api_estado("Intentando endpoint " . ($endpoint_index + 1) . ": $search_url");
        
        $ch = curl_init($search_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        log_api_estado("Enviando petición cURL a MercadoPago...");
        $search_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        log_api_estado("Respuesta endpoint " . ($endpoint_index + 1) . " - HTTP Code: $http_code");
        
        if (!empty($curl_error)) {
            log_api_estado("ERROR cURL en endpoint " . ($endpoint_index + 1) . ": $curl_error");
            continue; // Intentar siguiente endpoint
        }
        
        if ($http_code !== 200) {
            log_api_estado("ERROR HTTP en endpoint " . ($endpoint_index + 1) . ": Código $http_code - Respuesta: " . substr($search_response, 0, 200));
            continue; // Intentar siguiente endpoint
        }
        
        log_api_estado("Respuesta HTTP 200 recibida en endpoint " . ($endpoint_index + 1) . ", procesando datos JSON...");
        $search_data = json_decode($search_response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_api_estado("ERROR: JSON inválido en respuesta endpoint " . ($endpoint_index + 1) . " - " . json_last_error_msg());
            continue; // Intentar siguiente endpoint
        }
        
        log_api_estado("JSON decodificado correctamente en endpoint " . ($endpoint_index + 1) . ". Verificando resultados...");
        
        // Manejar diferentes formatos de respuesta según el endpoint
        $results = [];
        if (isset($search_data['results'])) {
            $results = $search_data['results'];
        } elseif (isset($search_data['elements'])) {
            $results = $search_data['elements'];
        } elseif (isset($search_data['response'])) {
            $results = [$search_data['response']];
        }
        
        if (!empty($results)) {
            log_api_estado("Resultados encontrados en endpoint " . ($endpoint_index + 1) . ". Total: " . count($results));
            
            foreach ($results as $result) {
                // Determinar el estado según el tipo de resultado
                $order_status = 'unknown';
                
                if (isset($result['order_status'])) {
                    // Es un merchant_order
                    $order_status = $result['order_status'];
                } elseif (isset($result['status'])) {
                    // Es un payment
                    $payment_status = $result['status'];
                    $order_status = ($payment_status === 'approved') ? 'paid' : $payment_status;
                }
                
                log_api_estado("Estado encontrado en endpoint " . ($endpoint_index + 1) . ": $order_status");
                
                if (in_array($order_status, ['paid', 'partially_paid']) || 
                    (isset($result['status']) && $result['status'] === 'approved')) {
                    
                    log_api_estado("¡PAGO ENCONTRADO Y APROBADO! Endpoint: " . ($endpoint_index + 1) . " - Estado: $order_status");
                    
                    // Leer datos previos para obtener el carrito
                    $pre_file = __DIR__ . "/../ordenes_status/orden_{$external_reference}_pre.json";
                    $orden_previa = [];
                    
                    if (file_exists($pre_file)) {
                        $orden_previa = json_decode(file_get_contents($pre_file), true) ?? [];
                        log_api_estado("Datos previos encontrados con carrito para crear pedido");
                    } else {
                        log_api_estado("WARNING: No hay datos previos - solo crear respuesta básica");
                    }
                    
                    // La orden está pagada, crear respuesta
                    $response_data = [
                        'status' => 'approved',
                        'external_reference' => $external_reference,
                        'order_status' => $order_status,
                        'fecha_pago' => date('Y-m-d H:i:s'),
                        'verified_online' => true,
                        'endpoint_used' => $endpoint_index + 1
                    ];
                    
                    // Si tenemos datos previos con carrito, crear ticket_data
                    if (!empty($orden_previa['carrito'])) {
                        // Calcular total del carrito
                        $monto_total = 0;
                        foreach ($orden_previa['carrito'] as $item) {
                            $monto_total += ($item['precio_unitario'] * $item['cantidad']);
                            if (!empty($item['personalizaciones'])) {
                                foreach ($item['personalizaciones'] as $pers) {
                                    $monto_total += ($pers['precio_extra'] ?? 0) * $item['cantidad'];
                                }
                            }
                        }
                        
                        // Generar número de pedido temporal (será reemplazado por el webhook)
                        $numero_temp = "QR-" . substr($external_reference, -6);
                        
                        $response_data['ticket_data'] = [
                            'numero_orden' => $numero_temp,
                            'metodo_pago' => 'QR_VIRTUAL', // Identificar como QR pero compatible
                            'ticket_items' => $orden_previa['carrito'],
                            'ticket_total' => number_format($monto_total, 2)
                        ];
                        
                        log_api_estado("Datos de ticket incluidos en respuesta para $external_reference");
                    }
                    
                    // Intentar guardar el archivo para futuras consultas
                    $save_result = @file_put_contents($statusFile, json_encode($response_data, JSON_PRETTY_PRINT));
                    if ($save_result) {
                        log_api_estado("Archivo de estado guardado exitosamente en: $statusFile");
                    } else {
                        log_api_estado("WARNING: No se pudo guardar archivo de estado en: $statusFile");
                    }
                    
                    echo json_encode($response_data);
                    exit;
                } else {
                    log_api_estado("Resultado encontrado pero NO pagado en endpoint " . ($endpoint_index + 1) . ". Estado: $order_status");
                }
            }
            
            // Si encontró resultados pero ninguno está pagado
            log_api_estado("Se encontraron resultados en endpoint " . ($endpoint_index + 1) . " pero ninguno está pagado");
            echo json_encode([
                'status' => 'pending',
                'external_reference' => $external_reference,
                'endpoint_checked' => $endpoint_index + 1
            ]);
            exit;
        } else {
            log_api_estado("No se encontraron resultados en endpoint " . ($endpoint_index + 1));
        }
    } // Fin del foreach de endpoints
    
    // Si no encontró nada, devolver pendiente
    log_api_estado("No se encontró información de pago. Devolviendo estado 'pending'");
    
    // NUEVA LÓGICA: Si tenemos datos del carrito, generar ticket_data de respaldo
    if ($orden_previa && !empty($orden_previa['carrito'])) {
        log_api_estado("Generando ticket_data de respaldo desde datos del carrito");
        
        // Calcular total del carrito
        $monto_total = 0;
        foreach ($orden_previa['carrito'] as $item) {
            $monto_total += ($item['precio_unitario'] * $item['cantidad']);
            if (!empty($item['personalizaciones'])) {
                foreach ($item['personalizaciones'] as $pers) {
                    $monto_total += ($pers['precio_extra'] ?? 0) * $item['cantidad'];
                }
            }
        }
        
        // Generar número de pedido temporal
        $numero_temp = "QR-" . substr($external_reference, -6);
        
        $response_data = [
            'status' => 'pending', // Aún pendiente, pero con datos para ticket
            'external_reference' => $external_reference,
            'message' => 'Payment pending but cart data available',
            'ticket_data' => [
                'numero_orden' => $numero_temp,
                'metodo_pago' => 'QR_VIRTUAL',
                'ticket_items' => $orden_previa['carrito'],
                'ticket_total' => number_format($monto_total, 2)
            ]
        ];
        
        log_api_estado("ticket_data de respaldo creado para $external_reference");
        echo json_encode($response_data);
        exit;
    }
    
    // Si no hay datos del carrito, respuesta simple
    echo json_encode(['status' => 'pending', 'external_reference' => $external_reference]);
    
} catch (Exception $e) {
    log_api_estado("EXCEPCIÓN CAPTURADA: " . $e->getMessage() . " en línea " . $e->getLine());
    log_api_estado("Stack trace: " . $e->getTraceAsString());
    
    // En caso de error, devolver pendiente
    echo json_encode(['status' => 'pending', 'error' => $e->getMessage(), 'external_reference' => $external_reference]);
}
?>
