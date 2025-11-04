<?php
// Headers DEBEN ir al inicio para evitar problemas con output buffering
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Incluir conexión PDO
require_once '../../config/pdo_connection.php';

// Verificar que la conexión PDO esté disponible
if ($pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

function enviarPedido() {
    // Validar y obtener datos
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    if (!$data || !isset($data['id_proveedor']) || !isset($data['productos']) || !isset($data['mensaje'])) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos para enviar el pedido']);
        return;
    }
    // Obtener datos del proveedor para WhatsApp/mail
    global $pdo;
    $stmt = $pdo->prepare('SELECT telefono, email, nombre_empresa, persona_contacto FROM proveedores WHERE id_proveedor = :id');
    $stmt->execute(['id' => $data['id_proveedor']]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

    $mensaje = $data['mensaje'];
    
    // Configurar vía de envío ANTES de generar URLs
    $via_envio = isset($data['via_envio']) ? $data['via_envio'] : 'whatsapp';
    
    // Preparar la lista de productos solicitados como texto (una sola vez)
    $productos_lista = [];
    foreach ($data['productos'] as $producto_data) {
        // Si viene como objeto con id_producto y cantidad
        if (is_array($producto_data) && isset($producto_data['id_producto'])) {
            $id_producto = $producto_data['id_producto'];
            $cantidad = isset($producto_data['cantidad']) ? (int)$producto_data['cantidad'] : 1;
        } else {
            // Si viene como simple ID (compatibilidad)
            $id_producto = $producto_data;
            $cantidad = 1;
        }
        
        // Obtener nombre del producto
        $stmt_prod = $pdo->prepare('SELECT nombre FROM productos WHERE id_producto = :id');
        $stmt_prod->execute(['id' => $id_producto]);
        $producto = $stmt_prod->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            $productos_lista[] = "- " . $producto['nombre'] . " x" . $cantidad;
        }
    }
    
    $productos_texto = implode("\n", $productos_lista);
    
    // Reemplazo dinámico de [CONTACTO] por el nombre de contacto del proveedor
    if (strpos($mensaje, '[CONTACTO]') !== false) {
        $contacto = ($proveedor && !empty($proveedor['persona_contacto'])) ? $proveedor['persona_contacto'] : '';
        $mensaje = str_replace('[CONTACTO]', $contacto, $mensaje);
    }
    
    // Reemplazo dinámico de [PRODUCTOS] con la lista ya preparada
    if (strpos($mensaje, '[PRODUCTOS]') !== false) {
        $mensaje = str_replace('[PRODUCTOS]', $productos_texto, $mensaje);
    }
    $whatsapp_url = null;
    $email_url = null;
    
    if ($proveedor) {
        // Debug: verificar datos del proveedor
        error_log("DEBUG - Proveedor encontrado: " . $proveedor['nombre_empresa']);
        error_log("DEBUG - Email proveedor: '" . $proveedor['email'] . "'");
        error_log("DEBUG - Teléfono proveedor: '" . $proveedor['telefono'] . "'");
        error_log("DEBUG - Vía de envío seleccionada: '" . $via_envio . "'");
        error_log("DEBUG - Email vacío? " . (empty($proveedor['email']) ? 'SÍ' : 'NO'));
        
        // Generar URL según la vía de envío seleccionada
        if ($via_envio === 'whatsapp' && !empty($proveedor['telefono'])) {
            // WhatsApp: solo si hay teléfono y se seleccionó WhatsApp
            $telefono = preg_replace('/[^0-9]/', '', $proveedor['telefono']);
            // Formato internacional para WhatsApp
            if (strpos($telefono, '54') === 0 && strlen($telefono) > 10) {
                // Si es Argentina y tiene 11 dígitos, dejar así
            } else if (strlen($telefono) == 10) {
                $telefono = '54' . $telefono;
            }
            $mensaje_wa = urlencode($mensaje);
            $whatsapp_url = "https://web.whatsapp.com/send/?phone=$telefono&text=$mensaje_wa";
            
        } else if ($via_envio === 'email' && !empty($proveedor['email'])) {
            error_log("DEBUG - Entrando a generar email URL");
            // Email: solo si hay email y se seleccionó Email
            $asunto = urlencode('Pedido para ' . $proveedor['nombre_empresa']);
            $cuerpo = urlencode($mensaje);
            
            // URL para abrir Gmail web con el mensaje pre-cargado
            $email_url = "https://mail.google.com/mail/?view=cm&fs=1&to=" . urlencode($proveedor['email']) . "&su=" . $asunto . "&body=" . $cuerpo;
            error_log("DEBUG - Gmail URL generada: " . $email_url);
        } else {
            error_log("DEBUG - No se genera URL porque:");
            error_log("DEBUG - via_envio === 'email'? " . ($via_envio === 'email' ? 'SÍ' : 'NO'));
            error_log("DEBUG - !empty(email)? " . (!empty($proveedor['email']) ? 'SÍ' : 'NO'));
        }
    }
    
    // Guardar el pedido en la base de datos
    try {
        // Preparar la lista de productos solicitados como texto
        $productos_texto = [];
        foreach ($data['productos'] as $producto_data) {
            // Si viene como objeto con id_producto y cantidad
            if (is_array($producto_data) && isset($producto_data['id_producto'])) {
                $id_producto = $producto_data['id_producto'];
                $cantidad = isset($producto_data['cantidad']) ? (int)$producto_data['cantidad'] : 1;
            } else {
                // Si viene como simple ID (compatibilidad)
                $id_producto = $producto_data;
                $cantidad = 1;
            }
            
            // Obtener nombre del producto
            $stmt_prod = $pdo->prepare('SELECT nombre FROM productos WHERE id_producto = :id');
            $stmt_prod->execute(['id' => $id_producto]);
            $producto = $stmt_prod->fetch(PDO::FETCH_ASSOC);
            
            if ($producto) {
                $productos_texto[] = "- " . $producto['nombre'] . " x" . $cantidad;
            }
        }
        
        $productos_solicitados = implode("\n", $productos_texto);
        
        // Log para debugging
        error_log("DEBUG - Enviando pedido a proveedor ID: " . $data['id_proveedor']);
        error_log("DEBUG - Mensaje procesado (primeros 100 chars): " . substr($mensaje, 0, 100));
        error_log("DEBUG - Productos solicitados: " . $productos_solicitados);
        error_log("DEBUG - Vía de envío: " . $via_envio);
        error_log("DEBUG - Longitud del mensaje: " . strlen($mensaje));
        
        // Insertar en pedidos_proveedor con mejor manejo de errores
        $stmt_insert = $pdo->prepare('
            INSERT INTO pedidos_proveedor 
            (id_proveedor, productos_solicitados, mensaje_enviado, via_envio, creado_en) 
            VALUES (:id_proveedor, :productos_solicitados, :mensaje_enviado, :via_envio, NOW())
        ');
        
        $resultado = $stmt_insert->execute([
            'id_proveedor' => $data['id_proveedor'],
            'productos_solicitados' => $productos_solicitados,
            'mensaje_enviado' => $mensaje,
            'via_envio' => $via_envio
        ]);
        
        if (!$resultado) {
            error_log("ERROR - Fallo al insertar pedido: " . print_r($stmt_insert->errorInfo(), true));
            throw new Exception("Error al guardar pedido en base de datos");
        }
        
        $id_pedido = $pdo->lastInsertId();
        error_log("SUCCESS - Pedido guardado exitosamente con ID: " . $id_pedido);
        
    } catch (Exception $e) {
        // Si hay error al guardar, aún permitimos continuar
        error_log("ERROR COMPLETO al guardar pedido proveedor: " . $e->getMessage());
        error_log("ERROR STACK TRACE: " . $e->getTraceAsString());
        error_log("ERROR DATOS - ID Proveedor: " . $data['id_proveedor']);
        error_log("ERROR DATOS - Mensaje longitud: " . strlen($mensaje));
        error_log("ERROR DATOS - Productos longitud: " . strlen($productos_solicitados));
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido generado correctamente',
        'whatsapp_url' => $whatsapp_url,
        'email_url' => $email_url
    ]);
}
function getPlantillas() {
    global $pdo;
    try {
        $stmt = $pdo->query('SELECT * FROM plantillas_mensaje ORDER BY nombre ASC');
        $plantillas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'plantillas' => $plantillas], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener plantillas', 'error' => $e->getMessage()]);
    }
    exit;
}

