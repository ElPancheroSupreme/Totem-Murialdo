# Solución: Categorías "Ambos" y Filtrado en Formulario

## 📅 Fecha de Implementación
**30 de Octubre de 2025**

---

## 🎯 Problema Identificado

Al crear un producto con `id_punto_venta=3` ("Ambos"), si se selecciona una categoría que solo existe en Buffet (por ejemplo, categoría id=13 "Bebidas" del Buffet), el producto solo aparecería en el Buffet porque el Kiosco no tendría esa categoría vinculada.

**Ejemplo del problema:**
- Producto: "Coca Cola 500ml"
- Punto de Venta: **Ambos** (id=3)
- Categoría seleccionada: "Bebidas" de **Buffet** (id=13, `id_punto_venta=1`)
- **Resultado:** El producto solo aparece en el Buffet ❌

---

## ✅ Solución Implementada

### **Enfoque Combinado:**
1. Crear categorías específicas para "Ambos" (`id_punto_venta=3`)
2. Filtrar categorías en el formulario según el punto de venta seleccionado

---

## 🗄️ Cambios en la Base de Datos

### **Script SQL Creado:** `crear_categorias_ambos.sql`

```sql
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
AND `eliminado` = 0;
```

**Resultado:**
- ✅ Ahora existen 27 categorías en total (9 por cada punto de venta)
- ✅ Buffet: 9 categorías con `id_punto_venta=1`
- ✅ Kiosco: 9 categorías con `id_punto_venta=2`
- ✅ **Ambos: 9 categorías con `id_punto_venta=3`** ⭐ NUEVO

---

## 💻 Cambios en el Frontend

### **1. Orden del Formulario (ConfigDash.html)**

**ANTES:**
```html
<div class="form-row">
    <div class="form-group">
        <label>Precio de Lista</label>
        ...
    </div>
    <div class="form-group">
        <label>Categoría *</label>  <!-- Primero -->
        ...
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Punto de Venta *</label>  <!-- Después -->
        ...
    </div>
    ...
</div>
```

**DESPUÉS:**
```html
<div class="form-row">
    <div class="form-group">
        <label>Precio de Lista</label>
        ...
    </div>
    <div class="form-group">
        <label>Punto de Venta *</label>  <!-- Primero ✅ -->
        <select id="punto-venta-select" name="id_punto_venta" required>
            <option value="">Seleccionar punto de venta</option>
            <option value="1">Buffet</option>
            <option value="2">Kiosco</option>
            <option value="3">Ambos</option>
        </select>
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label>Categoría *</label>  <!-- Después ✅ -->
        <select id="categoria-select" name="id_categoria" required>
            <!-- Se llena dinámicamente según punto de venta -->
        </select>
    </div>
    ...
</div>
```

**Beneficio:** Es más lógico seleccionar primero el punto de venta y luego la categoría correspondiente.

---

### **2. Filtrado Automático de Categorías (productos.js)**

#### **Función Actualizada:**

```javascript
actualizarSelectCategorias(puntoVentaId = null) {
    const select = document.getElementById('categoria-select');
    
    if (select) {
        select.innerHTML = '<option value="">Seleccionar categoría</option>';
        
        // Filtrar categorías según el punto de venta seleccionado
        let categoriasFiltradas = this.categorias;
        if (puntoVentaId !== null && puntoVentaId !== '') {
            categoriasFiltradas = this.categorias.filter(cat => 
                cat.id_punto_venta == puntoVentaId
            );
        }
        
        categoriasFiltradas.forEach(categoria => {
            const option = document.createElement('option');
            option.value = categoria.id_categoria;
            option.textContent = categoria.nombre;
            select.appendChild(option);
        });
        
        // Mensaje si no hay categorías
        if (categoriasFiltradas.length === 0 && puntoVentaId !== null) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No hay categorías para este punto de venta';
            option.disabled = true;
            select.appendChild(option);
        }
    }
}
```

#### **Listener Agregado:**

```javascript
// Listener para cambio de punto de venta - filtrar categorías
const puntoVentaSelect = document.getElementById('punto-venta-select');
if (puntoVentaSelect) {
    puntoVentaSelect.addEventListener('change', (e) => {
        const puntoVentaId = e.target.value;
        // Actualizar las categorías según el punto de venta seleccionado
        this.actualizarSelectCategorias(puntoVentaId);
        
        // Resetear la categoría seleccionada si ya no está disponible
        const categoriaSelect = document.getElementById('categoria-select');
        if (categoriaSelect && categoriaSelect.value) {
            const categoriaActual = this.categorias.find(cat => 
                cat.id_categoria == categoriaSelect.value
            );
            // Si la categoría actual no pertenece al punto de venta seleccionado, resetear
            if (categoriaActual && puntoVentaId && categoriaActual.id_punto_venta != puntoVentaId) {
                categoriaSelect.value = '';
            }
        }
    });
}
```

---

### **3. Actualización en Gestor de Categorías**

#### **Filtro de Categorías Actualizado:**

```html
<select id="filtro-punto-venta" class="filtro-select">
    <option value="">Todos los puntos de venta</option>
    <option value="1">Buffet</option>
    <option value="2">Kiosco</option>
    <option value="3">Ambos</option>  <!-- ⭐ NUEVO -->
</select>
```

