# 🎨 Sistema de Iconos SVG para Categorías

## 📋 Resumen del Cambio

Se implementó un sistema completo para subir y gestionar iconos SVG personalizados para las categorías del tótem, reemplazando el sistema anterior basado en emojis.

---

## ✨ Características Implementadas

### 1. **Upload de Iconos SVG**
- Subida de archivos SVG desde el ConfigDash
- Validación de tipo de archivo (solo SVG)
- Validación de tamaño (máximo 500KB)
- Preview en tiempo real del icono seleccionado

### 2. **Almacenamiento en Base de Datos**
- Campo `icono` modificado para almacenar rutas de archivos SVG
- Soporte para nombres de archivo personalizados
- Sistema de fallback automático si falta el icono

### 3. **Visualización Consistente**
- Los iconos se muestran igual en ConfigDash y en el tótem
- Renderizado optimizado con manejo de errores
- Soporte para archivos SVG multicolor

---

## 🚀 Cómo Usar

### **Paso 1: Ejecutar la Migración**

1. Abrir en el navegador:
   ```
   http://tu-servidor/Totem_Murialdo/backend/sql/migrar_iconos_categorias.html
   ```

2. Hacer clic en "▶️ Ejecutar Migración Ahora"

3. Verificar que se muestre "✅ MIGRACIÓN COMPLETADA EXITOSAMENTE"

### **Paso 2: Subir un Icono Personalizado**

1. Ir a **ConfigDash → Categorías**
2. Hacer clic en "✏️ Editar" en la categoría deseada
3. En el modal, hacer clic en "📁 Seleccionar SVG"
4. Elegir un archivo SVG de tu computadora
5. Esperar a que se suba y se muestre el preview
6. Hacer clic en "Guardar Categoría"

### **Paso 3: Verificar en el Tótem**

1. Ir a `kiosco_dinamico.html` o `buffet.html`
2. Verificar que el nuevo icono se muestre correctamente en la lista de categorías

---

## 📁 Archivos Modificados

### Backend
- `backend/sql/alter_categorias_icono_svg.sql` - Script SQL para modificar estructura
- `backend/sql/ejecutar_migracion_iconos.php` - Script PHP ejecutable de migración
- `backend/sql/migrar_iconos_categorias.html` - Interfaz web para ejecutar migración
- `backend/admin/api/upload_icono_categoria.php` - API para subir archivos SVG ✨ NUEVO
- `backend/admin/api/api_categorias.php` - Actualizado para manejar rutas SVG

### Frontend (Dashboard)
- `frontend/views/ConfigDash.html` - Agregado input file y preview de iconos
- `frontend/assets/js/dashboard/categorias.js` - Lógica de upload y renderizado SVG

### Frontend (Tótem)
- `frontend/assets/js/kiosco_dinamico.js` - Actualizado renderizado de iconos
- `frontend/assets/js/buffet.js` - Actualizado renderizado de iconos

---

## 🔒 Validaciones Implementadas

### En el Cliente (JavaScript)
- Validación de extensión `.svg`
- Validación de tamaño máximo 500KB
- Preview antes de enviar al servidor

### En el Servidor (PHP)
- Validación de tipo MIME
- Validación de contenido (debe contener etiqueta `<svg>`)
- Sanitización de nombre de archivo
- Control de permisos (solo admin y supervisor)
- Generación de nombres únicos con timestamp

---

## 📂 Estructura de Archivos

```
frontend/assets/images/Iconos/
├── Icono_Bebidas.svg           (existente)
├── Icono_Snacks.svg            (existente)
├── Icono_Comidas.svg           (existente)
├── Icono_Alfajores.svg         (existente)
├── Icono_Galletitas.svg        (existente)
├── Icono_Helados.svg           (existente)
├── Icono_Golosinas.svg         (existente)
├── Icono_Cafeteria.svg         (existente)
├── Icono_Especial.svg          (existente)
└── Icono_NombrePersonalizado_123456789.svg  (subidos por usuarios)
```

