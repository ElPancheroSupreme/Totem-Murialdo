<?php
// Archivo super simplificado para diagnosticar el problema
header('Content-Type: application/json');

// Capturar cualquier output inesperado
ob_start();

try {
    // Solo leer los datos POST
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    // Limpiar cualquier output previo
    ob_clean();
    
    // Respuesta simple
    echo json_encode([
        'success' => true,
        'message' => 'Archivo funcionando correctamente',
        'received_data' => $input,
        'input_length' => strlen($raw_input)
    ]);
    
} catch (Exception $e) {
    // Limpiar cualquier output previo
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename(__FILE__)
    ]);
}

// Finalizar output buffering
ob_end_flush();
?>