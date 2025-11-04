# ConfigDash.html Centralizado - Documentación

## 📋 Resumen
Se ha consolidado el archivo `ConfigDash.html` en una sola ubicación centralizada que funciona con ambos sistemas de pago (QR Dinámico y CheckoutPro) de manera independiente.

## 📁 Estructura de Archivos
```
frontend/
└── views/
    └── ConfigDash.html                     ← ARCHIVO CENTRALIZADO

frontend-qr/
└── views/
    └── ConfigDash.html                     ← Redirección al centralizado

frontend-checkoutpro/
└── views/
    └── ConfigDash.html                     ← Redirección al centralizado
```

## 🎯 Cómo Funciona

### 1. Detección Automática del Backend
El sistema detecta automáticamente qué backend usar basándose en:
- **Parámetro URL**: `?source=qr` o `?source=checkoutpro`
- **Ruta del navegador**: Si viene desde `/frontend-qr/` o `/frontend-checkoutpro/`
- **Por defecto**: Usa el backend original `/backend/`

### 2. Rutas API Dinámicas
Todas las llamadas API se adaptan automáticamente:
```javascript
// Antes (estático):
fetch('/Totem_Murialdo/backend/admin/api/configuracion_horarios.php')

// Ahora (dinámico):
fetch(BACKEND_API + '/admin/api/configuracion_horarios.php')
```

### 3. Carga de Scripts Dinámica
Los scripts JavaScript se cargan según el backend detectado:
```javascript
// Se carga automáticamente desde el backend correcto:
// - backend-qr/admin/js/
// - backend-checkoutpro/admin/js/
// - backend/admin/js/ (original)
```

## 🚀 Acceso al Dashboard

### Desde Sistema QR:
```
http://localhost/Totem_Murialdo/frontend/views/ConfigDash.html
→ Usa directamente: backend/admin/api/
```

### Desde Frontend CheckoutPro:
```
http://localhost/Totem_Murialdo/frontend-checkoutpro/views/ConfigDash.html
→ Redirige a: frontend/views/ConfigDash.html?source=checkoutpro
→ Usa: backend-checkoutpro/admin/api/
```

### Acceso Directo (Original):
```
http://localhost/Totem_Murialdo/frontend/views/ConfigDash.html
→ Usa: backend/admin/api/
```

## 🔍 Indicadores Visuales

El sistema muestra un indicador en la esquina superior derecha:
- 🔄 **Sistema QR** (desde /frontend/) - azul/morado
- 💳 **Sistema CheckoutPro** (desde /frontend-checkoutpro/) - verde/azul

## ⚙️ Archivos Copiados

### Scripts JavaScript (admin/js/):
- `config.js` - Configuración del dashboard
- `productos.js` - Gestión de productos
- `usuarios.js` - Gestión de usuarios
- `config_mp.js` - Configuración MercadoPago
- `config_test.php` - Pruebas de configuración

### APIs Administrativas (admin/api/):
- `configuracion_horarios.php` - Configuración de horarios
- `api_estadisticas.php` - Estadísticas y reportes
- `api_productos.php` - API de productos
- `api_usuarios.php` - API de usuarios
- `pin_admin.php` - Verificación PIN admin
- Y muchos más...

## ✅ Ventajas del Sistema Consolidado

1. **Mantenimiento Único**: Solo hay que actualizar un archivo
2. **Consistencia**: Misma interfaz para ambos sistemas
3. **Detección Automática**: No hay que configurar nada manualmente
4. **Independencia**: Cada sistema usa sus propias APIs y configuraciones
5. **Escalabilidad**: Fácil agregar nuevos backends en el futuro

## 🔧 Troubleshooting

### Si el dashboard no carga:
1. Verificar que existan los directorios `backend-qr/admin/` y `backend-checkoutpro/admin/`
2. Comprobar que se copiaron todos los archivos JS y API
3. Revisar la consola del navegador para errores de JavaScript
4. Verificar los permisos de los archivos copiados

### Si usa el backend incorrecto:
1. Verificar desde qué frontend se accede (/frontend/ vs /frontend-checkoutpro/)
2. Comprobar la función `detectarBackend()` en la consola
3. Usar las rutas correctas según el sistema

## 📝 Logs
El sistema registra en la consola del navegador:
```
🎯 ConfigDash.html - Backend detectado: /Totem_Murialdo/backend
📁 Scripts cargados desde: ../../backend/admin/js/

O:

🎯 ConfigDash.html - Backend detectado: /Totem_Murialdo/backend-checkoutpro  
📁 Scripts cargados desde: ../../backend-checkoutpro/admin/js/
```

---
**Fecha**: 9 de Octubre, 2025
**Estado**: ✅ Consolidación completada - Arquitectura simplificada
**Sistemas**: QR (/frontend/ + /backend/) + CheckoutPro (/frontend-checkoutpro/ + /backend-checkoutpro/)