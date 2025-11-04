<?php
// Archivo ultra-limpio para guardar pedidos
ob_start();

// Solo lo esencial
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (empty($input) || empty($input['carrito'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Carrito vacío']);
    exit;
}

try {
    // Conexión DB directa
    $pdo = new PDO('mysql:host=192.168.101.93;dbname=bg02;charset=utf8mb4', 'BG02', 'St2025#QkcwMg');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $carrito = $input['carrito'];
    $metodo_pago = $input['metodo_pago'] ?? 'VIRTUAL';
    $estado = $input['estado'] ?? 'PENDIENTE';
    $id_punto_venta = 2;
    
    // Calcular total
    $monto_total = 0;
    foreach ($carrito as $item) {
        $monto_total += ($item['precio_unitario'] * $item['cantidad']);
    }
    
    // Generar número de pedido
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE id_punto_venta = 2");
    $row = $stmt->fetch();
    $numero_pedido = "K-" . str_pad($row['total'] + 1, 3, '0', STR_PAD_LEFT);
    
    // Insertar pedido
    $stmt = $pdo->prepare("INSERT INTO pedidos (numero_pedido, id_punto_venta, metodo_pago, monto_total, estado) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$numero_pedido, $id_punto_venta, $metodo_pago, $monto_total, $estado]);
    $id_pedido = $pdo->lastInsertId();
    
    // Insertar items (versión simplificada)
    foreach ($carrito as $item) {
        $stmt = $pdo->prepare("INSERT INTO items_pedido (id_pedido, id_producto, cantidad, precio_unitario, precio_total_item, es_personalizable) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id_pedido,
            $item['id_producto'],
            $item['cantidad'],
            $item['precio_unitario'],
            $item['precio_unitario'] * $item['cantidad'],
            0
        ]);
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'id_pedido' => $id_pedido, 'numero_pedido' => $numero_pedido]);
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

ob_end_flush();
?>