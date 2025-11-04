<?php
// Deshabilitar la visualización de errores de PHP
ini_set('display_errors', 'Off');
error_reporting(0);

// Asegurarnos que la respuesta siempre sea JSON
header('Content-Type: application/json');

// Manejar todos los errores como JSON
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    exit;
});

require_once '../../config/pdo_connection.php';

if (isset($_GET['detalle'])) {
    $id_pedido = intval($_GET['detalle']);
    $stmt = $pdo->prepare('SELECT i.id_item_pedido, i.cantidad, p.nombre, p.precio_venta as precio_unitario FROM items_pedido i JOIN productos p ON i.id_producto = p.id_producto WHERE i.id_pedido = ?');
    $stmt->execute([$id_pedido]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener personalizaciones para cada item
    foreach ($items as &$item) {
        $stmt_personalizaciones = $pdo->prepare('
            SELECT op.nombre_opcion 
            FROM personalizaciones_item pi 
            JOIN opciones_personalizacion op ON pi.id_opcion = op.id_opcion 
            WHERE pi.id_item_pedido = ?
        ');
        $stmt_personalizaciones->execute([$item['id_item_pedido']]);
        $personalizaciones = $stmt_personalizaciones->fetchAll(PDO::FETCH_COLUMN);
        $item['personalizaciones'] = $personalizaciones;
    }
    unset($item);
    
    echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->query('
        SELECT p.id_pedido, p.numero_pedido, p.monto_total, p.estado, p.creado_en, p.metodo_pago,
               pv.nombre as punto_venta
        FROM pedidos p
        LEFT JOIN puntos_venta pv ON p.id_punto_venta = pv.id_punto_venta
        ORDER BY p.creado_en DESC
        LIMIT 100
    ');
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Modificar el formato del número de pedido y agregar cantidad de items
    foreach ($pedidos as &$pedido) {
        // Si el número es tipo K001-003 o K002-005, lo pasamos a K-003
        if (preg_match('/^K\d{3}-(\d+)$/', $pedido['numero_pedido'], $matches)) {
            $pedido['numero_pedido'] = 'K-' . $matches[1];
        }
        // Calcular cantidad de items
        $stmt2 = $pdo->prepare('SELECT SUM(cantidad) as total_items FROM items_pedido WHERE id_pedido = ?');
        $stmt2->execute([$pedido['id_pedido']]);
        $pedido['cantidad_items'] = (int)($stmt2->fetchColumn() ?: 0);
    }
    unset($pedido);
    echo json_encode(['success' => true, 'pedidos' => $pedidos], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 