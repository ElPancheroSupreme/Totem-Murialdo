# Integración QR Dinámico Mercado Pago
Esta linea de texto es para test. Linea de texto pa tessstttttt
Esta aplicación permite generar códigos QR dinámicos de Mercado Pago para recibir pagos de forma rápida y segura.

## 🚀 Características

- ✅ **Tienda completa** con interfaz de productos y carrito
- ✅ **Integración automática** del carrito con QR de pago
- ✅ Generación de QR Dinámico con Mercado Pago
- ✅ Interfaz web moderna y responsiva
- ✅ Manejo de errores detallado
- ✅ Configuración centralizada
- ✅ Compatible con cuentas de prueba y producción
- ✅ SDK oficial de Mercado Pago v3
- ✅ **Flujo completo** desde selección de productos hasta pago

## 📋 Requisitos

- PHP 7.1 o superior
- Composer
- Servidor web (Apache/Nginx)
- Cuenta de Mercado Pago con QR Dinámico habilitado
- Extensión cURL habilitada en PHP

## 🛠️ Instalación

### 1. Clonar o descargar el proyecto
```bash
git clone https://github.com/papittas/Totem_Murialdo/
cd Totem_Murialdo
```

### 2. Instalar dependencias con Composer
```bash
composer install
```

### 3. Instalar SDK de Mercado Pago
```bash
composer require mercadopago/dx-php
```

### 4. Configurar credenciales
Edita el archivo `config.php` y reemplaza las credenciales:

```php
// Access Token de Mercado Pago
define('MP_ACCESS_TOKEN', 'TU_ACCESS_TOKEN_AQUI');

// Public Key (opcional)
define('MP_PUBLIC_KEY', 'TU_PUBLIC_KEY_AQUI');

// User ID de tu cuenta
define('MP_USER_ID', 'TU_USER_ID_AQUI');

// External POS ID (identificador del punto de venta)
define('MP_EXTERNAL_POS_ID', 'pos001');

// Client ID y Client Secret (para OAuth, webhooks y APIs avanzadas)
define('MP_CLIENT_ID', 'TU_CLIENT_ID');
define('MP_CLIENT_SECRET', 'TU_CLIENT_SECRET');
```

## 🔧 Configuración

### Obtener credenciales de Mercado Pago

