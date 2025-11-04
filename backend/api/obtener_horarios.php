<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/pdo_connection.php';

try {
    // Obtener conexión PDO
    $pdo = get_pdo_connection();
    
    // Consulta para obtener horarios habilitados ordenados por hora
    $stmt = $pdo->prepare("
        SELECT id, hora, texto, habilitado 
        FROM horarios 
        WHERE habilitado = 1 
        ORDER BY hora ASC
    ");
    
    $stmt->execute();
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los horarios para el frontend
    $horariosFormateados = [];
    foreach ($horarios as $horario) {
        // Convertir TIME a formato HH:MM para mostrar
        $horaFormateada = date('H:i', strtotime($horario['hora']));
        
        $horariosFormateados[] = [
            'id' => $horario['id'],
            'hora' => $horaFormateada,
            'texto' => $horario['texto'],
            'habilitado' => $horario['habilitado']
        ];
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => $horariosFormateados,
        'total' => count($horariosFormateados)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Error de base de datos
    error_log("Error en obtener_horarios.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener horarios de la base de datos',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Error general
    error_log("Error general en obtener_horarios.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>