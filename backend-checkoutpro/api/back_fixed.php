<?php
header('Content-Type: application/json');

// Mostrar errores de PHP para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    echo json_encode(['error' => 'Falta vendor/autoload.php. Ejecuta composer install.']);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../config/config.php'; // Cargar configuración

use MercadoPago\MercadoPagoConfig;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Solo se aceptan peticiones POST']);
    exit;
}

// Verificar que el access token esté configurado
if (MP_ACCESS_TOKEN === 'AQUI_TU_ACCESS_TOKEN' || MP_ACCESS_TOKEN === '' || empty(MP_ACCESS_TOKEN)) {
    echo json_encode(['error' => 'Access Token no configurado. Edita el archivo config.php y agrega tu token de Mercado Pago']);
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

// Configurar la SDK v3
MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);

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
    
    // Crear directorio si no existe
    $ordenes_dir = __DIR__ . '/../ordenes_status';
    if (!is_dir($ordenes_dir)) {
        if (!mkdir($ordenes_dir, 0777, true)) {
            echo json_encode(['error' => 'No se pudo crear directorio de órdenes']);
            exit;
        }
    }
    
    $orden_file = $ordenes_dir . "/orden_{$external_reference}_pre.json";
    if (file_put_contents($orden_file, json_encode($orden_data)) === false) {
        echo json_encode(['error' => 'No se pudo guardar archivo de orden']);
        exit;
    }
    
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
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
        // Caso 1: El POST ya devuelve qr_data directamente
        echo json_encode([
            'qr_data' => $post_response_data['qr_data'],
            'order_id' => $post_response_data['in_store_order_id'] ?? null,
            'external_reference' => $post_data['external_reference'],
            'numero_orden' => $numero_orden,
            'origen' => $origen
        ]);
    } else {
        // Caso 2: Respuesta inesperada
        echo json_encode([
            'error' => 'Respuesta inesperada de MercadoPago',
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
} catch (Error $e) {
    echo json_encode([
        'error' => 'Error fatal: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
