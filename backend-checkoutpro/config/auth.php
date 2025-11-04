<?php
/**
 * Funciones de autenticación y control de acceso
 */

/**
 * Verifica si hay una sesión activa
 * @throws Exception si no hay sesión
 */
function checkLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['empleado'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
        exit;
    }
}

/**
 * Verifica si el usuario tiene uno de los roles permitidos
 * @param array $rolesPermitidos Array de IDs de roles permitidos
 * @throws Exception si el rol no está permitido
 */
function checkRol($rolesPermitidos) {
    checkLogin();
    
    if (!in_array($_SESSION['empleado']['id_rol'], $rolesPermitidos)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
        exit;
    }
}

/**
 * Verifica si el usuario es administrador
 * @return bool true si es admin, false si no
 */
function isAdmin() {
    return isset($_SESSION['empleado']) && $_SESSION['empleado']['id_rol'] === 1;
}

/**
 * Verifica si el usuario es supervisor
 * @return bool true si es supervisor, false si no
 */
function isSupervisor() {
    return isset($_SESSION['empleado']) && $_SESSION['empleado']['id_rol'] === 2;
}

/**
 * Verifica si el usuario es empleado regular
 * @return bool true si es empleado, false si no
 */
function isEmpleado() {
    return isset($_SESSION['empleado']) && $_SESSION['empleado']['id_rol'] === 3;
}
