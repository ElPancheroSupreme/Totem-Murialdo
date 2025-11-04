<?php
// backend/admin/api/avanzar_estado_pedido.php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_pedido = isset($data['id_pedido']) ? intval($data['id_pedido']) : null;
$estado_actual = isset($data['estado_actual']) ? strtolower(trim($data['estado_actual'])) : null;

if (!$id_pedido || !$estado_actual) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos']);
    exit;
}

require_once __DIR__ . '/../../config/pdo_connection.php';

// Orden de estados de menos a más avanzado
$orden_estados = ['pendiente', 'preparacion', 'listo', 'entregado'];
$idx = array_search($estado_actual, $orden_estados);
if ($idx === false || $idx === count($orden_estados) - 1) {
    echo json_encode(['success' => false, 'error' => 'No se puede avanzar más el estado']);
    exit;
}
$estado_nuevo = $orden_estados[$idx + 1];

try {
    $stmt = $pdo->prepare('UPDATE pedidos SET estado = ? WHERE id_pedido = ?');
    $stmt->execute([$estado_nuevo, $id_pedido]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'nuevo_estado' => $estado_nuevo]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo actualizar el estado']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
