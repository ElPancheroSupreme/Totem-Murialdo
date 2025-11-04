-- =====================================================
-- Script para modificar columna icono en categorias
-- De emoji a ruta de archivo SVG
-- Fecha: 2025-10-29
-- =====================================================

USE bg02;

-- Modificar columna icono para permitir rutas más largas
ALTER TABLE categorias 
MODIFY COLUMN icono VARCHAR(255) DEFAULT NULL 
COMMENT 'Ruta del archivo SVG del icono de la categoría (ej: Icono_Bebidas.svg)';

-- Actualizar los registros existentes con las rutas de los archivos SVG
-- Basado en los nombres de categorías existentes
UPDATE categorias SET icono = 'Icono_Bebidas.svg' WHERE nombre = 'Bebidas';
UPDATE categorias SET icono = 'Icono_Snacks.svg' WHERE nombre = 'Snacks';
UPDATE categorias SET icono = 'Icono_Comidas.svg' WHERE nombre = 'Comidas';
UPDATE categorias SET icono = 'Icono_Alfajores.svg' WHERE nombre = 'Alfajores';
UPDATE categorias SET icono = 'Icono_Galletitas.svg' WHERE nombre = 'Galletitas';
UPDATE categorias SET icono = 'Icono_Helados.svg' WHERE nombre = 'Helados';
UPDATE categorias SET icono = 'Icono_Golosinas.svg' WHERE nombre = 'Golosinas';
UPDATE categorias SET icono = 'Icono_Cafeteria.svg' WHERE nombre = 'Cafeteria';
UPDATE categorias SET icono = 'Icono_Especial.svg' WHERE nombre = 'Especial';

-- Verificar los cambios
SELECT id_categoria, nombre, icono, visible 
FROM categorias 
ORDER BY orden ASC;

-- Mensaje de confirmación
SELECT '✅ Columna icono actualizada correctamente a rutas SVG' AS Resultado;
