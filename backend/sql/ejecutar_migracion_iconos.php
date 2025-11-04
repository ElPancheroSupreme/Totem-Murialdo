<?php
/**
 * Script PHP para ejecutar la migración de iconos de categorías
 * De emojis a rutas de archivos SVG
 */

header('Content-Type: application/json');

// Incluir configuración
require_once '../config/config.php';
require_once '../config/pdo_connection.php';

try {
    $pdo->beginTransaction();
    
    $results = [
        'success' => false,
        'message' => '',
        'details' => []
    ];

    // PASO 1: Modificar columna icono
    $results['details']['paso1'] = 'Modificando columna icono...';
    $sql1 = "ALTER TABLE categorias 
             MODIFY COLUMN icono VARCHAR(255) DEFAULT NULL 
             COMMENT 'Ruta del archivo SVG del icono de la categoría'";
    $pdo->exec($sql1);
    $results['details']['paso1'] = '✓ Columna modificada exitosamente';

    // PASO 2: Actualizar registros existentes
    $results['details']['paso2'] = 'Actualizando registros...';
    
    $updates = [
        "UPDATE categorias SET icono = 'Icono_Bebidas.svg' WHERE nombre = 'Bebidas'",
        "UPDATE categorias SET icono = 'Icono_Snacks.svg' WHERE nombre = 'Snacks'",
        "UPDATE categorias SET icono = 'Icono_Comidas.svg' WHERE nombre = 'Comidas'",
        "UPDATE categorias SET icono = 'Icono_Alfajores.svg' WHERE nombre = 'Alfajores'",
        "UPDATE categorias SET icono = 'Icono_Galletitas.svg' WHERE nombre = 'Galletitas'",
        "UPDATE categorias SET icono = 'Icono_Helados.svg' WHERE nombre = 'Helados'",
        "UPDATE categorias SET icono = 'Icono_Golosinas.svg' WHERE nombre = 'Golosinas'",
        "UPDATE categorias SET icono = 'Icono_Cafeteria.svg' WHERE nombre = 'Cafeteria'",
        "UPDATE categorias SET icono = 'Icono_Especial.svg' WHERE nombre = 'Especial'"
    ];

    $actualizados = 0;
    foreach ($updates as $update) {
        $stmt = $pdo->prepare($update);
        $stmt->execute();
        $actualizados += $stmt->rowCount();
    }
    
    $results['details']['paso2'] = "✓ {$actualizados} registros actualizados";

    // PASO 3: Verificar cambios
    $results['details']['paso3'] = 'Verificando cambios...';
    $stmt = $pdo->query("SELECT id_categoria, nombre, icono, visible FROM categorias ORDER BY orden ASC");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results['details']['paso3'] = '✓ Verificación completada';
    $results['details']['categorias_actualizadas'] = $categorias;

    // Confirmar transacción
    $pdo->commit();
    
    $results['success'] = true;
    $results['message'] = "✅ Migración completada exitosamente!\n\n";
    $results['message'] .= "- Columna 'icono' modificada a VARCHAR(255)\n";
    $results['message'] .= "- {$actualizados} categorías actualizadas\n";
    $results['message'] .= "- Sistema de iconos SVG activado\n\n";
    $results['message'] .= "Ahora puedes subir iconos SVG personalizados desde el ConfigDash.";

} catch (Exception $e) {
    // Revertir cambios si hay error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $results = [
        'success' => false,
        'error' => $e->getMessage(),
        'details' => [
            'mensaje' => 'La migración falló y se revirtieron los cambios',
            'error_detallado' => $e->getMessage(),
            'linea' => $e->getLine()
        ]
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
