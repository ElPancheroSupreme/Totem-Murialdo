<?php
// Investigar problema específico con MercadoPago
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Solo POST');
    }
    
    echo json_encode(['step' => 1, 'message' => 'POST verificado']) . "\n";
    
    require __DIR__ . '/../../vendor/autoload.php';
    echo json_encode(['step' => 2, 'message' => 'Autoload cargado']) . "\n";
    
    require __DIR__ . '/../config/config.php';
    echo json_encode(['step' => 3, 'message' => 'Config cargado']) . "\n";
    
    // Verificar si la clase existe antes de importarla
    if (class_exists('MercadoPago\MercadoPagoConfig')) {
        echo json_encode(['step' => 4, 'message' => 'Clase MercadoPagoConfig existe']) . "\n";
    } else {
        throw new Exception('Clase MercadoPagoConfig no existe');
    }
    
    // Intentar hacer el use statement de forma más controlada
    echo json_encode(['step' => 5, 'message' => 'Intentando importar MercadoPagoConfig...']) . "\n";
    
    // En lugar de use, vamos a usar la clase directamente
    $config_class = 'MercadoPago\MercadoPagoConfig';
    
    if (class_exists($config_class)) {
        echo json_encode(['step' => 6, 'message' => 'Clase accesible mediante string']) . "\n";
        
        // Intentar acceder a métodos estáticos
        $methods = get_class_methods($config_class);
        echo json_encode(['step' => 7, 'message' => 'Métodos obtenidos', 'methods' => array_slice($methods, 0, 5)]) . "\n";
        
    } else {
        throw new Exception('Clase no accesible mediante string');
    }
    
    echo json_encode(['success' => true, 'message' => 'Investigación completada']);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
} catch (Error $e) {
    echo json_encode(['fatal_error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>
