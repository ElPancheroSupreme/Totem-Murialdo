<?php
require_once '../../config/pdo_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = get_pdo_connection();
    
    echo "=== VERIFICACIÓN DE ESTRUCTURA DB ===\n";
    
    // Verificar si la tabla empleados existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'empleados'");
    $stmt->execute();
    $tabla_existe = $stmt->rowCount() > 0;
    
    echo "Tabla 'empleados' existe: " . ($tabla_existe ? "SÍ" : "NO") . "\n";
    
    if (!$tabla_existe) {
        echo "Creando tabla 'empleados'...\n";
        
        $sql_crear_tabla = "
        CREATE TABLE IF NOT EXISTS empleados (
            id_empleado INT AUTO_INCREMENT PRIMARY KEY,
            usuario VARCHAR(50) UNIQUE NOT NULL,
            nombre_completo VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            telefono VARCHAR(20),
            rol ENUM('administrador', 'supervisor', 'empleado') DEFAULT 'empleado',
            password VARCHAR(255) NOT NULL,
            activo TINYINT(1) DEFAULT 1,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ultimo_acceso TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql_crear_tabla);
        echo "✅ Tabla 'empleados' creada exitosamente\n";
        
        // Insertar usuario administrador por defecto
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO empleados (usuario, nombre_completo, email, rol, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'Administrador Sistema', 'admin@murialdo.com', 'administrador', $admin_password]);
        
        echo "✅ Usuario administrador creado (usuario: admin, password: admin123)\n";
    }
    
    // Verificar estructura actual
    $stmt = $pdo->prepare("DESCRIBE empleados");
    $stmt->execute();
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== ESTRUCTURA TABLA EMPLEADOS ===\n";
    foreach ($columnas as $columna) {
        echo "- " . $columna['Field'] . " (" . $columna['Type'] . ")\n";
    }
    
    // Contar usuarios existentes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM empleados");
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    echo "\n=== ESTADÍSTICAS ===\n";
    echo "Total usuarios: $total\n";
    
    // Listar usuarios
    $stmt = $pdo->prepare("SELECT id_empleado, usuario, nombre_completo, rol, activo FROM empleados");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== USUARIOS EXISTENTES ===\n";
    foreach ($usuarios as $usuario) {
        $estado = $usuario['activo'] ? 'Activo' : 'Inactivo';
        echo "- ID: {$usuario['id_empleado']} | Usuario: {$usuario['usuario']} | Nombre: {$usuario['nombre_completo']} | Rol: {$usuario['rol']} | Estado: $estado\n";
    }
    
    echo "\n✅ Verificación completada\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
