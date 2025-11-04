<?php
header('Content-Type: application/json');

// Deshabilitar errores en producción (comentar estas líneas para debugging)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    echo json_encode(['error' => 'Falta vendor/autoload.php. Ejecuta composer install.']);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../config/config.php'; // Cargar configuración

// NO usar "use MercadoPago\MercadoPagoConfig;" debido a incompatibilidad con PHP 8.4.10
// En su lugar, usar la clase directamente con namespace completo

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Solo se aceptan peticiones POST']);
    exit;
}

// Verificar que el access token esté configurado
if (empty(MP_ACCESS_TOKEN) || MP_ACCESS_TOKEN === 'AQUI_TU_ACCESS_TOKEN' || MP_ACCESS_TOKEN === '') {
    echo json_encode([
        'error' => 'Access Token de Mercado Pago no configurado',
        'solucion' => 'Edita el archivo backend/config/config.php con tus credenciales de Mercado Pago',
        'ejemplo' => 'Revisa backend/config/config_ejemplo.php para ver cómo configurarlo'
    ]);
    exit;
}

// Verificar User ID
if (empty(MP_USER_ID) || MP_USER_ID === '') {
    echo json_encode([
        'error' => 'User ID de Mercado Pago no configurado',
        'solucion' => 'Configura MP_USER_ID en backend/config/config.php'
    ]);
    exit;
}

// Verificar External POS ID
if (empty(MP_EXTERNAL_POS_ID) || MP_EXTERNAL_POS_ID === '') {
    echo json_encode([
        'error' => 'External POS ID no configurado',
        'solucion' => 'Configura MP_EXTERNAL_POS_ID en backend/config/config.php'
    ]);
    exit;
}

// Leer el body JSON
$input = json_decode(file_get_contents('php://input'), true);

// Si no llega por JSON, intenta por POST tradicional
if (!$input || !isset($input['amount'])) {
    $input = $_POST;
}

// Nuevo: obtener carrito y origen
$carrito = isset($input['carrito']) ? $input['carrito'] : [];
$origen = isset($input['origen']) ? $input['origen'] : 'kiosco'; // kiosco o buffet

// Log para depuración
if (!isset($input['amount'])) {
    echo json_encode([
        'error' => 'Monto inválido',
        'debug' => [
            'php_input' => file_get_contents('php://input'),
            'post' => $_POST,
            'server' => $_SERVER
        ]
    ]);
    exit;
}

$amount = (float)$input['amount'];
if ($amount <= 0) {
    echo json_encode(['error' => 'Monto inválido (valor recibido: ' . $input['amount'] . ')']);
    exit;
}

// Configurar la SDK v3 - usar clase directamente sin use statement
\MercadoPago\MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);

