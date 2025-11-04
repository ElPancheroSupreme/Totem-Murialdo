-- =====================================================
-- Script para separar categorías por punto de venta
-- Opción 1: Agregar id_punto_venta a categorias
-- Fecha: 2025-10-29
-- =====================================================

USE bg02;

-- =====================================================
-- PASO 1: AGREGAR COLUMNA id_punto_venta A CATEGORIAS
-- =====================================================

-- Agregar la nueva columna
ALTER TABLE categorias 
ADD COLUMN id_punto_venta TINYINT(4) DEFAULT NULL AFTER id_categoria,
ADD KEY idx_punto_venta (id_punto_venta);

-- Agregar la foreign key
ALTER TABLE categorias
ADD CONSTRAINT fk_categorias_punto_venta 
FOREIGN KEY (id_punto_venta) REFERENCES puntos_venta(id_punto_venta);

-- Verificar estructura
SELECT '✅ PASO 1 COMPLETADO: Columna id_punto_venta agregada' AS Status;
DESCRIBE categorias;

-- =====================================================
-- PASO 2: ASIGNAR CATEGORÍAS ACTUALES A KIOSCO (id=2)
-- =====================================================

-- Las categorías actuales se asignan a Kiosco por defecto
UPDATE categorias 
SET id_punto_venta = 2 
WHERE id_punto_venta IS NULL;

SELECT '✅ PASO 2 COMPLETADO: Categorías actuales asignadas a Kiosco' AS Status;

-- =====================================================
-- PASO 3: DUPLICAR CATEGORÍAS PARA BUFFET (id=1)
-- =====================================================

-- Desactivar temporalmente las foreign keys para hacer la inserción
SET FOREIGN_KEY_CHECKS = 0;

-- Insertar duplicados para Buffet (cambiando id_punto_venta a 1)
INSERT INTO categorias (nombre, descripcion, icono, color, visible, orden, id_punto_venta, creado_en, modificado_en, eliminado)
SELECT 
    nombre,
    descripcion,
    icono,
    color,
    visible,
    orden,
    1 AS id_punto_venta,  -- Buffet
    NOW() AS creado_en,
    NOW() AS modificado_en,
    eliminado
FROM categorias
WHERE id_punto_venta = 2;  -- Copiar desde las de Kiosco

-- Reactivar foreign keys
SET FOREIGN_KEY_CHECKS = 1;

SELECT '✅ PASO 3 COMPLETADO: Categorías duplicadas para Buffet' AS Status;

-- =====================================================
-- PASO 4: ACTUALIZAR PRODUCTOS PARA USAR CATEGORÍAS CORRECTAS
-- =====================================================

-- Crear tabla temporal con mapeo de categorías antiguas a nuevas
CREATE TEMPORARY TABLE temp_categoria_mapping (
    nombre_categoria VARCHAR(30),
    id_categoria_kiosco TINYINT(4),
    id_categoria_buffet TINYINT(4)
);

-- Llenar tabla de mapeo
INSERT INTO temp_categoria_mapping (nombre_categoria, id_categoria_kiosco, id_categoria_buffet)
SELECT 
    k.nombre,
    k.id_categoria AS id_categoria_kiosco,
    b.id_categoria AS id_categoria_buffet
FROM categorias k
INNER JOIN categorias b ON k.nombre = b.nombre AND k.id_punto_venta = 2 AND b.id_punto_venta = 1;

-- Actualizar productos de Buffet (id_punto_venta = 1)
UPDATE productos p
INNER JOIN categorias c_old ON p.id_categoria = c_old.id_categoria
INNER JOIN temp_categoria_mapping m ON c_old.nombre = m.nombre_categoria
SET p.id_categoria = m.id_categoria_buffet
WHERE p.id_punto_venta = 1;

-- Actualizar productos de Kiosco (id_punto_venta = 2)
-- Ya están apuntando a las categorías correctas, pero por si acaso:
UPDATE productos p
INNER JOIN categorias c_old ON p.id_categoria = c_old.id_categoria
INNER JOIN temp_categoria_mapping m ON c_old.nombre = m.nombre_categoria
SET p.id_categoria = m.id_categoria_kiosco
WHERE p.id_punto_venta = 2;

-- Limpiar tabla temporal
DROP TEMPORARY TABLE temp_categoria_mapping;

