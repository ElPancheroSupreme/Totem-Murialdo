-- Script para intercambiar precios de venta y lista en la tabla productos
-- Creado: 24/09/2025
-- Propósito: Intercambiar los valores entre precio_venta y precio_lista

-- Verificar datos antes del cambio (opcional - comentar si no se necesita)
SELECT 
    'ANTES DEL CAMBIO' as estado,
    id_producto,
    nombre,
    precio_venta,
    precio_lista
FROM productos 
WHERE eliminado = 0
ORDER BY id_producto
LIMIT 10;

-- Intercambiar los precios usando una variable temporal
UPDATE productos 
SET 
    precio_venta = (@temp := precio_venta),
    precio_venta = precio_lista,
    precio_lista = @temp,
    actualizado_en = NOW()
WHERE eliminado = 0;

-- Verificar datos después del cambio (opcional - comentar si no se necesita)
SELECT 
    'DESPUÉS DEL CAMBIO' as estado,
    id_producto,
    nombre,
    precio_venta,
    precio_lista
FROM productos 
WHERE eliminado = 0
ORDER BY id_producto
LIMIT 10;

-- Mostrar resumen de productos actualizados
SELECT 
    COUNT(*) as productos_actualizados,
    'Intercambio completado exitosamente' as mensaje
FROM productos 
WHERE eliminado = 0;