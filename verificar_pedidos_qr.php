<?php
// Verificar pedidos VIRTUAL en la base de datos
require_once 'backend/config/pdo_connection.php';

try {
    // Conectar a la base de datos
    $pdo = get_pdo_connection();
    
    echo "=== VERIFICACIÓN DE PEDIDOS QR EN BASE DE DATOS ===\n\n";
    
    // Consultar pedidos con metodo_pago VIRTUAL
    $stmt = $pdo->prepare("SELECT id_pedido, numero_pedido, metodo_pago, estado, monto_total, creado_en FROM pedidos WHERE metodo_pago = 'VIRTUAL' ORDER BY id_pedido DESC LIMIT 10");
    $stmt->execute();
    $pedidos_virtual = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($pedidos_virtual) {
        echo "✅ Pedidos VIRTUAL encontrados:\n";
        foreach ($pedidos_virtual as $pedido) {
            echo "  - ID: {$pedido['id_pedido']}, Número: {$pedido['numero_pedido']}, Estado: {$pedido['estado']}, Total: {$pedido['monto_total']}, Fecha: {$pedido['creado_en']}\n";
        }
    } else {
        echo "❌ No se encontraron pedidos con metodo_pago VIRTUAL\n";
    }
    
    echo "\n";
    
    // Consultar los últimos 5 pedidos en general
    $stmt = $pdo->prepare("SELECT id_pedido, numero_pedido, metodo_pago, estado, monto_total, creado_en FROM pedidos ORDER BY id_pedido DESC LIMIT 5");
    $stmt->execute();
    $ultimos_pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Últimos 5 pedidos en general:\n";
    foreach ($ultimos_pedidos as $pedido) {
        echo "  - ID: {$pedido['id_pedido']}, Número: {$pedido['numero_pedido']}, Método: {$pedido['metodo_pago']}, Estado: {$pedido['estado']}, Total: {$pedido['monto_total']}\n";
    }
    
    // Verificar items de los pedidos VIRTUAL
    if ($pedidos_virtual) {
        echo "\n📦 Items de los pedidos VIRTUAL:\n";
        foreach ($pedidos_virtual as $pedido) {
            $stmt = $pdo->prepare("SELECT i.*, p.nombre as producto_nombre FROM items_pedido i LEFT JOIN productos p ON i.id_producto = p.id_producto WHERE i.id_pedido = ?");
            $stmt->execute([$pedido['id_pedido']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "  Pedido {$pedido['numero_pedido']} (ID: {$pedido['id_pedido']}):\n";
            foreach ($items as $item) {
                echo "    - {$item['producto_nombre']} x{$item['cantidad']} @ \${$item['precio_unitario']}\n";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