---

## 🎯 Flujo de Datos

```
┌─────────────────┐
│  ConfigDash     │
│  (Subir SVG)    │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────┐
│  upload_icono_categoria.php │
│  - Valida archivo           │
│  - Guarda en /Iconos/       │
│  - Retorna nombre archivo   │
└────────┬────────────────────┘
         │
         ▼
┌─────────────────────────────┐
│  api_categorias.php         │
│  - Guarda ruta en BD        │
│  - Columna: icono           │
└────────┬────────────────────┘
         │
         ▼
┌─────────────────────────────┐
│  Tótem (Kiosco/Buffet)      │
│  - Lee icono desde BD       │
│  - Renderiza SVG            │
└─────────────────────────────┘
```

---

## 🛠️ Solución de Problemas

### **Problema:** No se muestra el icono en el tótem
**Solución:** 
- Verificar que el archivo existe en `/frontend/assets/images/Iconos/`
- Revisar que el nombre del archivo coincide con el de la BD
- Verificar permisos del archivo (debe ser 644)

### **Problema:** Error al subir archivo
**Solución:**
- Verificar que es un archivo SVG válido
- Verificar que pesa menos de 500KB
- Verificar permisos de escritura en `/frontend/assets/images/Iconos/`

### **Problema:** El icono se ve cortado o deformado
**Solución:**
- Abrir el SVG en un editor y ajustar el viewBox
- Asegurarse de que no tiene dimensiones fijas (width/height absolutos)
- Usar dimensiones relativas o porcentuales

---

## 📊 Cambios en Base de Datos

### Antes
```sql
icono VARCHAR(10) DEFAULT NULL  -- Almacenaba emojis: "🍔"
```

### Después
```sql
icono VARCHAR(255) DEFAULT NULL COMMENT 'Ruta del archivo SVG'  -- Almacena: "Icono_Bebidas.svg"
```

---

## 🔐 Seguridad

- ✅ Solo usuarios con rol Admin o Supervisor pueden subir iconos
- ✅ Validación estricta de tipo de archivo
- ✅ Sanitización de nombres de archivo
- ✅ Nombres únicos con timestamp para evitar sobreescritura
- ✅ Validación de contenido SVG (debe contener etiqueta `<svg>`)
- ✅ Límite de tamaño de archivo (500KB)

---

## 🎨 Recomendaciones de Diseño

Para mejores resultados con los iconos SVG:

1. **Tamaño recomendado:** 64x64px o 128x128px
2. **Formato:** SVG optimizado (puedes usar SVGOMG.com)
3. **Colores:** Pueden ser multicolor, el sistema los respeta
4. **ViewBox:** Usar `viewBox="0 0 64 64"` para escalado correcto
5. **Simplicidad:** Iconos simples se ven mejor en tamaños pequeños

---

## 📝 Notas Adicionales

- Los iconos antiguos (emojis) fueron migrados automáticamente a rutas SVG
- El sistema mantiene compatibilidad con los archivos SVG existentes
- Si se sube un icono nuevo para una categoría, reemplaza el anterior en la BD (pero no borra el archivo físico)
- Los archivos SVG subidos tienen un timestamp único para evitar colisiones

---

## 🚀 Próximas Mejoras (Opcional)

- [ ] Galería de iconos prediseñados para elegir
- [ ] Editor de color para iconos SVG monocromáticos
- [ ] Previsualización en diferentes tamaños
- [ ] Limpieza automática de iconos no utilizados
- [ ] Versionado de iconos

---

## 📞 Soporte

Si tienes problemas con el sistema de iconos SVG:
1. Revisa los logs del navegador (Console)
2. Verifica la estructura de la base de datos
3. Confirma que ejecutaste la migración correctamente

---

**Fecha de implementación:** 29 de Octubre, 2025  
**Versión:** 1.0.0  
**Estado:** ✅ Completado y Funcional
