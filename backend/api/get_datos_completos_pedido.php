<?php
// Obtener datos completos del pedido por external_reference
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['external_reference']) || empty($data['external_reference'])) {
    echo json_encode(['success' => false, 'error' => 'external_reference requerido']);
    exit;
}

try {
    // Usar la configuración centralizada de base de datos
    require_once __DIR__ . '/../config/pdo_connection.php';
    
    // Usar la conexión centralizada
    if (!$pdo) {
        throw new Exception('No se pudo establecer conexión con la base de datos');
    }
    
    $external_ref = $data['external_reference'];
    
    // Primero buscar en archivos JSON (más completo)
    $possible_dirs = [
        __DIR__ . "/../ordenes_status",
        "/tmp/ordenes_status",
        dirname(__DIR__) . "/ordenes_status"
    ];
    
    foreach ($possible_dirs as $dir) {
        $archivo_completo = $dir . "/orden_{$external_ref}.json";
        if (file_exists($archivo_completo)) {
            $orden_data = json_decode(file_get_contents($archivo_completo), true);
            if ($orden_data && isset($orden_data['numero_pedido'])) {
                echo json_encode([
                    'success' => true,
                    'numero_pedido' => $orden_data['numero_pedido'],
                    'ticket_items' => $orden_data['ticket_data']['ticket_items'] ?? $orden_data['carrito'] ?? [],
                    'ticket_total' => $orden_data['ticket_data']['ticket_total'] ?? $orden_data['monto_total'] ?? '0',
                    'metodo_pago' => $orden_data['ticket_data']['metodo_pago'] ?? 'CheckoutPro Mercado Pago',
                    'source' => 'archivo_json'
                ]);
                exit;
            }
        }
    }
    
    // Si no está en archivos, buscar en base de datos
    // La conexión $pdo ya está disponible desde el archivo de configuración
    
    // Buscar el pedido más reciente (puede ser VIRTUAL o CheckoutPro) 
    $stmt = $pdo->prepare("
        SELECT p.numero_pedido, p.id_pedido, p.monto_total, p.creado_en, p.metodo_pago,
               GROUP_CONCAT(
                   JSON_OBJECT(
                       'id_producto', i.id_producto,
                       'nombre', pr.nombre,
                       'cantidad', i.cantidad,
                       'precio_unitario', i.precio_unitario,
                       'precio_total', i.precio_total_item
                   )
               ) as items_json
        FROM pedidos p
        LEFT JOIN items_pedido i ON p.id_pedido = i.id_pedido  
        LEFT JOIN productos pr ON i.id_producto = pr.id_producto
        WHERE p.creado_en >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        AND (p.metodo_pago = 'VIRTUAL' OR p.metodo_pago LIKE '%MERCADOPAGO%' OR p.metodo_pago LIKE '%CHECKOUTPRO%')
        GROUP BY p.id_pedido
        ORDER BY p.id_pedido DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $pedido = $stmt->fetch();
    
    if ($pedido) {
        // Procesar items
        $items = [];
        if ($pedido['items_json']) {
            $items_data = json_decode('[' . $pedido['items_json'] . ']', true);
            if ($items_data) {
                $items = $items_data;
            }
        }
        
        echo json_encode([
            'success' => true,
            'numero_pedido' => $pedido['numero_pedido'],
            'ticket_items' => $items,
            'ticket_total' => number_format($pedido['monto_total'], 2),
            'metodo_pago' => $pedido['metodo_pago'],
            'source' => 'base_de_datos',
            'id_pedido' => $pedido['id_pedido'],
            'creado_en' => $pedido['creado_en']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se encontró pedido reciente']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>