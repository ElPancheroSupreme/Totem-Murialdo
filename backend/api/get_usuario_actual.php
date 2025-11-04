<?php
header('Content-Type: application/json');
require_once('../config/auth.php');

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['empleado'])) {
        echo json_encode([
            'success' => false,
            'autenticado' => false
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'autenticado' => true,
        'empleado' => $_SESSION['empleado']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener usuario actual'
    ]);
}
