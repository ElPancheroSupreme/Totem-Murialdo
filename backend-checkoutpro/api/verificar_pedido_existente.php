<?php
// verificar_pedido_existente.php - Verificar si ya existe un pedido con el external_reference
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Leer datos del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['external_reference'])) {
        echo json_encode(['error' => 'Missing external_reference', 'exists' => false]);
        exit;
    }
    
    $external_reference = $data['external_reference'];
    
    // Conectar a la base de datos
    require_once __DIR__ . '/../config/pdo_connection.php';
    
    // Buscar pedido por external_reference
    $stmt = $pdo->prepare("
        SELECT 
            pedidos.id,
            pedidos.numero_pedido,
            pedidos.monto_total,
            pedidos.metodo_pago,
            pedidos.estado,
            pedidos.creado_en
        FROM pedidos 
        WHERE pedidos.external_reference = ? 
        LIMIT 1
    ");
    
    $stmt->execute([$external_reference]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pedido) {
        // Obtener los items del pedido
        $itemsStmt = $pdo->prepare("
            SELECT 
                items_pedido.id_producto,
                items_pedido.cantidad,
                items_pedido.precio_unitario,
                items_pedido.precio_total_item,
                productos.nombre as nombre_producto
            FROM items_pedido
            LEFT JOIN productos ON items_pedido.id_producto = productos.id
            WHERE items_pedido.id_pedido = ?
        ");
        
        $itemsStmt->execute([$pedido['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear items para el ticket
        $itemsFormateados = [];
        foreach ($items as $item) {
            $itemsFormateados[] = [
                'id_producto' => $item['id_producto'],
                'nombre' => $item['nombre_producto'] ?: 'Producto ID ' . $item['id_producto'],
                'cantidad' => intval($item['cantidad']),
                'precio_unitario' => floatval($item['precio_unitario']),
                'precio_total' => floatval($item['precio_total_item']),
                'personalizaciones' => [] // Las personalizaciones se pueden agregar después si es necesario
            ];
        }
        
        // Preparar respuesta con datos del pedido existente
        $pedidoCompleto = [
            'id' => $pedido['id'],
            'numero_pedido' => $pedido['numero_pedido'],
            'monto_total' => floatval($pedido['monto_total']),
            'metodo_pago' => $pedido['metodo_pago'],
            'estado' => $pedido['estado'],
            'creado_en' => $pedido['creado_en'],
            'items' => $itemsFormateados,
            'external_reference' => $external_reference
        ];
        
        echo json_encode([
            'exists' => true,
            'pedido' => $pedidoCompleto,
            'message' => 'Pedido encontrado - evitando duplicado'
        ]);
        
    } else {
        // No existe el pedido
        echo json_encode([
            'exists' => false,
            'message' => 'Pedido no encontrado - se puede crear'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'exists' => false
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error: ' . $e->getMessage(),
        'exists' => false
    ]);
}
?>