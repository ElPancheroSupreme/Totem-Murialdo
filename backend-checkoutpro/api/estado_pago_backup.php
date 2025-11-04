<?php
// estado_pago.php - Devuelve el estado de una orden por external_reference

header('Content-Type: application/json');

$purchaseId = $_GET['external_reference'] ?? null;
if (!$purchaseId) {
    echo json_encode(['error' => 'Falta external_reference']);
    exit;
}

$statusFile = __DIR__ . "/../ordenes_status/orden_{$purchaseId}.json";
if (file_exists($statusFile)) {
    $data = json_decode(file_get_contents($statusFile), true);
    // Devolver todos los datos relevantes para el ticket
    echo json_encode($data);
} else {
    echo json_encode(['status' => 'pending']);
} 