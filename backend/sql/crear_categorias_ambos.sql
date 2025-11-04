-- ============================================
-- Script para crear Categorías "Ambos"
-- Fecha: 2025-10-30
-- Descripción: Crea categorías con id_punto_venta=3 para productos compartidos
-- ============================================

USE `bg02`;

-- ============================================
-- CREAR CATEGORÍAS PARA "AMBOS"
-- ============================================

-- Copiar categorías base para el punto de venta "Ambos"
-- Usar las categorías del Buffet como base (id_punto_venta = 1)
INSERT INTO `categorias` (`id_punto_venta`, `nombre`, `descripcion`, `icono`, `color`, `visible`, `orden`)
SELECT 
    3 as id_punto_venta,
    nombre,
    descripcion,
    icono,
    color,
    visible,
    orden
FROM `categorias`
WHERE `id_punto_venta` = 1  -- Usar las categorías del Buffet como base
AND `eliminado` = 0;

-- ============================================
-- VERIFICACIÓN
-- ============================================

-- Verificar cuántas categorías hay por punto de venta
SELECT 
    id_punto_venta,
    CASE 
        WHEN id_punto_venta = 1 THEN 'Buffet'
        WHEN id_punto_venta = 2 THEN 'Kiosco'
        WHEN id_punto_venta = 3 THEN 'Ambos'
        ELSE 'Desconocido'
    END as punto_venta,
    COUNT(*) as total_categorias,
    GROUP_CONCAT(nombre ORDER BY orden SEPARATOR ', ') as categorias
FROM categorias
WHERE eliminado = 0
GROUP BY id_punto_venta
ORDER BY id_punto_venta;

-- Ver todas las categorías "Ambos" creadas
SELECT 
    id_categoria,
    nombre,
    icono,
    color,
    orden,
    visible
FROM categorias
WHERE id_punto_venta = 3
AND eliminado = 0
ORDER BY orden ASC, nombre ASC;

-- ============================================
-- ROLLBACK (Si necesitas revertir)
-- ============================================
/*
-- Para eliminar las categorías "Ambos" creadas:
DELETE FROM categorias WHERE id_punto_venta = 3;
*/

-- ============================================
-- FIN DEL SCRIPT
-- ============================================

SELECT '✅ Categorías "Ambos" creadas exitosamente' as resultado;
SELECT 'ℹ️ Ahora los productos "Ambos" deben usar categorías con id_punto_venta=3' as nota;
