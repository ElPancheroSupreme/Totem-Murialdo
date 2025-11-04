# Implementación del Punto de Venta "Ambos"

## 📅 Fecha de Implementación
**30 de Octubre de 2025**

---

## 🎯 Objetivo

Agregar un tercer punto de venta llamado **"Ambos" (id=3)** que permita que un producto aparezca simultáneamente en el Totem del Buffet y en el Totem del Kiosco sin necesidad de duplicar el registro en la base de datos.

---

## ✅ Cambios Realizados

### 1. **Base de Datos** ✅

**Archivo:** `backend/sql/agregar_punto_venta_ambos.sql`

- ✅ Agregado `INSERT INTO puntos_venta` con `id_punto_venta = 3`, `nombre = 'Ambos'`
- ✅ Queries de verificación incluidos
- ✅ Ejemplos de migración de productos (comentados)
- ✅ Script de rollback incluido

**Ejecución:**
```sql
USE `bg02`;
INSERT INTO `puntos_venta` (`id_punto_venta`, `nombre`) 
VALUES (3, 'Ambos')
ON DUPLICATE KEY UPDATE nombre = 'Ambos';
```

---

### 2. **Backend - API de Totem** ✅

**Archivo:** `backend/api/api_kiosco.php`

**Cambio realizado:**
```php
// ANTES:
WHERE p.id_punto_venta = ?

// DESPUÉS:
WHERE p.id_punto_venta IN (?, 3)
```

**Explicación:**
- Cuando el Buffet (id=1) solicita productos: `WHERE id_punto_venta IN (1, 3)`
- Cuando el Kiosco (id=2) solicita productos: `WHERE id_punto_venta IN (2, 3)`
- Los productos con `id_punto_venta=3` aparecen en ambos totems automáticamente

---

### 3. **Frontend - Dashboard (JavaScript)** ✅

**Archivo:** `backend/admin/js/productos.js`

**Cambios realizados:**

