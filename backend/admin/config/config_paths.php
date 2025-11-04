<?php
// Configuración de rutas del proyecto

// Detectar la ruta base del proyecto automáticamente
function getProjectRoot() {
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    
    // Obtener la ruta del proyecto relativa al document root
    $projectPath = dirname(dirname(dirname($scriptPath))); // Subir tres niveles desde backend/admin/config/
    
    return $projectPath;
}

// Definir la ruta base del proyecto
define('PROJECT_ROOT', getProjectRoot());

// Función para construir URLs de imágenes
function buildImageUrl($relativePath) {
    // Las imágenes están en frontend/assets/images/, no en backend
    return '/Totem_Murialdo/frontend/assets/images/' . ltrim($relativePath, '/');
}
?>