SELECT '✅ PASO 4 COMPLETADO: Productos actualizados con categorías correctas' AS Status;

-- =====================================================
-- PASO 5: VERIFICACIONES FINALES
-- =====================================================

-- Verificar categorías por punto de venta
SELECT 
    '📊 RESUMEN DE CATEGORÍAS' AS Seccion,
    pv.nombre AS Punto_Venta,
    COUNT(*) AS Total_Categorias,
    SUM(CASE WHEN c.visible = 1 THEN 1 ELSE 0 END) AS Visibles,
    SUM(CASE WHEN c.visible = 0 THEN 1 ELSE 0 END) AS Ocultas
FROM categorias c
INNER JOIN puntos_venta pv ON c.id_punto_venta = pv.id_punto_venta
GROUP BY pv.nombre, pv.id_punto_venta
ORDER BY pv.id_punto_venta;

-- Verificar productos por punto de venta y categoría
SELECT 
    '📦 RESUMEN DE PRODUCTOS' AS Seccion,
    pv.nombre AS Punto_Venta,
    c.nombre AS Categoria,
    COUNT(*) AS Total_Productos
FROM productos p
INNER JOIN categorias c ON p.id_categoria = c.id_categoria
INNER JOIN puntos_venta pv ON p.id_punto_venta = pv.id_punto_venta
WHERE p.eliminado = 0
GROUP BY pv.nombre, c.nombre
ORDER BY pv.nombre, c.nombre;

-- Listar todas las categorías creadas
SELECT 
    '📋 LISTADO COMPLETO DE CATEGORÍAS' AS Seccion,
    c.id_categoria,
    pv.nombre AS Punto_Venta,
    c.nombre AS Categoria,
    c.icono,
    c.visible,
    c.orden,
    (SELECT COUNT(*) FROM productos p WHERE p.id_categoria = c.id_categoria AND p.eliminado = 0) AS Productos
FROM categorias c
INNER JOIN puntos_venta pv ON c.id_punto_venta = pv.id_punto_venta
ORDER BY pv.nombre, c.orden, c.nombre;

-- Verificar integridad referencial
SELECT 
    '🔍 VERIFICACIÓN DE INTEGRIDAD' AS Seccion,
    CASE 
        WHEN COUNT(*) = 0 THEN '✅ Todos los productos tienen categorías válidas'
        ELSE CONCAT('⚠️ ', COUNT(*), ' productos sin categoría válida')
    END AS Estado
FROM productos p
LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
WHERE p.eliminado = 0 AND c.id_categoria IS NULL;

-- =====================================================
-- MENSAJE FINAL
-- =====================================================

SELECT 
    '🎉 MIGRACIÓN COMPLETADA EXITOSAMENTE' AS '════════════════════════════════',
    '' AS ' ',
    'Las categorías ahora están separadas por punto de venta:' AS Descripcion,
    '• Buffet tiene sus propias categorías' AS Buffet,
    '• Kiosco tiene sus propias categorías' AS Kiosco,
    '• Los productos están correctamente asignados' AS Productos,
    '' AS ' ',
    'Próximos pasos:' AS Siguiente,
    '1. Actualizar backend/admin/api/api_categorias.php' AS Paso_1,
    '2. Actualizar frontend ConfigDash.html' AS Paso_2,
    '3. Actualizar frontend/assets/js/dashboard/categorias.js' AS Paso_3,
    '4. Actualizar backend/api/api_kiosco.php' AS Paso_4,
    '5. Verificar en el tótem que funcione correctamente' AS Paso_5;

-- =====================================================
-- ROLLBACK (EN CASO DE ERROR - NO EJECUTAR SI TODO SALIÓ BIEN)
-- =====================================================

/*
-- Si algo salió mal, puedes revertir con estos comandos:

-- Eliminar categorías de Buffet (las nuevas)
DELETE FROM categorias WHERE id_punto_venta = 1;

-- Restaurar categorías de Kiosco al estado original (sin punto de venta)
UPDATE categorias SET id_punto_venta = NULL WHERE id_punto_venta = 2;

-- Eliminar la columna y foreign key
ALTER TABLE categorias DROP FOREIGN KEY fk_categorias_punto_venta;
ALTER TABLE categorias DROP COLUMN id_punto_venta;
*/
