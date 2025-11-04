-- ============================================
-- Script para agregar Punto de Venta "Ambos"
-- Fecha: 2025-10-30
-- Descripción: Agrega un tercer punto de venta (id=3) llamado "Ambos"
--              para productos que deben aparecer tanto en Buffet como en Kiosco
-- ============================================

USE `bg02`;

-- ============================================
-- 1. AGREGAR PUNTO DE VENTA "AMBOS"
-- ============================================

-- Insertar el nuevo punto de venta con id=3
INSERT INTO `puntos_venta` (`id_punto_venta`, `nombre`) 
VALUES (3, 'Ambos')
ON DUPLICATE KEY UPDATE nombre = 'Ambos';

-- Verificar que se insertó correctamente
SELECT * FROM `puntos_venta`;

-- ============================================
-- 2. CREAR CATEGORÍAS PARA "AMBOS" (OPCIONAL)
-- ============================================
-- Nota: Las categorías para "Ambos" son opcionales.
-- Los productos con id_punto_venta=3 pueden usar las categorías existentes
-- de Buffet (id_punto_venta=1) si lo prefieres.
-- 
-- Si decides crear categorías específicas para "Ambos", descomenta lo siguiente:

/*
-- Copiar categorías base para el punto de venta "Ambos"
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
AND `eliminado` = 0
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion);
*/

-- ============================================
-- 3. VERIFICACIONES
-- ============================================

-- Verificar puntos de venta
SELECT 
    id_punto_venta,
    nombre,
    'Punto de Venta' as tipo
FROM puntos_venta
ORDER BY id_punto_venta;

-- Verificar cuántas categorías hay por punto de venta
SELECT 
    id_punto_venta,
    CASE 
        WHEN id_punto_venta = 1 THEN 'Buffet'
        WHEN id_punto_venta = 2 THEN 'Kiosco'
        WHEN id_punto_venta = 3 THEN 'Ambos'
        ELSE 'Desconocido'
    END as punto_venta,
    COUNT(*) as total_categorias
FROM categorias
WHERE eliminado = 0
GROUP BY id_punto_venta
ORDER BY id_punto_venta;

-- Verificar productos por punto de venta (antes del cambio)
SELECT 
    id_punto_venta,
    CASE 
        WHEN id_punto_venta = 1 THEN 'Buffet'
        WHEN id_punto_venta = 2 THEN 'Kiosco'
        WHEN id_punto_venta = 3 THEN 'Ambos'
        ELSE 'Desconocido'
    END as punto_venta,
    COUNT(*) as total_productos
FROM productos
WHERE eliminado = 0
GROUP BY id_punto_venta
ORDER BY id_punto_venta;

-- ============================================
-- 4. MIGRACION DE PRODUCTOS (EJEMPLO)
-- ============================================
-- Si quieres migrar algunos productos específicos a "Ambos", usa este formato:
-- IMPORTANTE: Esto es solo un EJEMPLO. NO se ejecutará automáticamente.

/*
-- Ejemplo: Migrar Coca Cola a "Ambos" (aparecerá en Buffet y Kiosco)
UPDATE productos 
SET id_punto_venta = 3 
WHERE id_producto IN (4, 13)  -- IDs de Coca Cola en ambos puntos de venta
AND eliminado = 0;

-- Ejemplo: Migrar todas las bebidas comunes a "Ambos"
UPDATE productos p
INNER JOIN categorias c ON p.id_categoria = c.id_categoria
SET p.id_punto_venta = 3
WHERE c.nombre = 'Bebidas'
AND p.nombre LIKE '%Coca Cola%'
AND p.eliminado = 0;
*/

-- ============================================
-- 5. INDICES Y CONSTRAINTS (OPCIONAL)
-- ============================================
-- Verificar que los índices existentes siguen siendo efectivos

-- Verificar constraint de punto de venta en productos
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'bg02'
AND TABLE_NAME IN ('productos', 'categorias')
AND REFERENCED_TABLE_NAME = 'puntos_venta';

-- ============================================
-- 6. NOTAS IMPORTANTES
-- ============================================
/*
COMPORTAMIENTO ESPERADO:

1. PRODUCTOS CON id_punto_venta = 3 ("Ambos"):
   - Aparecerán en el totem del Buffet (id=1)
   - Aparecerán en el totem del Kiosco (id=2)
   - En el dashboard se mostrarán con etiqueta "Ambos"

2. CATEGORÍAS:
   - Puedes usar las categorías existentes de Buffet o Kiosco
   - O crear categorías específicas para "Ambos" (descomentando la sección 2)
   - Los productos "Ambos" pueden pertenecer a cualquier categoría

3. QUERIES EN EL FRONTEND:
   - Los totems deben modificarse para traer productos de su punto de venta + "Ambos"
   - Buffet: WHERE id_punto_venta IN (1, 3)
   - Kiosco: WHERE id_punto_venta IN (2, 3)

4. ESTADÍSTICAS:
   - Los pedidos mantienen su id_punto_venta original (1 o 2)
   - Un producto "Ambos" vendido en el Buffet se cuenta en estadísticas del Buffet
   - Un producto "Ambos" vendido en el Kiosco se cuenta en estadísticas del Kiosco

5. DASHBOARD:
   - Agregar opción "Ambos" en el select de punto de venta
   - Al filtrar por "Ambos", mostrar solo productos con id_punto_venta=3
*/

-- ============================================
-- 7. ROLLBACK (En caso de necesitar revertir)
-- ============================================
/*
-- Para revertir los cambios:

-- Eliminar punto de venta "Ambos"
DELETE FROM puntos_venta WHERE id_punto_venta = 3;

-- Si creaste categorías para "Ambos", eliminarlas:
DELETE FROM categorias WHERE id_punto_venta = 3;

-- Revertir productos que migraste (ejemplo, ajusta según lo que hayas hecho):
-- UPDATE productos SET id_punto_venta = 1 WHERE id_punto_venta = 3 AND condición...;
*/

-- ============================================
-- FIN DEL SCRIPT
-- ============================================

SELECT '✅ Script ejecutado correctamente. Punto de venta "Ambos" agregado.' as resultado;
SELECT '⚠️ Recuerda actualizar el código backend y frontend para soportar id_punto_venta=3' as aviso;