1. Ve a [Mercado Pago Developers](https://www.mercadopago.com.ar/developers/panel)
2. Crea una aplicación
3. Configura la integración con "CódigoQR" y modelo "Dinámico"
4. Copia el Access Token, Public Key y User ID

### Crear Store (Sucursal) y POS (Punto de Venta)

**Importante**: Antes de usar QR Dinámico, debes crear una sucursal y un punto de venta.

#### Opción 1: Usar scripts automáticos (Recomendado)

1. **Crear Store**: Ve a `http://localhost/php_mp/create_store.php`
2. **Crear POS**: Ve a `http://localhost/php_mp/create_pos.php`

#### Opción 2: Usar APIs manualmente

**Crear Store:**
```bash
curl -X POST \
  'https://api.mercadopago.com/users/TU_USER_ID/stores' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer TU_ACCESS_TOKEN' \
  -d '{
    "name": "Mi Sucursal",
    "external_id": "store001",
    "location": {
      "street_number": "123",
      "street_name": "Calle Ejemplo",
      "city_name": "Ciudad",
      "state_name": "Provincia",
      "latitude": -34.6037,
      "longitude": -58.3816
    }
  }'
```

**Crear POS:**
```bash
curl -X POST \
  'https://api.mercadopago.com/pos' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer TU_ACCESS_TOKEN' \
  -d '{
    "name": "Mi POS",
    "external_store_id": "store001",
    "external_id": "pos001"
  }'
```

## 🎯 Uso

### Flujo Completo de la Tienda

#### 1. Acceder a la tienda
```
http://localhost/php_mp/index.html
```

#### 2. Seleccionar productos
- Navega por las categorías (Bebidas, Sanguches, Ensaladas, etc.)
- Haz clic en los productos para agregarlos al carrito
- El carrito se actualiza automáticamente en tiempo real

#### 3. Revisar el carrito
- El footer muestra el resumen de tu orden
- Puedes cancelar la orden o continuar al pago

#### 4. Proceder al pago
- Haz clic en "Continuar" para ir a la página de pago
- El total del carrito se carga automáticamente
- Se genera el QR de Mercado Pago

#### 5. Escanear QR
- Escanea el QR con la app de Mercado Pago
- Completa el pago

### Uso Directo del QR (Modo Simple)

Si prefieres usar solo la funcionalidad de QR sin la tienda:

```
http://localhost/php_mp/MPQR.html
```

- Ingresa el monto manualmente
- Genera el QR
- Escanea y paga

## 📁 Estructura del proyecto

```
php_mp/
├── index.html              # Interfaz principal de la tienda
├── MPQR.html               # Página de pago con QR
├── style.css               # Estilos de la aplicación
├── back.php                # Backend para generar QR
├── config.php              # Configuración de credenciales
├── create_store.php        # Script para crear store (temporal)
├── create_pos.php          # Script para crear POS (temporal)
├── test_payment_methods.php # Script de diagnóstico (temporal)
├── test_integration.html   # Página de pruebas de integración
├── composer.json           # Dependencias de Composer
├── composer.lock           # Versiones exactas de dependencias
├── .gitignore              # Archivos ignorados por Git
├── README.md               # Este archivo
└── vendor/                 # Dependencias de Composer (ignorado por Git)
```

## 🔍 Diagnóstico y solución de problemas

### Error: "Access Token no configurado"
- Edita `config.php` y agrega tu Access Token

### Error: "Point of sale not found"
- Ejecuta `create_pos.php` para crear el POS
- Verifica que el `external_pos_id` coincida

### Error: "External store id does not refer any store"
- Ejecuta `create_store.php` para crear la store
- Verifica que el `external_store_id` coincida

### Error: "Collector and Sponsor must be both from the same site"
- El campo `sponsor` se eliminó automáticamente del código
- No es necesario para integraciones estándar

### Error: "Api error. Check response for details"
- Verifica que las credenciales sean correctas
- Asegúrate de que la cuenta tenga QR Dinámico habilitado

### Verificar métodos de pago disponibles
```
http://localhost/php_mp/test_payment_methods.php
```

### Probar la integración completa
```
http://localhost/php_mp/test_integration.html
```

Esta página te permite:
- Verificar que localStorage funcione correctamente
- Probar la conexión con el backend
- Simular un carrito de compras
- Navegar entre las diferentes páginas
- Ver el estado actual del carrito

## 🔒 Seguridad

- **Nunca subas `config.php` a repositorios públicos**
- Usa variables de entorno en producción
- Mantén las credenciales seguras
- El archivo `.gitignore` ya excluye archivos sensibles

## 🌐 Producción

### Cambios necesarios para producción:

1. **Cambiar credenciales**: Usa Access Token de producción
2. **Configurar webhooks**: Agrega URL de notificaciones en `config.php`
3. **HTTPS**: Usa certificado SSL
4. **Variables de entorno**: Considera usar `.env` para credenciales

### Ejemplo de configuración para producción:
```php
define('MP_ENVIRONMENT', 'production');
define('MP_NOTIFICATION_URL', 'https://tu-dominio.com/webhook.php');
```

## 📚 Referencias

- [Documentación oficial QR Dinámico](https://www.mercadopago.com.ar/developers/es/reference/qr-dynamic/_instore_orders_qr_seller_collectors_user_id_pos_external_pos_id_qrs/post)
- [SDK de Mercado Pago PHP](https://github.com/mercadopago/sdk-php)
- [API de Stores](https://www.mercadopago.com.ar/developers/es/reference/stores/_users_user_id_stores/post)
- [API de POS](https://www.mercadopago.com.ar/developers/es/reference/pos/_pos/post)

## 🆘 Soporte

Para problemas con QR Dinámico:
- [Documentación oficial](https://www.mercadopago.com.ar/developers/es/docs/qr-code)
- [Soporte Mercado Pago](https://www.mercadopago.com.ar/developers/support)

## 📄 Licencia

Este proyecto es de uso libre para fines educativos y comerciales.

## 🎉 ¡Listo!

Tu integración de QR Dinámico de Mercado Pago está completa y lista para usar. ¡Felicitaciones! 🚀 
