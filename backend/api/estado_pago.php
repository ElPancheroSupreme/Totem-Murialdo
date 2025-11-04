<?php
// estado_pago_mejorado.php - Versión mejorada que verifica directamente con Mercado Pago

header('Content-Type: application/json');

// Función de logging para rastrear consultas a API
function log_api_estado($message) {
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] ESTADO_PAGO: $message\n";
    
    // Guardar en múltiples ubicaciones
    $log_files = [
        __DIR__ . '/estado_pago_api.log',
        __DIR__ . '/../estado_pago_api.log',
        __DIR__ . '/../../estado_pago_debug.log'
    ];
    
    foreach ($log_files as $log_file) {
        @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
    }
}

$external_reference = $_GET['external_reference'] ?? null;
if (!$external_reference) {
    log_api_estado("ERROR: Falta external_reference en la solicitud");
    echo json_encode(['error' => 'Falta external_reference']);
    exit;
}

log_api_estado("Iniciando consulta de estado para: $external_reference");

// NUEVA LÓGICA: Siempre buscar archivo _pre para datos del carrito
$pre_file = __DIR__ . "/../ordenes_status/orden_{$external_reference}_pre.json";
$orden_previa = null;

if (file_exists($pre_file)) {
    $orden_previa = json_decode(file_get_contents($pre_file), true);
    log_api_estado("Archivo _pre encontrado con datos del carrito");
}

// Primero, intentar leer el archivo local (método original)
$statusFile = __DIR__ . "/../ordenes_status/orden_{$external_reference}.json";
if (file_exists($statusFile)) {
    log_api_estado("Archivo local encontrado para $external_reference, usando método original");
    $data = json_decode(file_get_contents($statusFile), true);
    echo json_encode($data);
    exit;
}

log_api_estado("Archivo local NO encontrado para $external_reference, consultando API de MercadoPago");

// Si no existe el archivo local, consultar directamente a Mercado Pago
require_once __DIR__ . '/../config/config.php';

try {
    log_api_estado("Iniciando consulta a API de MercadoPago para $external_reference");
    
    // Buscar la orden en Mercado Pago usando external_reference
    $access_token = MP_ACCESS_TOKEN;
    
    if (empty($access_token) || $access_token === 'AQUI_TU_ACCESS_TOKEN') {
        log_api_estado("ERROR: Access token no configurado o inválido");
        echo json_encode(['status' => 'pending', 'error' => 'Access token no configurado']);
        exit;
    }
    
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
