<?php
// Script para verificar estructura de la base de datos
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

try {
    require_once '../config/pdo_connection.php';
    
    echo "=== VERIFICACIÓN DE BASE DE DATOS ===\n\n";
    
    // Verificar tabla categorias
    echo "ESTRUCTURA DE TABLA CATEGORIAS:\n";
    $stmt = $pdo->query('DESCRIBE categorias');
    $cols = $stmt->fetchAll();
    foreach ($cols as $col) {
        echo "- {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']} {$col['Default']}\n";
    }
    
    echo "\nDATA DE CATEGORIAS:\n";
    $stmt = $pdo->query('SELECT * FROM categorias LIMIT 5');
    $cats = $stmt->fetchAll();
    foreach ($cats as $cat) {
        echo "ID: {$cat['id_categoria']}, Nombre: {$cat['nombre']}\n";
    }
    
    echo "\n\nESTRUCTURA DE TABLA PRODUCTOS:\n";
    $stmt = $pdo->query('DESCRIBE productos');
    $cols = $stmt->fetchAll();
    foreach ($cols as $col) {
        echo "- {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']} {$col['Default']}\n";
    }
    
    echo "\nDATA DE PRODUCTOS:\n";
    $stmt = $pdo->query('SELECT id_producto, nombre, id_categoria, estado FROM productos LIMIT 5');
    $prods = $stmt->fetchAll();
    foreach ($prods as $prod) {
        echo "ID: {$prod['id_producto']}, Nombre: {$prod['nombre']}, Categoria: {$prod['id_categoria']}, Estado: {$prod['estado']}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
?>
