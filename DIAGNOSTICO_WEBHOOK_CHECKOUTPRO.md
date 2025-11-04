# 🔍 DIAGNÓSTICO COMPLETO DEL WEBHOOK CHECKOUTPRO

## ❌ PROBLEMAS ENCONTRADOS:

### 1. **Columna `external_reference` no existe en tabla `pedidos`**
**Error:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'external_reference' in 'WHERE'`

**Causa:** El código del webhook intenta buscar pedidos por `external_reference`, pero esa columna nunca se agregó a la tabla.

**Impacto:** El webhook recibe la notificación, obtiene los datos correctamente, pero FALLA al buscar el pedido en la BD.

---

### 2. **Formato de webhook de MercadoPago cambió**
**Error:** `ERROR: ID vacío`

**Causa:** MercadoPago ahora envía dos formatos diferentes:
- **Formato antiguo:** `{"type": "payment", "data": {"id": "123"}}`
- **Formato NUEVO:** `{"topic": "payment", "resource": "123"}` ← El código solo manejaba el antiguo

**Impacto:** El webhook no podía extraer el ID del pago correctamente.

---

## ✅ SOLUCIONES APLICADAS:

### 1. **Agregar columna `external_reference`**
**Script creado:** `backend-checkoutpro/api/ejecutar_add_external_reference.php`

**Ejecutar en el navegador:**
```
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/ejecutar_add_external_reference.php
```

Este script:
- ✅ Verifica si la columna ya existe
- ✅ Agrega la columna `external_reference VARCHAR(50) NULL`
- ✅ Crea un índice para optimizar búsquedas
- ✅ Muestra la estructura completa de la tabla

---

### 2. **Soporte para ambos formatos de webhook**
**Archivo modificado:** `backend-checkoutpro/api/webhook_checkoutpro.php`

**Cambio aplicado:**
```php
// ANTES (solo formato antiguo):
$webhook_type = $body['type'] ?? '';
$webhook_id = $body['data']['id'] ?? '';

// AHORA (ambos formatos):
$webhook_type = $body['type'] ?? $body['topic'] ?? '';
$webhook_id = $body['data']['id'] ?? $body['resource'] ?? '';

// Si resource es URL, extraer solo el ID
if (is_string($webhook_id) && strpos($webhook_id, 'http') === 0) {
    $webhook_id = basename(parse_url($webhook_id, PHP_URL_PATH));
}
```

---

## 🧪 PASOS PARA PROBAR:

### **PASO 1: Agregar columna a la BD**
```
Abre: https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/ejecutar_add_external_reference.php
```
Deberías ver: ✅ "Columna agregada exitosamente"

---

### **PASO 2: Realizar un pago de prueba**
1. Ve al sistema de pedidos
2. Crea un pedido con CheckoutPro
3. Completa el pago
4. Observa los logs del webhook

---

### **PASO 3: Verificar logs**
```
Abre: https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/test_webhook_logs.php
```

**Deberías ver:**
- ✅ "Datos obtenidos - Status: approved, External ref: CP_..."
- ✅ "Pedido encontrado - ID: XXX"
- ✅ "Estado del pedido actualizado a PAGADO"
- ✅ Tipo: SUCCESS (en verde)

**NO deberías ver:**
- ❌ "Unknown column 'external_reference'"
- ❌ "ERROR: ID vacío"

---

## 📊 LOGS ACTUALES (antes de la corrección):

```
✅ Webhooks recibidos: 3
✅ Procesados exitosamente: 0
❌ Con errores: 3
```

**Errores detectados:**
1. Log #313: `Unknown column 'external_reference' in 'WHERE'` ← PROBLEMA PRINCIPAL
2. Log #309, #303, #302: `ERROR: ID vacío` ← Formato nuevo de MercadoPago
3. Log #308, #307: Webhook recibido de IP 54.88.218.97 (MercadoPago real)

---

## ✅ DESPUÉS DE APLICAR LAS CORRECCIONES:

**Se espera ver:**
```
✅ Webhooks recibidos: X
✅ Procesados exitosamente: X
❌ Con errores: 0
```

---

## 🚀 COMANDOS RÁPIDOS:

### Ver logs del webhook:
```
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/test_webhook_logs.php
```

### Agregar columna external_reference:
```
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/ejecutar_add_external_reference.php
```

### Simular webhook:
```
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/test_webhook_simulation.php
```

---

## 🔧 CORRECCIONES ADICIONALES RECOMENDADAS:

### **3. Actualizar `create_checkoutpro.php`**
Asegurarse de que cuando se crea la preferencia, se guarde el `external_reference` en la BD:

```php
// Al crear el pedido ANTES de redireccionar a MercadoPago
$stmt = $pdo->prepare("
    INSERT INTO pedidos (external_reference, metodo_pago, estado, ...) 
    VALUES (?, 'VIRTUAL', 'PENDIENTE', ...)
");
$stmt->execute([$external_reference, ...]);
```

### **4. Agregar columna `payment_id`**
Para guardar el ID del pago de MercadoPago:

```sql
ALTER TABLE pedidos ADD COLUMN payment_id VARCHAR(50) NULL;
```

---

## 📋 RESUMEN EJECUTIVO:

1. ✅ **Webhook SÍ está recibiendo notificaciones** (3 intentos en logs)
2. ❌ **Webhook FALLA** por falta de columna `external_reference`
3. ❌ **Webhook FALLA** por cambio de formato en MercadoPago API
4. ✅ **Solución 1:** Script PHP para agregar columna (ejecutar_add_external_reference.php)
5. ✅ **Solución 2:** Código actualizado para soportar ambos formatos de webhook
6. 🧪 **Próximo paso:** Ejecutar el script y probar con un pago real

---

**Fecha:** 2025-10-23 19:34
**External Reference del último intento:** CP_1761258686755_wkqgbvkof
**Payment ID:** 130480282009
**IP de MercadoPago:** 54.88.218.97, 18.215.140.160
