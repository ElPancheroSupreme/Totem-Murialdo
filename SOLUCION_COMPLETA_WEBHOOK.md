# ✅ SOLUCIÓN COMPLETA DEL WEBHOOK CHECKOUTPRO

## 🎯 Problema Principal Identificado:

**El webhook de CheckoutPro NO funcionaba porque:**

1. ❌ La tabla `pedidos` **no tenía** la columna `external_reference`
2. ❌ El código `create_checkoutpro.php` **no guardaba** el pedido en la BD antes de redirigir a MercadoPago
3. ❌ Cuando el webhook intentaba buscar el pedido, **no existía** en la base de datos

**Resultado:** El webhook recibía las notificaciones correctamente, pero siempre mostraba:
```
ERROR: No se encontró pedido con external_reference: CP_1761259527928_foodom10f
```

---

## ✅ Correcciones Aplicadas:

### **1. Agregado soporte para nuevo formato de MercadoPago** ✓
**Archivo:** `backend-checkoutpro/api/webhook_checkoutpro.php`

**Problema:** MercadoPago cambió el formato del webhook de:
```json
{"type": "payment", "data": {"id": "123"}}
```
A:
```json
{"topic": "payment", "resource": "123"}
```

**Solución:** El código ahora soporta ambos formatos:
```php
$webhook_type = $body['type'] ?? $body['topic'] ?? '';
$webhook_id = $body['data']['id'] ?? $body['resource'] ?? '';

// Si resource es URL, extraer solo el ID
if (is_string($webhook_id) && strpos($webhook_id, 'http') === 0) {
    $webhook_id = basename(parse_url($webhook_id, PHP_URL_PATH));
}
```

---

### **2. Corregidos headers HTML en scripts de testing** ✓
**Archivos:**
- `backend-checkoutpro/api/test_webhook_logs.php`
- `backend-checkoutpro/api/test_webhook_simulation.php`

**Problema:** Los scripts mostraban HTML en consola sin estilos

**Solución:**
- Cambiado `Content-Type: application/json` a `text/html; charset=utf-8`
- Agregado estructura HTML completa con `<!DOCTYPE html>`
- Los estilos CSS ahora se aplican correctamente

---

### **3. Script para agregar columna `external_reference`** ✓
**Archivo:** `backend-checkoutpro/api/ejecutar_add_external_reference.php`

**Función:**
- Verifica si la columna existe
- Agrega `external_reference VARCHAR(50) NULL` a la tabla `pedidos`
- Crea índice para optimizar búsquedas
- Muestra estructura completa de la tabla

**Ejecutar:**
```
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/ejecutar_add_external_reference.php
```

---

### **4. Modificado `create_checkoutpro.php` para guardar pedido ANTES** ✓
**Archivo:** `backend-checkoutpro/api/create_checkoutpro.php`

**Nuevo flujo:**

**ANTES:**
```
1. Usuario crea pedido
2. Se genera external_reference
3. Se crea preferencia en MercadoPago
4. Usuario es redirigido a pagar
5. Webhook llega → ❌ Pedido NO existe en BD
```

**AHORA:**
```
1. Usuario crea pedido
2. Se genera external_reference
3. ✅ SE GUARDA PEDIDO EN BD con external_reference
4. Se crea preferencia en MercadoPago
5. Usuario es redirigido a pagar
6. Webhook llega → ✅ Pedido EXISTE en BD → Se actualiza estado
```

**Código agregado:**
```php
// Guardar pedido en BD ANTES de crear la preferencia
$stmt = $pdo->prepare("
    INSERT INTO pedidos (
        external_reference, 
        monto_total, 
        metodo_pago, 
        estado, 
        estado_pago,
        creado_en
    ) VALUES (?, ?, 'VIRTUAL', 'PREPARACION', 'PENDIENTE', NOW())
");

$stmt->execute([$external_reference, $monto_total]);

// Guardar items del pedido
foreach ($data['items'] as $item) {
    // Insertar en items_pedido...
}
```

---

## 🧪 Pasos para Probar:

### **PASO 1: Ejecutar script de BD (CRÍTICO)**
```
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/ejecutar_add_external_reference.php
```

**Resultado esperado:**
```
✅ Columna 'external_reference' agregada exitosamente
✅ Índice creado exitosamente
```

---

### **PASO 2: Realizar un pago de prueba**

1. Ve al sistema de pedidos
2. Agrega productos al carrito
3. Selecciona pagar con CheckoutPro
4. **OBSERVA:** Ahora el pedido se guarda en BD ANTES de redirigir
5. Completa el pago en MercadoPago
6. Cuando MercadoPago envíe el webhook, el pedido YA EXISTIRÁ

---

### **PASO 3: Verificar logs del webhook**
```
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/test_webhook_logs.php
```

**Resultado esperado (DESPUÉS de las correcciones):**
```
✅ Webhooks recibidos: X
✅ Procesados exitosamente: X
❌ Con errores: 0
```

**Mensajes esperados en logs:**
```
✅ Pago aprobado - External reference: CP_...
✅ Pedido encontrado - ID: XXX
✅ Estado del pedido actualizado a PAGADO
```

**NO deberías ver:**
```
❌ ERROR: No se encontró pedido con external_reference
❌ Unknown column 'external_reference'
```

---

## 📊 Logs Actuales (antes de la corrección):

**Último intento:**
- External Ref: `CP_1761259527928_foodom10f`
- Payment ID: `130482544419`
- Merchant Order: `34978583395`
- Estado: `approved`
- **Resultado:** ❌ ERROR - Pedido no encontrado

**Por qué fallaba:**
1. El pedido NO se guardaba en BD antes de crear la preferencia
2. Cuando el webhook buscaba `CP_1761259527928_foodom10f`, no existía
3. Error: "No se encontró pedido con external_reference"

---

## ✅ Después de Aplicar las Correcciones:

**Flujo esperado:**

1. **Usuario crea pedido:**
   - Se genera: `CP_1761260000000_abc123xyz`
   - ✅ Se GUARDA en BD con estado `PENDIENTE`

2. **Usuario paga con MercadoPago:**
   - Completa el pago
   - MercadoPago envía webhook

3. **Webhook procesa:**
   - ✅ Busca pedido con `external_reference`
   - ✅ ENCUENTRA el pedido (porque ya existe)
   - ✅ Actualiza estado a `PAGADO`
   - ✅ Responde con success

4. **Usuario ve su ticket:**
   - ✅ Con número de orden correcto
   - ✅ Sin duplicados
   - ✅ Con todos los datos

---

## 📋 Checklist Final:

- [ ] **Ejecutar:** `ejecutar_add_external_reference.php` (agregar columna)
- [ ] **Verificar:** Columna agregada correctamente
- [ ] **Probar:** Crear un pedido nuevo con CheckoutPro
- [ ] **Observar:** Pedido se guarda en BD antes de redirección
- [ ] **Pagar:** Completar pago en MercadoPago
- [ ] **Revisar logs:** Webhook debe mostrar "Pedido encontrado"
- [ ] **Verificar ticket:** Sin duplicados, número correcto

---

## 🚀 Comandos Rápidos:

```bash
# Ver logs del webhook (con estilos)
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/test_webhook_logs.php

# Agregar columna external_reference
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/ejecutar_add_external_reference.php

# Simular webhook (testing)
https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/test_webhook_simulation.php
```

---

**Fecha de corrección:** 2025-10-23 19:49
**Archivos modificados:** 4
**Estado:** ✅ Listo para probar en producción
