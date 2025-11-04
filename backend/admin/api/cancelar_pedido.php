<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// backend/admin/api/cancelar_pedido.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_pedido = isset($data['id_pedido']) ? intval($data['id_pedido']) : null;

if (!$id_pedido) {
    echo json_encode(['success' => false, 'error' => 'Falta el id del pedido']);
    exit;
}

require_once __DIR__ . '/../../config/pdo_connection.php';

try {
    $stmt = $pdo->prepare('UPDATE pedidos SET estado = ? WHERE id_pedido = ?');
    $stmt->execute(['cancelado', $id_pedido]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se encontró el pedido o ya estaba cancelado']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
