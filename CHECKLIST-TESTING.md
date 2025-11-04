# 📋 CHECKLIST DE VERIFICACIÓN - TÓTEM MURIALDO v2.0

## ✅ PRUEBAS BÁSICAS DE FUNCIONAMIENTO

### 🔵 Sistema QR
- [ ] **Conectividad**: `/Totem_Murialdo/index-qr.html` carga correctamente
- [ ] **Redirección**: Se redirige a `frontend-qr/views/index.html`
- [ ] **Kiosco**: Los productos cargan desde `backend-qr/api/api_kiosco.php`
- [ ] **Carrito**: Se pueden agregar/quitar productos
- [ ] **Método de pago**: Solo muestra opciones QR (no CheckoutPro)
- [ ] **Generación QR**: ⭐ CORREGIDO - Se crea el código QR correctamente
  - [ ] **Test Conectividad**: `backend-qr/api/test.php` responde OK
  - [ ] **Backend URL**: Consola muestra `🎯 QR Backend URL: [URL_DETECTADA]`
  - [ ] **No Error 404**: `backend-qr/api/back.php` accesible
- [ ] **API Calls**: Todas las llamadas van a `/backend-qr/`
- [ ] **Logs**: Se generan logs en `backend-qr/logs/`

### 🔴 Sistema CheckoutPro  
- [ ] **Conectividad**: `/Totem_Murialdo/index-checkoutpro.html` carga correctamente
- [ ] **Redirección**: Se redirige a `frontend-checkoutpro/views/index.html`
- [ ] **Kiosco**: Los productos cargan desde `backend-checkoutpro/api/api_kiosco.php`
- [ ] **Carrito**: Se pueden agregar/quitar productos
- [ ] **Método de pago**: Solo muestra opciones CheckoutPro (no QR)
- [ ] **Checkout**: Se crea la preferencia CheckoutPro correctamente
- [ ] **API Calls**: Todas las llamadas van a `/backend-checkoutpro/`
- [ ] **Logs**: Se generan logs en `backend-checkoutpro/logs/`

## 🔍 PRUEBAS DE INDEPENDENCIA

### 🚫 Verificar NO Referencias Cruzadas
- [ ] **Frontend QR**: NO menciona CheckoutPro en consola
- [ ] **Frontend CheckoutPro**: NO menciona QR en consola  
- [ ] **Backend QR**: NO recibe llamadas de frontend-checkoutpro
- [ ] **Backend CheckoutPro**: NO recibe llamadas de frontend-qr

### 🔧 Verificar Configuraciones Separadas
- [ ] **Credenciales QR**: `backend-qr/config/config.php` tiene credenciales QR
- [ ] **Credenciales CheckoutPro**: `backend-checkoutpro/config/config.php` tiene credenciales CheckoutPro
- [ ] **Webhooks**: Cada backend tiene su webhook específico
- [ ] **Estados**: Cada backend guarda estados en su directorio `ordenes_status/`

## 🧪 PRUEBAS AVANZADAS

### 💰 Flujo Completo QR
1. [ ] Agregar productos al carrito
2. [ ] Seleccionar método QR
3. [ ] Generar código QR
4. [ ] Simular pago exitoso
5. [ ] Verificar actualización en BD
6. [ ] Confirmar ticket generado

### 💳 Flujo Completo CheckoutPro
1. [ ] Agregar productos al carrito  
2. [ ] Seleccionar método CheckoutPro
3. [ ] Crear preferencia CheckoutPro
4. [ ] Redirigir a MercadoPago
5. [ ] Simular pago exitoso
6. [ ] Verificar webhook recibido
7. [ ] Confirmar actualización en BD

## 📊 ConfigDash.html Centralizado ⭐ NUEVO

### Detección Automática del Backend
- [ ] **Desde QR**: `frontend-qr/views/ConfigDash.html` → redirige y usa `backend-qr`
- [ ] **Desde CheckoutPro**: `frontend-checkoutpro/views/ConfigDash.html` → redirige y usa `backend-checkoutpro`  
- [ ] **Acceso Directo**: `frontend/views/ConfigDash.html` → usa `backend` original
- [ ] **Parámetros URL**: `?source=qr` y `?source=checkoutpro` funcionan correctamente

### Indicadores Visuales
- [ ] **Sistema QR**: Muestra "🔄 Sistema QR Dinámico" (azul/morado)
- [ ] **Sistema CheckoutPro**: Muestra "💳 Sistema CheckoutPro" (verde/azul)
- [ ] **Sistema Original**: Muestra "Sistema Original" (gris)

### Funcionalidad del Dashboard
- [ ] **Estadísticas**: Cargan desde el backend correcto
- [ ] **Configuraciones**: Se guardan en el backend adecuado
- [ ] **APIs**: Todas las llamadas van al backend detectado
- [ ] **Scripts JS**: Se cargan dinámicamente según el backend
- [ ] **Logs de Consola**: Muestran backend detectado y scripts cargados

### Pruebas Específicas
- [ ] **Redirección QR**: Acceder a `frontend-qr/views/ConfigDash.html` y verificar redirección
- [ ] **Redirección CheckoutPro**: Acceder a `frontend-checkoutpro/views/ConfigDash.html` y verificar redirección  
- [ ] **Datos Independientes**: Cada sistema muestra sus propios datos/estadísticas
- [ ] **Sin Interferencias**: Cambios en un sistema no afectan al otro

## 🎯 COMANDOS ÚTILES PARA TESTING

### Verificar logs en tiempo real:
```bash
# QR Logs
tail -f backend-qr/logs/qr_webhook.log

# CheckoutPro Logs  
tail -f backend-checkoutpro/logs/checkoutpro_webhook.log
```

### Verificar estado de archivos:
```bash
# Estados QR
ls -la backend-qr/ordenes_status/

# Estados CheckoutPro
ls -la backend-checkoutpro/ordenes_status/
```

### Limpiar logs para testing:
```bash
# Limpiar logs QR
> backend-qr/logs/qr_webhook.log
> backend-qr/estado_pago_api.log

# Limpiar logs CheckoutPro
> backend-checkoutpro/logs/checkoutpro_webhook.log  
> backend-checkoutpro/estado_pago_api.log
```

## 🏁 RESULTADO ESPERADO

✅ **ÉXITO**: Ambos sistemas funcionan completamente independientes
❌ **FALLO**: Hay referencias cruzadas o errores de conectividad

---
**Fecha de verificación**: ___________
**Verificado por**: ___________
**Estado**: [ ] ✅ Aprobado  [ ] ❌ Requiere corrección