function crearPlantilla() {
    global $pdo;
    
    // Soportar tanto JSON como POST clásico
    $data = null;
    $raw_input = file_get_contents('php://input');
    if (!empty($raw_input)) {
        $data = json_decode($raw_input, true);
    }
    
    $nombre = $_POST['nombre'] ?? $data['nombre'] ?? null;
    $contenido = $_POST['contenido'] ?? $data['contenido'] ?? null;
    
    if (!$nombre || !$contenido) {
        echo json_encode(['success' => false, 'message' => 'Nombre y contenido son obligatorios']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO plantillas_mensaje (nombre, contenido, activa) VALUES (:nombre, :contenido, 1)");
        $result = $stmt->execute([
            'nombre' => $nombre,
            'contenido' => $contenido
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Plantilla creada exitosamente', 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al crear plantilla']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function editarPlantilla() {
    global $pdo;
    
    // Soportar tanto JSON como POST clásico
    $data = null;
    $raw_input = file_get_contents('php://input');
    if (!empty($raw_input)) {
        $data = json_decode($raw_input, true);
    }
    
    $id = $_POST['id_plantilla'] ?? $data['id_plantilla'] ?? null;
    $nombre = $_POST['nombre'] ?? $data['nombre'] ?? null;
    $contenido = $_POST['contenido'] ?? $data['contenido'] ?? null;
    
    if (!$id || !$nombre || !$contenido) {
        echo json_encode(['success' => false, 'message' => 'ID, nombre y contenido son obligatorios']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE plantillas_mensaje SET nombre = :nombre, contenido = :contenido WHERE id_plantilla = :id");
        $result = $stmt->execute([
            'nombre' => $nombre,
            'contenido' => $contenido,
            'id' => $id
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Plantilla actualizada exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar plantilla']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function eliminarPlantilla() {
    global $pdo;
    
    // Soportar tanto JSON como POST clásico
    $data = null;
    $raw_input = file_get_contents('php://input');
    if (!empty($raw_input)) {
        $data = json_decode($raw_input, true);
    }
    
    $id = $_POST['id_plantilla'] ?? $data['id_plantilla'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID de plantilla requerido']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM plantillas_mensaje WHERE id_plantilla = :id");
        $result = $stmt->execute(['id' => $id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Plantilla eliminada exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar plantilla']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

try {
    $raw_input = file_get_contents('php://input');
    $data = null;
    if (!empty($raw_input)) {
        $data = json_decode($raw_input, true);
    }
    $action = $_GET['action'] ?? $_POST['action'] ?? ($data['action'] ?? '');
    
    switch($action) {
        case 'get_proveedores':
            getProveedores();
            break;
            
        case 'get_proveedor':
            getProveedor();
            break;
            
        case 'crear_proveedor':
            crearProveedor();
            break;
            
        case 'actualizar_proveedor':
            actualizarProveedor();
            break;
            
        case 'eliminar_proveedor':
            eliminarProveedor();
            break;
            
        case 'get_productos_proveedor':
            getProductosProveedor();
            break;
            
        case 'asignar_productos':
            asignarProductos();
            break;
            
        case 'get_plantillas':
            getPlantillas();
            break;
            
        case 'crear_plantilla':
            crearPlantilla();
            break;
            
        case 'editar_plantilla':
            editarPlantilla();
            break;
            
        case 'eliminar_plantilla':
            eliminarPlantilla();
            break;
            
        case 'enviar_pedido':
            enviarPedido();
            break;
            
        case 'get_historial_pedidos':
            getHistorialPedidos();
            break;
            
        case 'get_estadisticas':
            getEstadisticas();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}

function getProveedores() {
    global $pdo;
    
    $estado = $_GET['estado'] ?? 'all';
    $busqueda = $_GET['busqueda'] ?? '';
    
    $sql = "SELECT p.*, 
            COUNT(DISTINCT pp.id_producto) as total_productos,
            MAX(ped.creado_en) as ultimo_pedido
            FROM proveedores p 
            LEFT JOIN producto_proveedor pp ON p.id_proveedor = pp.id_proveedor
            LEFT JOIN pedidos_proveedor ped ON p.id_proveedor = ped.id_proveedor
            WHERE 1=1";
    
    $params = [];
    
    if ($estado !== 'all') {
        $sql .= " AND p.estado = :estado";
        $params['estado'] = $estado;
    }
    
    if (!empty($busqueda)) {
        $sql .= " AND (p.nombre_empresa LIKE :busqueda OR p.persona_contacto LIKE :busqueda OR p.cuit LIKE :busqueda)";
        $params['busqueda'] = "%$busqueda%";
    }
    
    $sql .= " GROUP BY p.id_proveedor ORDER BY p.nombre_empresa";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'proveedores' => $proveedores]);
}

function getProveedor() {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id_proveedor = :id");
    $stmt->execute(['id' => $id]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proveedor) {
        echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
        return;
    }
    
    echo json_encode(['success' => true, 'proveedor' => $proveedor]);
}

// (Eliminada función duplicada crearProveedor)

function actualizarProveedor() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id_proveedor'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    // Validar CUIT si se proporciona
    if (!empty($data['cuit']) && !validarCuit($data['cuit'])) {
        echo json_encode(['success' => false, 'message' => 'CUIT inválido']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE proveedores SET 
                          nombre_empresa = :nombre_empresa,
                          persona_contacto = :persona_contacto,
                          cuit = :cuit,
                          telefono = :telefono,
                          email = :email,
                          direccion = :direccion,
                          notas = :notas,
                          estado = :estado
                          WHERE id_proveedor = :id");
    
    $result = $stmt->execute([
        'nombre_empresa' => $data['nombre_empresa'],
        'persona_contacto' => $data['persona_contacto'] ?? null,
        'cuit' => $data['cuit'] ?? null,
        'telefono' => $data['telefono'] ?? null,
        'email' => $data['email'] ?? null,
        'direccion' => $data['direccion'] ?? null,
        'notas' => $data['notas'] ?? null,
        'estado' => $data['estado'] ?? 1,
        'id' => $id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Proveedor actualizado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar proveedor']);
    }
}

function eliminarProveedor() {
    global $pdo;
    
    $id = $_POST['id'] ?? 0;
    $forzar = $_POST['forzar'] ?? false;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    // Verificar si tiene productos asignados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM producto_proveedor WHERE id_proveedor = :id");
    $stmt->execute(['id' => $id]);
    $count = $stmt->fetchColumn();
    
    // Si tiene productos y no se está forzando, devolver información para confirmación
    if ($count > 0 && !$forzar) {
        echo json_encode([
            'success' => false, 
            'requires_confirmation' => true,
            'message' => "Este proveedor tiene $count productos asignados",
            'count_productos' => $count
        ]);
        return;
    }
    
    try {
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Si tiene productos asignados, eliminar las relaciones primero
        if ($count > 0) {
            $stmt = $pdo->prepare("DELETE FROM producto_proveedor WHERE id_proveedor = :id");
            $stmt->execute(['id' => $id]);
        }
        
        // Eliminar el proveedor
        $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id_proveedor = :id");
        $result = $stmt->execute(['id' => $id]);
        
        if ($result) {
            $pdo->commit();
            $mensaje = $count > 0 ? 
                "Proveedor eliminado exitosamente junto con $count asignaciones de productos" : 
                "Proveedor eliminado exitosamente";
            echo json_encode(['success' => true, 'message' => $mensaje]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error al eliminar proveedor']);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function getProductosProveedor() {
    global $pdo;
    
    $id_proveedor = $_GET['id_proveedor'] ?? 0;
    
    if (!$id_proveedor) {
        echo json_encode(['success' => false, 'message' => 'ID de proveedor requerido']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT p.id_producto, p.nombre, p.precio_lista, c.nombre as categoria, pp.es_principal
                          FROM productos p
                          JOIN categorias c ON p.id_categoria = c.id_categoria
                          JOIN producto_proveedor pp ON p.id_producto = pp.id_producto
                          WHERE pp.id_proveedor = :id_proveedor AND p.eliminado = 0
                          ORDER BY c.nombre, p.nombre");
    
    $stmt->execute(['id_proveedor' => $id_proveedor]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'productos' => $productos]);
}

function asignarProductos() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id_proveedor = $data['id_proveedor'] ?? 0;
    $productos = $data['productos'] ?? [];
    
    if (!$id_proveedor) {
        echo json_encode(['success' => false, 'message' => 'ID de proveedor requerido']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Eliminar asignaciones existentes
        $stmt = $pdo->prepare("DELETE FROM producto_proveedor WHERE id_proveedor = :id_proveedor");
        $stmt->execute(['id_proveedor' => $id_proveedor]);
        
        // Insertar nuevas asignaciones
        foreach ($productos as $producto) {
            $stmt = $pdo->prepare("INSERT INTO producto_proveedor (id_producto, id_proveedor, es_principal) 
                                  VALUES (:id_producto, :id_proveedor, :es_principal)");
            $stmt->execute([
                'id_producto' => $producto['id_producto'],
                'id_proveedor' => $id_proveedor,
                'es_principal' => $producto['es_principal'] ?? 0
            ]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Productos asignados exitosamente']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al asignar productos: ' . $e->getMessage()]);
    }
}

function crearProveedor() {
    global $pdo;
    
    // Obtener datos JSON
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    if ($data === null || !is_array($data)) {
        echo json_encode(['success' => false, 'message' => 'Error: JSON inválido']);
        return;
    }

    // Validación básica
    if (empty($data['nombre_empresa'])) {
        echo json_encode(['success' => false, 'message' => 'Campo requerido: nombre_empresa']);
        return;
    }

    // Validar CUIT si se proporciona
    if (!empty($data['cuit']) && !validarCuit($data['cuit'])) {
        echo json_encode(['success' => false, 'message' => 'CUIT inválido']);
        return;
    }

    try {
        // Insertar en base de datos
        $stmt = $pdo->prepare("INSERT INTO proveedores (nombre_empresa, persona_contacto, cuit, telefono, email, direccion, notas, estado)
                              VALUES (:nombre_empresa, :persona_contacto, :cuit, :telefono, :email, :direccion, :notas, :estado)");
        
        $result = $stmt->execute([
            'nombre_empresa' => $data['nombre_empresa'],
            'persona_contacto' => $data['persona_contacto'] ?? null,
            'cuit' => $data['cuit'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'email' => $data['email'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'notas' => $data['notas'] ?? null,
            'estado' => $data['estado'] ?? 1
        ]);
        
        if ($result) {
            $id = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Proveedor creado exitosamente', 'id' => $id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al crear proveedor']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    }
}
    
// (Eliminado bloque duplicado de crearProveedor)

function getHistorialPedidos() {
    global $pdo;
    
    $id_proveedor = $_GET['id_proveedor'] ?? 0;
    $limit = intval($_GET['limit'] ?? 10);
    
    // Validar limit para evitar inyección SQL
    if ($limit < 1 || $limit > 100) {
        $limit = 10;
    }
    
    try {
        $sql = "SELECT pp.*, pr.nombre_empresa 
                FROM pedidos_proveedor pp
                JOIN proveedores pr ON pp.id_proveedor = pr.id_proveedor";
        
        if ($id_proveedor) {
            $sql .= " WHERE pp.id_proveedor = ?";
            $sql .= " ORDER BY pp.creado_en DESC LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_proveedor]);
        } else {
            $sql .= " ORDER BY pp.creado_en DESC LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'pedidos' => $pedidos]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener historial: ' . $e->getMessage()]);
    }
}

function getEstadisticas() {
    global $pdo;
    
    // Total de proveedores activos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_activos FROM proveedores WHERE estado = 1");
    $stmt->execute();
    $total_activos = $stmt->fetchColumn();
    
    // Total de proveedores inactivos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_inactivos FROM proveedores WHERE estado = 0");
    $stmt->execute();
    $total_inactivos = $stmt->fetchColumn();
    
    // Productos sin proveedor
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos p 
                          LEFT JOIN producto_proveedor pp ON p.id_producto = pp.id_producto 
                          WHERE pp.id_producto IS NULL AND p.eliminado = 0");
    $stmt->execute();
    $productos_sin_proveedor = $stmt->fetchColumn();
    
    // Pedidos del último mes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos_proveedor WHERE creado_en >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $stmt->execute();
    $pedidos_mes = $stmt->fetchColumn();
    
    // Top 5 proveedores por número de productos
    $stmt = $pdo->prepare("SELECT p.nombre_empresa, COUNT(pp.id_producto) as total_productos
                          FROM proveedores p
                          LEFT JOIN producto_proveedor pp ON p.id_proveedor = pp.id_proveedor
                          WHERE p.estado = 1
                          GROUP BY p.id_proveedor, p.nombre_empresa
                          ORDER BY total_productos DESC
                          LIMIT 5");
    $stmt->execute();
    $top_proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'estadisticas' => [
            'total_activos' => $total_activos,
            'total_inactivos' => $total_inactivos,
            'productos_sin_proveedor' => $productos_sin_proveedor,
            'pedidos_mes' => $pedidos_mes,
            'top_proveedores' => $top_proveedores
        ]
    ]);
}

function validarCuit($cuit) {
    // Remover guiones y espacios
    $cuit = preg_replace('/[^0-9]/', '', $cuit);
    
    // Verificar que tenga 11 dígitos
    if (strlen($cuit) !== 11) {
        return false;
    }
    
    // Algoritmo de validación CUIT
    $verificadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    
    for ($i = 0; $i < 10; $i++) {
        $suma += intval($cuit[$i]) * $verificadores[$i];
    }
    
    $resto = $suma % 11;
    $digitoVerificador = $resto < 2 ? $resto : 11 - $resto;
    
    return intval($cuit[10]) === $digitoVerificador;
}
?>
