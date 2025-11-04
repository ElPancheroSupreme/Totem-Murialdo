<?php
// Archivo limpio y funcional para guardar pedidos
header('Content-Type: application/json');

// Incluir configuración de base de datos
require_once '../config/db_connection.php';

// Leer datos del POST
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (empty($input) || empty($input['carrito'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Carrito vacío']);
    exit;
}

try {
    // Usar conexión centralizada convertida a PDO
    $mysqli = getConnection();
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $carrito = $input['carrito'];
    $metodo_pago = $input['metodo_pago'] ?? 'VIRTUAL';
    $estado = $input['estado'] ?? 'PENDIENTE';
    // Obtener id_punto_venta desde el input o usar Kiosco (2) por defecto
    $id_punto_venta = intval($input['id_punto_venta'] ?? 2);
    
    $pdo->beginTransaction();
    
    // Calcular monto total (incluyendo personalizaciones)
    $monto_total = 0;
    foreach ($carrito as $item) {
        $precio_unitario = floatval($item['precio_unitario'] ?? 0);
        $cantidad = intval($item['cantidad'] ?? 1);
        $monto_total += ($precio_unitario * $cantidad);
        
        // Agregar personalizaciones si existen
        if (!empty($item['personalizaciones'])) {
            foreach ($item['personalizaciones'] as $pers) {
                $precio_extra = floatval($pers['precio_extra'] ?? 0);
                $monto_total += $precio_extra * $cantidad;
            }
        }
    }
    
    // Generar número de pedido según punto de venta
    // 1 = Buffet (B-XXX), 2 = Kiosco (K-XXX)
    $prefijo = ($id_punto_venta == 1) ? 'B' : 'K';
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE id_punto_venta = ?");
    $stmt->execute([$id_punto_venta]);
    $row = $stmt->fetch();
    $numero_pedido = $prefijo . "-" . str_pad($row['total'] + 1, 3, '0', STR_PAD_LEFT);
    
    // Insertar pedido
    $stmt = $pdo->prepare("INSERT INTO pedidos (numero_pedido, id_punto_venta, metodo_pago, monto_total, estado) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$numero_pedido, $id_punto_venta, $metodo_pago, $monto_total, $estado]);
    $id_pedido = $pdo->lastInsertId();
    
    // Insertar items y personalizaciones
    foreach ($carrito as $item) {
        $precio_total_item = $item['precio_unitario'] * $item['cantidad'];
        
        // Agregar costo de personalizaciones al item
        if (!empty($item['personalizaciones'])) {
            foreach ($item['personalizaciones'] as $pers) {
                $precio_total_item += ($pers['precio_extra'] ?? 0) * $item['cantidad'];
            }
        }
        
        // Insertar item
        $stmt = $pdo->prepare("INSERT INTO items_pedido (id_pedido, id_producto, cantidad, precio_unitario, precio_total_item, es_personalizable) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id_pedido,
            $item['id_producto'],
            $item['cantidad'],
            $item['precio_unitario'],
            $precio_total_item,
            !empty($item['personalizaciones']) ? 1 : 0
        ]);
        $id_item_pedido = $pdo->lastInsertId();
        
        // Insertar personalizaciones si existen
        if (!empty($item['personalizaciones'])) {
            foreach ($item['personalizaciones'] as $pers) {
                $stmt = $pdo->prepare("INSERT INTO personalizaciones_item (id_opcion, id_item_pedido) VALUES (?, ?)");
                $stmt->execute([
                    $pers['id_opcion'],
                    $id_item_pedido
                ]);
            }
        }
    }
    
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'id_pedido' => $id_pedido, 
        'numero_pedido' => $numero_pedido,
        'metodo_pago' => $metodo_pago
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error al guardar el pedido: ' . $e->getMessage()
    ]);
}
?>