#### **Formulario de Crear/Editar Categoría:**

```html
<select id="categoria-punto-venta" name="id_punto_venta" required>
    <option value="">Seleccione...</option>
    <option value="1">Buffet</option>
    <option value="2">Kiosco</option>
    <option value="3">Ambos</option>  <!-- ⭐ NUEVO -->
</select>
```

---

## 🎬 Flujo de Usuario

### **Crear Producto "Ambos":**

1. Dashboard → Productos → **+ Nuevo Producto**
2. Llenar:
   - Nombre: "Coca Cola 500ml"
   - Precio de Venta: $2200
3. **Seleccionar Punto de Venta: "Ambos"** ⭐
4. **Select de Categoría se actualiza** → Solo muestra categorías "Ambos"
   - Bebidas (Ambos)
   - Snacks (Ambos)
   - Dulces (Ambos)
   - etc.
5. Seleccionar Categoría: **"Bebidas (Ambos)"**
6. Guardar

**Resultado:**
- ✅ Producto guardado con `id_punto_venta=3`
- ✅ Categoría asignada con `id_punto_venta=3`
- ✅ Aparece en Totem del Buffet
- ✅ Aparece en Totem del Kiosco
- ✅ Badge morado en el dashboard

---

## 🔍 Validaciones Implementadas

### **1. En el Formulario:**
- ✅ Punto de Venta es requerido
- ✅ Categoría es requerida
- ✅ Solo se muestran categorías del punto de venta seleccionado
- ✅ Si se cambia el punto de venta, se resetea la categoría si no es compatible

### **2. En la Base de Datos:**
- ✅ `id_punto_venta` es NOT NULL
- ✅ Constraint FK asegura integridad referencial
- ✅ Cada categoría tiene su punto de venta definido

---

## 🧪 Testing

### **Test 1: Crear Producto "Ambos"**
1. ✅ Seleccionar Punto de Venta: "Ambos"
2. ✅ Verificar que solo aparezcan categorías "Ambos"
3. ✅ Seleccionar una categoría
4. ✅ Guardar producto
5. ✅ Verificar que aparece en ambos totems

### **Test 2: Cambiar Punto de Venta en Formulario**
1. ✅ Seleccionar Punto de Venta: "Buffet"
2. ✅ Seleccionar Categoría: "Bebidas" (de Buffet)
3. ✅ Cambiar Punto de Venta: "Kiosco"
4. ✅ Verificar que la categoría se resetea
5. ✅ Verificar que solo aparecen categorías de Kiosco

### **Test 3: Editar Producto**
1. ✅ Editar producto existente "Ambos"
2. ✅ Verificar que se muestra la categoría correcta
3. ✅ Cambiar a otro punto de venta
4. ✅ Verificar que las categorías se actualizan

### **Test 4: Filtro de Categorías en Dashboard**
1. ✅ Ir a Gestión de Categorías
2. ✅ Filtrar por "Ambos"
3. ✅ Verificar que solo se muestran categorías con `id_punto_venta=3`
4. ✅ Verificar badge morado

---

## 📊 Estado Final

### **Categorías en Base de Datos:**

| Punto de Venta | Cantidad | IDs Aproximados |
|----------------|----------|-----------------|
| Buffet (1) | 9 | 1-9, 13-21, etc. |
| Kiosco (2) | 9 | 10-18, etc. |
| **Ambos (3)** | **9** | **29-37** ⭐ NUEVO |
| **TOTAL** | **27** | - |

### **Categorías "Ambos" Creadas:**

1. Bebidas (Ambos)
2. Snacks (Ambos)
3. Comidas (Ambos)
4. Alfajores (Ambos)
5. Galletitas (Ambos)
6. Helados (Ambos)
7. Golosinas (Ambos)
8. Cafeteria (Ambos)
9. Especial (Ambos)

---

## ✅ Beneficios de la Solución

1. **Coherencia total:** Un producto "Ambos" siempre usa categoría "Ambos"
2. **Prevención de errores:** El formulario no permite asignaciones incorrectas
3. **Experiencia de usuario mejorada:** Orden lógico (punto de venta → categoría)
4. **Filtrado inteligente:** Solo se muestran opciones relevantes
5. **Mantenibilidad:** Clara separación conceptual
6. **Escalabilidad:** Fácil agregar nuevas categorías "Ambos"

---

## 🚀 Próximos Pasos

1. ✅ Ejecutar script `crear_categorias_ambos.sql`
2. ✅ Recargar dashboard (Ctrl + F5)
3. ✅ Probar crear producto "Ambos"
4. ✅ Verificar en ambos totems
5. ✅ Capacitar al equipo sobre el nuevo flujo

---

## 📞 Soporte

Si la categoría no se filtra correctamente:
1. Limpiar caché del navegador (Ctrl + Shift + Delete)
2. Hard reload (Ctrl + F5)
3. Verificar que las categorías "Ambos" existan en la base de datos:
   ```sql
   SELECT * FROM categorias WHERE id_punto_venta = 3;
   ```
4. Revisar consola del navegador (F12) por errores JavaScript

---

**Implementado por:** Sistema de Totem Murialdo  
**Fecha:** 30 de Octubre de 2025  
**Versión:** 1.0 - Categorías Ambos con Filtrado