#### A) Vista de Lista (Tabla)
- ✅ Agregado badge visual con color morado (#9c27b0) para "Ambos"
- ✅ Badge verde para Buffet
- ✅ Badge azul para Kiosco

```javascript
// Determinar color del badge según punto de venta
if (puntoVentaNombre === 'Buffet') {
    puntoVentaBadge = '<span style="background-color: #10b98122; color: #10b981; ...">Buffet</span>';
} else if (puntoVentaNombre === 'Kiosco') {
    puntoVentaBadge = '<span style="background-color: #3b82f622; color: #3b82f6; ...">Kiosco</span>';
} else if (puntoVentaNombre === 'Ambos') {
    puntoVentaBadge = '<span style="background-color: #9c27b022; color: #9c27b0; ...">Ambos</span>';
}
```

#### B) Vista de Grilla (Cards)
- ✅ Misma lógica de badges aplicada a la vista de cards
- ✅ Badge más pequeño y compacto para diseño responsive

---

### 4. **Frontend - Formularios (HTML)** ✅

**Archivo:** `frontend/views/ConfigDash.html`

**Nota:** No se requieren cambios manuales en el HTML, ya que el select de punto de venta se llena dinámicamente desde la API:

```javascript
// En productos.js - línea 451-461
actualizarSelectPuntosVenta() {
    const select = document.getElementById('punto-venta-select');
    if (select) {
        select.innerHTML = '<option value="">Seleccionar punto de venta</option>';
        this.puntosVenta.forEach(punto => {
            const option = document.createElement('option');
            option.value = punto.id_punto_venta;
            option.textContent = punto.nombre;
            select.appendChild(option);
        });
    }
}
```

**Resultado:** El select ahora tiene 3 opciones automáticamente:
1. Buffet
2. Kiosco
3. Ambos ⭐ NUEVO

---

### 5. **Documentación** ✅

**Archivo:** `SISTEMA_CATEGORIAS_SEPARADAS.md`

Actualizaciones realizadas:
- ✅ Sección de descripción general actualizada
- ✅ Tabla de puntos de venta con "Ambos"
- ✅ Diagramas de flujo actualizados
- ✅ Ejemplos de código con opción "Ambos"
- ✅ Guía completa de uso del punto de venta "Ambos"
- ✅ Ejemplos de migración de productos duplicados
- ✅ Mejores prácticas y recomendaciones

---

## 🎨 Colores de Badges

| Punto de Venta | Color de Fondo | Color de Texto | Código Hex |
|----------------|----------------|----------------|------------|
| **Buffet** | Verde claro | Verde oscuro | `#10b981` |
| **Kiosco** | Azul claro | Azul oscuro | `#3b82f6` |
| **Ambos** ⭐ | Morado claro | Morado oscuro | `#9c27b0` |

---

## 🔧 Cómo Funciona

### **Escenario 1: Crear Producto "Ambos"**

1. Admin va a Dashboard → Productos → Crear Producto
2. Llena el formulario:
   - Nombre: "Coca Cola 500ml"
   - Precio: $2200
   - Categoría: "Bebidas" (de Buffet o Kiosco)
   - **Punto de Venta:** Selecciona "Ambos" ⭐
3. Guarda el producto
4. **Resultado:**
   - Se guarda UNA SOLA VEZ en la base de datos
   - Aparece en el Totem del Buffet
   - Aparece en el Totem del Kiosco
   - En el dashboard se ve con badge morado "Ambos"

### **Escenario 2: Query desde el Totem del Buffet**

```http
GET /backend/api/api_kiosco.php?punto_venta=1
```

Query ejecutado:
```sql
SELECT p.*, c.nombre as categoria_nombre 
FROM productos p 
LEFT JOIN categorias c ON p.id_categoria = c.id_categoria 
WHERE p.estado=1 
AND c.visible = 1 
AND p.id_punto_venta IN (1, 3)  -- Buffet + Ambos
ORDER BY c.orden ASC, c.nombre ASC, p.nombre ASC
```

**Resultado:** Trae productos de Buffet + productos Ambos

### **Escenario 3: Query desde el Totem del Kiosco**

```http
GET /backend/api/api_kiosco.php?punto_venta=2
```

Query ejecutado:
```sql
WHERE p.id_punto_venta IN (2, 3)  -- Kiosco + Ambos
```

**Resultado:** Trae productos de Kiosco + productos Ambos

### **Escenario 4: Estadísticas**

Un usuario compra una "Coca Cola 500ml" (producto "Ambos") en el **Kiosco**:

1. El pedido se guarda con `id_punto_venta = 2` (Kiosco)
2. Las estadísticas del Kiosco incluyen esta venta
3. El producto sigue siendo "Ambos", pero la venta se atribuye al Kiosco
4. Esto permite:
   - Saber qué productos se venden más en cada lugar
   - Mantener control de inventario por punto de venta
   - Tener un solo registro del producto en la base de datos

---

## 📊 Casos de Uso Recomendados

### ✅ **Usar "Ambos" para:**

- **Bebidas envasadas:** Coca-Cola, Sprite, Fanta, Agua, Jugos, etc.
- **Snacks empaquetados:** Papas fritas, Doritos, Palitos, etc.
- **Golosinas:** Chocolates, caramelos, chicles
- **Productos de marca:** Cualquier producto con mismo precio y presentación

### ❌ **NO usar "Ambos" para:**

- **Comidas preparadas:** Sandwiches, hamburguesas, platos del día
- **Productos con precio diferente:** Si el precio varía según el punto de venta
- **Menús exclusivos:** Combos o promociones específicas de un lugar
- **Productos personalizables diferentes:** Si las opciones de personalización varían

---

## 🔄 Migración de Productos Duplicados

Si ya tienes productos duplicados (ejemplo: Coca-Cola en Buffet y Coca-Cola en Kiosco):

### **Opción A: Migración Manual desde SQL**

```sql
-- 1. Identificar duplicados
SELECT p1.nombre, p1.id_producto as id_buffet, p2.id_producto as id_kiosco
FROM productos p1
JOIN productos p2 ON p1.nombre = p2.nombre
WHERE p1.id_punto_venta = 1 
AND p2.id_punto_venta = 2
AND p1.precio_venta = p2.precio_venta;

-- 2. Cambiar uno a "Ambos"
UPDATE productos 
SET id_punto_venta = 3 
WHERE id_producto = 4;  -- ID del producto a mantener

-- 3. Eliminar el duplicado
DELETE FROM productos 
WHERE id_producto = 13;  -- ID del producto duplicado
```

### **Opción B: Migración desde Dashboard**

1. Ve a Dashboard → Productos
2. Edita el producto que quieres mantener
3. Cambia "Punto de Venta" a "Ambos"
4. Guarda
5. Elimina el producto duplicado

---

## 🧪 Testing Recomendado

### **1. Crear Producto "Ambos"**
- [ ] Crear producto nuevo con punto de venta "Ambos"
- [ ] Verificar que aparece en ambos totems
- [ ] Verificar badge morado en el dashboard

### **2. Visualización en Totems**
- [ ] Abrir totem del Buffet → Ver productos Buffet + Ambos
- [ ] Abrir totem del Kiosco → Ver productos Kiosco + Ambos
- [ ] Verificar que NO se duplican los productos "Ambos"

### **3. Compras y Estadísticas**
- [ ] Comprar producto "Ambos" desde el Buffet
- [ ] Verificar que el pedido tiene `id_punto_venta=1`
- [ ] Verificar que aparece en estadísticas del Buffet
- [ ] Comprar mismo producto desde el Kiosco
- [ ] Verificar que el pedido tiene `id_punto_venta=2`
- [ ] Verificar que aparece en estadísticas del Kiosco

### **4. Filtros en Dashboard**
- [ ] Filtrar por "Buffet" → NO debe mostrar productos "Ambos"
- [ ] Filtrar por "Kiosco" → NO debe mostrar productos "Ambos"
- [ ] Filtrar por "Ambos" → Solo productos con id=3
- [ ] Filtrar por "Todos" → Debe mostrar Buffet + Kiosco + Ambos

### **5. Edición de Productos**
- [ ] Editar producto "Ambos" → Cambiar precio → Verificar en ambos totems
- [ ] Cambiar producto de "Buffet" a "Ambos" → Verificar que aparece en Kiosco
- [ ] Cambiar producto de "Ambos" a "Kiosco" → Verificar que desaparece de Buffet

---

## 🚨 Problemas Conocidos y Soluciones

### **Problema 1: No aparece opción "Ambos" en el select**
**Causa:** No se ejecutó el script SQL o hubo error en la inserción.

**Solución:**
```sql
SELECT * FROM puntos_venta;  -- Verificar que existe id=3
-- Si no existe:
INSERT INTO puntos_venta (id_punto_venta, nombre) VALUES (3, 'Ambos');
```

### **Problema 2: Productos "Ambos" no aparecen en los totems**
**Causa:** Cache del navegador o error en la API.

**Solución:**
1. Limpiar cache del navegador (Ctrl + Shift + Delete)
2. Verificar en la consola del navegador (F12) que la API responde correctamente
3. Verificar logs del servidor PHP

### **Problema 3: Badge no se ve con color morado**
**Causa:** JavaScript no actualizado o cache.

**Solución:**
1. Hacer hard reload (Ctrl + F5)
2. Verificar que el archivo `productos.js` tiene los cambios

---

## 📈 Beneficios de la Implementación

✅ **Evita duplicación de datos:** Un producto se guarda una sola vez
✅ **Simplifica gestión:** Actualizar precio/stock en un solo lugar
✅ **Mejora consistencia:** Mismo producto = misma información
✅ **Facilita inventario:** Saber qué productos son comunes
✅ **Optimiza base de datos:** Menos registros = mejor performance
✅ **Identificación visual clara:** Badge morado distingue productos compartidos

---

## 🔮 Futuras Mejoras (Opcional)

1. **Filtro automático de duplicados:** Script que detecta y sugiere productos para migrar a "Ambos"
2. **Reporte de productos comunes:** Dashboard que muestra productos que podrían ser "Ambos"
3. **Gestión de stock separada:** Aunque el producto es "Ambos", llevar stock independiente por punto de venta
4. **Precios diferenciados:** Permitir que un producto "Ambos" tenga precio A en Buffet y precio B en Kiosco

---

## ✅ Checklist de Implementación

- [x] Ejecutar script SQL `agregar_punto_venta_ambos.sql`
- [x] Actualizar `backend/api/api_kiosco.php`
- [x] Actualizar `backend/admin/js/productos.js`
- [x] Actualizar documentación `SISTEMA_CATEGORIAS_SEPARADAS.md`
- [x] Crear documento de implementación
- [ ] Ejecutar tests de verificación
- [ ] Capacitar al equipo sobre el nuevo punto de venta
- [ ] Migrar productos duplicados existentes (si aplica)
- [ ] Monitorear logs por 1 semana post-implementación

---

## 📞 Soporte

Si encuentras algún problema con esta implementación:

1. Revisar logs en: `backend/admin/api/debug_productos.log`
2. Verificar consola del navegador (F12)
3. Revisar este documento de implementación
4. Consultar `SISTEMA_CATEGORIAS_SEPARADAS.md`

---

**Implementado por:** Sistema de Totem Murialdo  
**Fecha:** 30 de Octubre de 2025  
**Versión:** 1.0
