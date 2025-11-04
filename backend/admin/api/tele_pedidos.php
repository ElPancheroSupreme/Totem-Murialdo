<?php
// backend/api/tele_pedidos.php
header('Content-Type: application/json');

// Conectar directamente a la base de datos
require_once __DIR__ . '/../../config/pdo_connection.php';

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
    
    // Modificar el formato del número de pedido
    foreach ($pedidos as &$pedido) {
        // Si el número es tipo K001-003 o K002-005, lo pasamos a K-003
        if (preg_match('/^K\d{3}-(\d+)$/', $pedido['numero_pedido'], $matches)) {
            $pedido['numero_pedido'] = 'K-' . $matches[1];
        }
    }
    unset($pedido);

    $preparacion = [];
    $listos = [];
    
    foreach ($pedidos as $pedido) {
        $estado = strtolower($pedido['estado'] ?? '');
        $codigo = $pedido['numero_pedido'] ?? $pedido['id_pedido'];
        
        if (in_array($estado, ['preparacion', 'en preparacion', 'preparando'])) {
            $preparacion[] = ["codigo" => $codigo];
        } elseif (in_array($estado, ['preparado', 'listo'])) {
            $listos[] = ["codigo" => $codigo];
        }
    }
    
    echo json_encode(["preparacion" => $preparacion, "listos" => $listos]);
    
} catch (Exception $e) {
    echo json_encode(["preparacion" => [], "listos" => [], "error" => $e->getMessage()]);
}
