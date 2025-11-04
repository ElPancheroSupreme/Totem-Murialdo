<?php
// Obtener número de pedido real desde external_reference
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
    
    // Si NO es señal especial, buscar en archivos primero
    if ($external_ref !== 'ULTIMO_PEDIDO') {
        $archivo_completo = __DIR__ . "/../ordenes_status/{$external_ref}_completa.json";
        
        if (file_exists($archivo_completo)) {
            $orden_data = json_decode(file_get_contents($archivo_completo), true);
            if (isset($orden_data['numero_pedido'])) {
                echo json_encode([
                    'success' => true, 
                    'numero_pedido' => $orden_data['numero_pedido'],
                    'metodo' => 'archivo_json'
                ]);
                exit;
            }
        }
    }
    
    // Si no está en archivos o es ULTIMO_PEDIDO, buscar en base de datos
    // La conexión $pdo ya está disponible desde el archivo de configuración
    
    // Buscar el último pedido (VIRTUAL o CheckoutPro) de las últimas 24 horas
    $stmt = $pdo->prepare("
        SELECT numero_pedido, id_pedido, creado_en, metodo_pago
        FROM pedidos 
        WHERE (metodo_pago = 'VIRTUAL' OR metodo_pago = 'CHECKOUTPRO' OR metodo_pago LIKE '%MERCADOPAGO%' OR metodo_pago = 'QR_VIRTUAL')
        AND creado_en >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        ORDER BY id_pedido DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $pedidos = $stmt->fetchAll();
    
    if (count($pedidos) > 0) {
        // Tomar el último pedido como el más probable
        echo json_encode([
            'success' => true, 
            'numero_pedido' => $pedidos[0]['numero_pedido'],
            'metodo' => $external_ref === 'ULTIMO_PEDIDO' ? 'ultimo_pedido_virtual' : 'ultimo_pedido_reciente',
            'metodo_pago' => $pedidos[0]['metodo_pago'],
            'creado_en' => $pedidos[0]['creado_en'],
            'id_pedido' => $pedidos[0]['id_pedido'],
            'total_encontrados' => count($pedidos)
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se encontró pedido reciente (VIRTUAL/CHECKOUTPRO)']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>