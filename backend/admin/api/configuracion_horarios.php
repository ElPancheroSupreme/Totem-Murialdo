<?php
// api/configuracion_horarios.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Evitar que errores PHP rompan el JSON

try {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $horariosFile = __DIR__ . '/../../config/horarios.json';


switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $default = [
            'habilitar_horarios' => true,
            'modo_mantenimiento' => false,
            'dias' => [
                'lunes' => ['habilitado' => true, 'desde' => '07:30', 'hasta' => '13:00'],
                'martes' => ['habilitado' => true, 'desde' => '07:30', 'hasta' => '13:00'],
                'miércoles' => ['habilitado' => true, 'desde' => '07:30', 'hasta' => '13:00'],
                'jueves' => ['habilitado' => true, 'desde' => '07:30', 'hasta' => '13:00'],
                'viernes' => ['habilitado' => true, 'desde' => '07:30', 'hasta' => '13:00'],
                'sábado' => ['habilitado' => true, 'desde' => '07:30', 'hasta' => '13:00'],
                'domingo' => ['habilitado' => true, 'desde' => '07:30', 'hasta' => '13:00'],
            ],
            'monto_minimo_mp' => 1.00,
            'metodos_pago' => [
                'efectivo' => true,
                'qr' => true
            ],
            'max_unidades_carrito' => 10,
            'tiempo_inactividad' => 300
        ];
        if (file_exists($horariosFile)) {
            $json = file_get_contents($horariosFile);
            $data = json_decode($json, true);
            // Validar y migrar formato si es necesario
            if (!isset($data['habilitar_horarios']) || !isset($data['dias'])) {
                $data = $default;
            }
            // Migrar modo_mantenimiento si no existe
            if (!isset($data['modo_mantenimiento'])) {
                $data['modo_mantenimiento'] = $default['modo_mantenimiento'];
            }
            // Migrar monto_minimo_mp si no existe
            if (!isset($data['monto_minimo_mp'])) {
                $data['monto_minimo_mp'] = $default['monto_minimo_mp'];
            }
            // Migrar metodos_pago si no existe
            if (!isset($data['metodos_pago']) || !is_array($data['metodos_pago'])) {
                $data['metodos_pago'] = $default['metodos_pago'];
            } else {
                // Asegurar que existan las claves 'efectivo' y 'qr'
                if (!isset($data['metodos_pago']['efectivo'])) $data['metodos_pago']['efectivo'] = true;
                if (!isset($data['metodos_pago']['qr'])) $data['metodos_pago']['qr'] = true;
            }
            // Migrar max_unidades_carrito si no existe
            if (!isset($data['max_unidades_carrito']) || !is_numeric($data['max_unidades_carrito'])) {
                $data['max_unidades_carrito'] = $default['max_unidades_carrito'];
            } else {
                $data['max_unidades_carrito'] = max(1, intval($data['max_unidades_carrito']));
            }
            // Migrar tiempo_inactividad si no existe
            if (!isset($data['tiempo_inactividad']) || !is_numeric($data['tiempo_inactividad'])) {
                $data['tiempo_inactividad'] = $default['tiempo_inactividad'];
            } else {
                $data['tiempo_inactividad'] = max(1, intval($data['tiempo_inactividad'])); // Mínimo 1 segundo
            }
        } else {
            $data = $default;
        }
        echo json_encode(['success' => true, 'config' => $data], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            exit;
        }
        // Validar estructura mínima
        if (!isset($data['habilitar_horarios']) || !isset($data['dias'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Estructura inválida']);
            exit;
        }
        // Validar modo_mantenimiento
        if (!isset($data['modo_mantenimiento'])) {
            $data['modo_mantenimiento'] = false;
        } else {
            $data['modo_mantenimiento'] = !!$data['modo_mantenimiento'];
        }
        // Validar y normalizar monto_minimo_mp
        if (!isset($data['monto_minimo_mp']) || !is_numeric($data['monto_minimo_mp']) || $data['monto_minimo_mp'] < 0) {
            $data['monto_minimo_mp'] = 0;
        } else {
            $data['monto_minimo_mp'] = round(floatval($data['monto_minimo_mp']), 2);
        }
        // Validar y normalizar max_unidades_carrito
        if (!isset($data['max_unidades_carrito']) || !is_numeric($data['max_unidades_carrito'])) {
            $data['max_unidades_carrito'] = 10;
        } else {
            $data['max_unidades_carrito'] = max(1, intval($data['max_unidades_carrito']));
        }
        // Validar y normalizar tiempo_inactividad
        if (!isset($data['tiempo_inactividad']) || !is_numeric($data['tiempo_inactividad'])) {
            $data['tiempo_inactividad'] = 300;
        } else {
            $data['tiempo_inactividad'] = max(1, intval($data['tiempo_inactividad'])); // Mínimo 1 segundo
        }
        // Validar metodos_pago
        if (!isset($data['metodos_pago']) || !is_array($data['metodos_pago'])) {
            $data['metodos_pago'] = [ 'efectivo' => true, 'qr' => true ];
        } else {
            if (!isset($data['metodos_pago']['efectivo'])) $data['metodos_pago']['efectivo'] = true;
            if (!isset($data['metodos_pago']['qr'])) $data['metodos_pago']['qr'] = true;
            $data['metodos_pago']['efectivo'] = !!$data['metodos_pago']['efectivo'];
            $data['metodos_pago']['qr'] = !!$data['metodos_pago']['qr'];
        }
        // Intentar escribir al archivo
        $writeResult = @file_put_contents($horariosFile, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        
        if ($writeResult === false) {
            // Si no se puede escribir, aún devolver éxito para que la UI funcione
            echo json_encode([
                'success' => true, 
                'warning' => 'Configuración procesada pero no se pudo guardar en el archivo',
                'file_writable' => is_writable($horariosFile),
                'file_exists' => file_exists($horariosFile)
            ]);
        } else {
            echo json_encode(['success' => true, 'saved' => true]);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

} catch (Exception $e) {
    error_log("Error en configuracion_horarios.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
} catch (Error $e) {
    error_log("Error fatal en configuracion_horarios.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error fatal del servidor',
        'message' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
}
?>
