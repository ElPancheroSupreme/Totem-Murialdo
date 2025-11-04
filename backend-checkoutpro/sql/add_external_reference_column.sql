-- add_external_reference_column.sql
-- Agregar columna external_reference a la tabla pedidos para CheckoutPro

-- Verificar si la columna ya existe (opcional, para evitar errores)
-- Si ya existe, este script no hará nada

ALTER TABLE pedidos 
ADD COLUMN IF NOT EXISTS external_reference VARCHAR(50) NULL AFTER numero_pedido;

-- Crear índice para búsquedas rápidas por external_reference
CREATE INDEX IF NOT EXISTS idx_external_reference ON pedidos(external_reference);

-- Verificar que se creó correctamente
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE, 
    COLUMN_KEY
FROM 
    INFORMATION_SCHEMA.COLUMNS 
WHERE 
    TABLE_SCHEMA = 'bg02' 
    AND TABLE_NAME = 'pedidos' 
    AND COLUMN_NAME = 'external_reference';

-- Mostrar mensaje de éxito
SELECT '✅ Columna external_reference agregada correctamente' AS Resultado;
