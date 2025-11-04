<?php
// Deshabilitar la visualización de errores de PHP
ini_set('display_errors', 'Off');
error_reporting(0);

// Asegurarnos que la respuesta siempre sea JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar todos los errores como JSON
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../config/pdo_connection.php';

function error_response($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {
        case 'dashboard':
            // Obtener estadísticas generales del dashboard según el filtro
            $filtro = $_GET['filtro'] ?? 'hoy';
            $where = "1";
            if ($filtro === 'hoy') {
                $where = "DATE(creado_en) = CURDATE()";
            } elseif ($filtro === 'semana') {
                $where = "YEARWEEK(creado_en, 1) = YEARWEEK(CURDATE(), 1)"; // Modo 1: semana inicia lunes
            } elseif ($filtro === 'mes') {
                $where = "YEAR(creado_en) = YEAR(CURDATE()) AND MONTH(creado_en) = MONTH(CURDATE())";
            } elseif ($filtro === '3meses') {
                $where = "creado_en >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            } elseif (strpos($filtro, 'personalizado_') === 0) {
                // Filtro personalizado: personalizado_2025-09-01_2025-09-30
                $partes = explode('_', $filtro);
                if (count($partes) === 3) {
                    $fechaInicio = $partes[1];
                    $fechaFin = $partes[2];
                    $where = "DATE(creado_en) BETWEEN '$fechaInicio' AND '$fechaFin'";
                }
            }

            $stats = [];
            
            // 1. Ventas según el filtro
            $stmt = $pdo->query("SELECT COALESCE(SUM(monto_total), 0) as total_ventas FROM pedidos WHERE $where");
            $stats['ventas_dia'] = $stmt->fetch()['total_ventas'];
            
            // 2. Total de pedidos según el filtro
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE $where");
            $stats['total_pedidos_hoy'] = $stmt->fetch()['total'];
            
            // 3. Pedidos en curso (pendientes, en preparación, preparados) según el filtro
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE estado IN ('PENDIENTE', 'EN_PREPARACION', 'PREPARADO', 'preparacion', 'preparando', 'pendiente') AND $where");
            $stats['pedidos_curso'] = $stmt->fetch()['total'];
            
            // 4. Pedidos pendientes según el filtro
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE estado = 'PENDIENTE' AND $where");
            $stats['pedidos_pendientes'] = $stmt->fetch()['total'];
            
            // 5. Método de pago más usado según el filtro
            $stmt = $pdo->query("SELECT metodo_pago, COUNT(*) as total FROM pedidos WHERE $where GROUP BY metodo_pago ORDER BY total DESC LIMIT 1");
            $row = $stmt->fetch();
            $stats['metodo_mas_usado'] = $row ? $row['metodo_pago'] : 'N/A';
            
            echo json_encode(['success' => true, 'data' => $stats], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'ventas_por_dia':
            // Obtener ventas según el filtro
            $filtro = $_GET['filtro'] ?? 'hoy';
            $where = "1";
            $interval = "7 DAY";
            if ($filtro === 'hoy') {
                $where = "DATE(creado_en) = CURDATE()";
                $interval = "1 DAY";
            } elseif ($filtro === 'semana') {
                $where = "YEARWEEK(creado_en, 1) = YEARWEEK(CURDATE(), 1)"; // Modo 1: semana inicia lunes
                $interval = "7 DAY";
            } elseif ($filtro === 'mes') {
                $where = "YEAR(creado_en) = YEAR(CURDATE()) AND MONTH(creado_en) = MONTH(CURDATE())";
                $interval = "30 DAY";
            } elseif ($filtro === '3meses') {
                $where = "creado_en >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                $interval = "90 DAY";
            } elseif (strpos($filtro, 'personalizado_') === 0) {
                // Filtro personalizado: personalizado_2025-09-01_2025-09-30
                $partes = explode('_', $filtro);
                if (count($partes) === 3) {
                    $fechaInicio = $partes[1];
                    $fechaFin = $partes[2];
                    $where = "DATE(creado_en) BETWEEN '$fechaInicio' AND '$fechaFin'";
                    $interval = "CUSTOM";
                }
            }
            
            $stmt = $pdo->query("
                SELECT 
                    DATE(creado_en) as fecha,
                    COUNT(*) as total_pedidos,
                    COALESCE(SUM(monto_total), 0) as total_ventas
                FROM pedidos 
                WHERE $where
                GROUP BY DATE(creado_en)
                ORDER BY fecha
            ");
            $ventas_por_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $ventas_por_dia], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'metodos_pago':
            // Obtener distribución de métodos de pago según el filtro
            $filtro = $_GET['filtro'] ?? 'hoy';
            $where = "1";
            if ($filtro === 'hoy') {
                $where = "DATE(creado_en) = CURDATE()";
            } elseif ($filtro === 'semana') {
                $where = "YEARWEEK(creado_en, 1) = YEARWEEK(CURDATE(), 1)"; // Modo 1: semana inicia lunes
            } elseif ($filtro === 'mes') {
                $where = "YEAR(creado_en) = YEAR(CURDATE()) AND MONTH(creado_en) = MONTH(CURDATE())";
            } elseif ($filtro === '3meses') {
                $where = "creado_en >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            } elseif (strpos($filtro, 'personalizado_') === 0) {
                // Filtro personalizado: personalizado_2025-09-01_2025-09-30
                $partes = explode('_', $filtro);
                if (count($partes) === 3) {
                    $fechaInicio = $partes[1];
                    $fechaFin = $partes[2];
                    $where = "DATE(creado_en) BETWEEN '$fechaInicio' AND '$fechaFin'";
                }
            }
            
            $stmt = $pdo->query("
                SELECT 
                    metodo_pago,
                    COUNT(*) as total_pedidos,
                    COALESCE(SUM(monto_total), 0) as total_ventas
                FROM pedidos 
                WHERE $where
                GROUP BY metodo_pago
                ORDER BY total_pedidos DESC
            ");
            $metodos_pago = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $metodos_pago], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'top_productos':
            // Obtener top 5 productos más vendidos
            $stmt = $pdo->query("
                SELECT 
                    p.nombre,
                    SUM(ip.cantidad) as unidades_vendidas,
                    COALESCE(SUM(ip.precio_total_item), 0) as ingresos_totales
                FROM items_pedido ip
                JOIN productos p ON ip.id_producto = p.id_producto
                JOIN pedidos ped ON ip.id_pedido = ped.id_pedido
                WHERE ped.creado_en >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY p.id_producto, p.nombre
                ORDER BY unidades_vendidas DESC
                LIMIT 5
            ");
            $top_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $top_productos], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'pedidos_por_hora':
            // Obtener pedidos por hora según el filtro
            $filtro = $_GET['filtro'] ?? 'hoy';
            $where = "1";
            if ($filtro === 'hoy') {
                $where = "DATE(creado_en) = CURDATE()";
            } elseif ($filtro === 'semana') {
                $where = "YEARWEEK(creado_en, 1) = YEARWEEK(CURDATE(), 1)"; // Modo 1: semana inicia lunes
            } elseif ($filtro === 'mes') {
                $where = "YEAR(creado_en) = YEAR(CURDATE()) AND MONTH(creado_en) = MONTH(CURDATE())";
            } elseif ($filtro === '3meses') {
                $where = "creado_en >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            } elseif (strpos($filtro, 'personalizado_') === 0) {
                // Filtro personalizado: personalizado_2025-09-01_2025-09-30
                $partes = explode('_', $filtro);
                if (count($partes) === 3) {
                    $fechaInicio = $partes[1];
                    $fechaFin = $partes[2];
                    $where = "DATE(creado_en) BETWEEN '$fechaInicio' AND '$fechaFin'";
                }
            }
            
            $stmt = $pdo->query("
                SELECT 
                    HOUR(creado_en) as hora,
                    COUNT(*) as total_pedidos
                FROM pedidos 
                WHERE $where AND HOUR(creado_en) >= 6 AND HOUR(creado_en) <= 23
                GROUP BY HOUR(creado_en)
                ORDER BY hora
            ");
            $pedidos_por_hora = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Rellenar horas sin pedidos con 0 (solo de 6 AM a 11 PM)
            $horas_completas = [];
            for ($i = 6; $i <= 23; $i++) {
                $encontrado = false;
                foreach ($pedidos_por_hora as $pedido) {
                    if ($pedido['hora'] == $i) {
                        $horas_completas[] = $pedido;
                        $encontrado = true;
                        break;
                    }
                }
                if (!$encontrado) {
                    $horas_completas[] = ['hora' => $i, 'total_pedidos' => 0];
                }
            }
            
            echo json_encode(['success' => true, 'data' => $horas_completas], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'resumen_semanal':
            // Obtener resumen semanal
            $stats = [];
            
            // Ingresos totales de la semana
            $stmt = $pdo->query("
                SELECT COALESCE(SUM(monto_total), 0) as total_ventas 
                FROM pedidos 
                WHERE YEARWEEK(creado_en) = YEARWEEK(CURDATE())
            ");
            $stats['ingresos_semana'] = $stmt->fetch()['total_ventas'];
            
            // Total pedidos de la semana
            $stmt = $pdo->query("
                SELECT COUNT(*) as total_pedidos 
                FROM pedidos 
                WHERE YEARWEEK(creado_en) = YEARWEEK(CURDATE())
            ");
            $stats['total_pedidos_semana'] = $stmt->fetch()['total_pedidos'];
            
            // Ticket promedio
            $stmt = $pdo->query("
                SELECT COALESCE(AVG(monto_total), 0) as ticket_promedio 
                FROM pedidos 
                WHERE YEARWEEK(creado_en) = YEARWEEK(CURDATE())
            ");
            $stats['ticket_promedio'] = $stmt->fetch()['ticket_promedio'];
            
            echo json_encode(['success' => true, 'data' => $stats], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'compras_totem':
            // Estadísticas filtradas por fecha
            $filtro = $_GET['filtro'] ?? 'hoy';
            $where = "1";
            if ($filtro === 'hoy') {
                $where = "DATE(creado_en) = CURDATE()";
            } elseif ($filtro === 'semana') {
                $where = "YEARWEEK(creado_en, 1) = YEARWEEK(CURDATE(), 1)"; // Modo 1: semana inicia lunes
            } elseif ($filtro === 'mes') {
                $where = "YEAR(creado_en) = YEAR(CURDATE()) AND MONTH(creado_en) = MONTH(CURDATE())";
            } elseif ($filtro === '3meses') {
                $where = "creado_en >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            } elseif (strpos($filtro, 'personalizado_') === 0) {
                // Filtro personalizado: personalizado_2025-09-01_2025-09-30
                $partes = explode('_', $filtro);
                if (count($partes) === 3) {
                    $fechaInicio = $partes[1];
                    $fechaFin = $partes[2];
                    $where = "DATE(creado_en) BETWEEN '$fechaInicio' AND '$fechaFin'";
                }
            }
            
            // Obtener todas las estadísticas necesarias en una sola respuesta
            $stats = [];
            
            // Total de compras
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE $where");
            $stats['total_compras'] = $stmt->fetch()['total'];
            
            // Ingresos totales
            $stmt = $pdo->query("SELECT COALESCE(SUM(monto_total), 0) as total FROM pedidos WHERE $where");
            $stats['ingresos_totales'] = $stmt->fetch()['total'];
            
            // Ticket promedio
            $stmt = $pdo->query("SELECT COALESCE(AVG(monto_total), 0) as promedio FROM pedidos WHERE $where");
            $stats['ticket_promedio'] = $stmt->fetch()['promedio'];
            
            echo json_encode(['success' => true, 'data' => $stats], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'rentabilidad':
            // Análisis de rentabilidad por producto
            $filtro = $_GET['filtro'] ?? 'hoy';
            
            try {
                // Verificar conexión básica
                if (!$pdo) {
                    throw new Exception('Error: conexión PDO no disponible');
                }
                
                // Primera consulta: obtener todos los productos con su información básica
                $queryProductos = "
                    SELECT 
                        id_producto,
                        nombre,
                        precio_venta,
                        COALESCE(precio_lista, 0) as precio_compra,
                        (precio_venta - COALESCE(precio_lista, 0)) as ganancia_unitaria,
                        CASE 
                            WHEN COALESCE(precio_lista, 0) > 0 THEN 
                                ROUND(((precio_venta - COALESCE(precio_lista, 0)) / precio_lista) * 100, 2)
                            ELSE 100 
                        END as margen_porcentaje
                    FROM productos 
                    WHERE estado = 1 AND eliminado = 0
                    ORDER BY nombre
                ";
                
                $stmt = $pdo->prepare($queryProductos);
                if (!$stmt) {
                    throw new Exception('Error preparando consulta de productos: ' . implode(' ', $pdo->errorInfo()));
                }
                
                $result = $stmt->execute();
                if (!$result) {
                    throw new Exception('Error ejecutando consulta de productos: ' . implode(' ', $stmt->errorInfo()));
                }
                
                $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($productos)) {
                    echo json_encode(['success' => true, 'data' => [], 'message' => 'No hay productos activos'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                // Segunda consulta: obtener ventas por producto según el filtro
                $whereCondition = "1=1";
                $params = [];
                
                if ($filtro === 'hoy') {
                    $whereCondition = "DATE(p.creado_en) = CURDATE()";
                } elseif ($filtro === 'semana') {
                    $whereCondition = "YEARWEEK(p.creado_en, 1) = YEARWEEK(CURDATE(), 1)";
                } elseif ($filtro === 'mes') {
                    $whereCondition = "YEAR(p.creado_en) = YEAR(CURDATE()) AND MONTH(p.creado_en) = MONTH(CURDATE())";
                } elseif ($filtro === '3meses') {
                    $whereCondition = "p.creado_en >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                } elseif (strpos($filtro, 'personalizado_') === 0) {
                    $partes = explode('_', $filtro);
                    if (count($partes) === 3) {
                        $whereCondition = "DATE(p.creado_en) BETWEEN ? AND ?";
                        $params = [$partes[1], $partes[2]];
                    }
                }
                
                $queryVentas = "
                    SELECT 
                        ip.id_producto,
                        SUM(ip.cantidad) as unidades_vendidas,
                        SUM(ip.cantidad * pr.precio_venta) as ingresos_totales
                    FROM items_pedido ip
                    INNER JOIN pedidos p ON ip.id_pedido = p.id_pedido
                    INNER JOIN productos pr ON ip.id_producto = pr.id_producto
                    WHERE $whereCondition
                    GROUP BY ip.id_producto
                ";
                
                $stmt2 = $pdo->prepare($queryVentas);
                if (!$stmt2) {
                    throw new Exception('Error preparando consulta de ventas: ' . implode(' ', $pdo->errorInfo()));
                }
                
                $result = $stmt2->execute($params);
                if (!$result) {
                    throw new Exception('Error ejecutando consulta de ventas: ' . implode(' ', $stmt2->errorInfo()));
                }
                
                $ventas = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                
                // Combinar datos
                $ventasPorProducto = [];
                foreach ($ventas as $venta) {
                    $ventasPorProducto[$venta['id_producto']] = $venta;
                }
                
                $rentabilidad = [];
                foreach ($productos as $producto) {
                    $idProducto = $producto['id_producto'];
                    $venta = $ventasPorProducto[$idProducto] ?? null;
                    
                    $unidadesVendidas = $venta ? intval($venta['unidades_vendidas']) : 0;
                    $ingresosTotales = $venta ? floatval($venta['ingresos_totales']) : 0;
                    $gananciaTotalCalculada = $unidadesVendidas * floatval($producto['ganancia_unitaria']);
                    
                    $rentabilidad[] = [
                        'nombre' => $producto['nombre'],
                        'precio_venta' => floatval($producto['precio_venta']),
                        'precio_compra' => floatval($producto['precio_compra']),
                        'ganancia_unitaria' => floatval($producto['ganancia_unitaria']),
                        'margen_porcentaje' => floatval($producto['margen_porcentaje']),
                        'unidades_vendidas' => $unidadesVendidas,
                        'ganancia_total' => $gananciaTotalCalculada,
                        'ingresos_totales' => $ingresosTotales
                    ];
                }
                
                // Ordenar por ganancia total descendente
                usort($rentabilidad, function($a, $b) {
                    if ($a['ganancia_total'] == $b['ganancia_total']) {
                        return $b['margen_porcentaje'] <=> $a['margen_porcentaje'];
                    }
                    return $b['ganancia_total'] <=> $a['ganancia_total'];
                });
                
                // Opcional: limitar resultados si se especifica un parámetro
                $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 0;
                if ($limite > 0) {
                    $rentabilidad = array_slice($rentabilidad, 0, $limite);
                }
                
                echo json_encode(['success' => true, 'data' => $rentabilidad], JSON_UNESCAPED_UNICODE);
                
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Error de base de datos en rentabilidad',
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Error en análisis de rentabilidad',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
        default:
            error_response('Acción no válida', 400);
    }
    
} catch (PDOException $e) {
    error_response('Error de base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_response('Error interno: ' . $e->getMessage(), 500);
}
?> 