try {
    // Paso 1: Crear la orden con POST
    $user_id = defined('MP_USER_ID') ? MP_USER_ID : '2541809286';
    $external_pos_id = defined('MP_EXTERNAL_POS_ID') ? MP_EXTERNAL_POS_ID : 'pos_001';
    
    $post_url = "https://api.mercadopago.com/instore/orders/qr/seller/collectors/{$user_id}/pos/{$external_pos_id}/qrs";
    
    // Generar número de orden
    $orden_prefijo = ($origen === 'buffet') ? 'B' : 'K';
    $orden_num = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $numero_orden = $orden_prefijo . $orden_num;
    
    $dt = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
    $fecha = $dt->format('Y-m-d');
    $hora = $dt->format('H:i:s');
    
    $post_data = [
        'external_reference' => uniqid('qr_'),
        'title' => 'Pago QR Dinámico',
        'description' => 'Pago realizado mediante QR Dinámico',
        'total_amount' => $amount,
        'items' => [
            [
                'sku_number' => 'QR001',
                'category' => 'services',
                'title' => 'Pago QR Dinámico',
                'description' => 'Pago mediante código QR',
                'unit_price' => $amount,
                'quantity' => 1,
                'unit_measure' => 'unit',
                'total_amount' => $amount
            ]
        ],
        'cash_out' => [
            'amount' => 0
        ]
    ];
    
    // Agregar notification_url solo si está configurado y no está vacío
    if (!empty(MP_NOTIFICATION_URL)) {
        $post_data['notification_url'] = MP_NOTIFICATION_URL;
    }
    
    // Guardar datos de la orden en archivo temporal
    $external_reference = $post_data['external_reference'];
    $orden_data = [
        'numero_orden' => $numero_orden,
        'carrito' => $carrito,
        'total' => $amount,
        'fecha' => $fecha,
        'hora' => $hora,
        'origen' => $origen,
        'external_reference' => $external_reference
    ];
    if (!is_dir(__DIR__ . '/ordenes_status')) {
        mkdir(__DIR__ . '/ordenes_status', 0777, true);
    }
    file_put_contents(__DIR__ . "/../ordenes_status/orden_{$external_reference}_pre.json", json_encode($orden_data));
    
    // POST - Crear la orden
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $post_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . MP_ACCESS_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Fix para problema de certificados SSL en Windows
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $post_response = curl_exec($ch);
    $post_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $post_error = curl_error($ch);
    curl_close($ch);
    
    if ($post_error) {
        echo json_encode(['error' => 'Error de cURL en POST: ' . $post_error]);
        exit;
    }
    
    $post_response_data = json_decode($post_response, true);
    
    if (($post_http_code === 200 || $post_http_code === 201) && isset($post_response_data['qr_data'])) {
        echo json_encode([
            'qr_data' => $post_response_data['qr_data'],
            'order_id' => $post_response_data['in_store_order_id'] ?? null,
            'external_reference' => $post_data['external_reference'],
            'numero_orden' => $numero_orden,
            'origen' => $origen
        ]);
    } else if ($post_http_code === 200 || $post_http_code === 201) {
        // Si la respuesta es exitosa pero no viene qr_data, intentar con PUT (flujo anterior)
        if (!isset($post_response_data['in_store_order_id'])) {
            echo json_encode([
                'error' => 'No se recibió in_store_order_id en la respuesta POST',
                'response' => $post_response_data
            ]);
            exit;
        }
        $in_store_order_id = $post_response_data['in_store_order_id'];
        $put_url = "https://api.mercadopago.com/instore/orders/qr/seller/collectors/{$user_id}/pos/{$external_pos_id}/qrs/{$in_store_order_id}";
        $put_data = [
            'external_reference' => $post_data['external_reference'],
            'title' => $post_data['title'],
            'description' => $post_data['description'],
            'total_amount' => $amount,
            'items' => $post_data['items'],
            'cash_out' => $post_data['cash_out']
        ];
        if (!empty(MP_NOTIFICATION_URL)) {
            $put_data['notification_url'] = MP_NOTIFICATION_URL;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $put_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($put_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MP_ACCESS_TOKEN
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Fix para problema de certificados SSL en Windows
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $put_response = curl_exec($ch);
        $put_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $put_error = curl_error($ch);
        curl_close($ch);
        $put_response_data = json_decode($put_response, true);
        if ($put_error) {
            echo json_encode(['error' => 'Error de cURL en PUT: ' . $put_error]);
            exit;
        }
        if (($put_http_code === 200 || $put_http_code === 201) && isset($put_response_data['qr_data'])) {
            echo json_encode([
                'qr_data' => $put_response_data['qr_data'],
                'order_id' => $in_store_order_id,
                'external_reference' => $post_data['external_reference']
            ]);
        } else {
            echo json_encode([
                'error' => 'Error al obtener QR',
                'http_code' => $put_http_code,
                'response' => $put_response_data,
                'order_id' => $in_store_order_id
            ]);
        }
    } else {
        echo json_encode([
            'error' => 'Error al crear orden',
            'http_code' => $post_http_code,
            'response' => $post_response_data
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error: ' . $e->getMessage(),
        'details' => [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
