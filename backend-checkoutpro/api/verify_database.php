<?php
// Verificación de estructura de base de datos
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Habilitar errores para debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once '../config/pdo_connection.php';
    
    if (!isset($pdo)) {
        throw new Exception("PDO no está definido");
    }
    
    $results = [];
    
    // Verificar existencia de tablas principales
    $tables = ['categorias', 'productos', 'opciones_personalizacion'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            $results['tables'][$table] = $exists;
            
            if ($exists) {
                // Contar registros
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $stmt->fetch()['count'];
                $results['counts'][$table] = $count;
                
                // Obtener estructura de la tabla
                $stmt = $pdo->query("DESCRIBE $table");
                $structure = $stmt->fetchAll();
                $results['structure'][$table] = $structure;
            }
        } catch (Exception $e) {
            $results['errors'][$table] = $e->getMessage();
        }
    }
    
    // Verificar datos de prueba en categorías
    try {
        $stmt = $pdo->query("SELECT * FROM categorias WHERE visible = 1 LIMIT 5");
        $categorias_sample = $stmt->fetchAll();
        $results['samples']['categorias'] = $categorias_sample;
    } catch (Exception $e) {
        $results['errors']['categorias_sample'] = $e->getMessage();
    }
    
    // Verificar datos de prueba en productos
    try {
        $stmt = $pdo->query("SELECT p.*, c.nombre as categoria_nombre FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.estado = 1 LIMIT 5");
        $productos_sample = $stmt->fetchAll();
        $results['samples']['productos'] = $productos_sample;
    } catch (Exception $e) {
        $results['errors']['productos_sample'] = $e->getMessage();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>
