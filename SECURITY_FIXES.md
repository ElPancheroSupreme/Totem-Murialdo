# PLAN DE REMEDIACIÓN DE SEGURIDAD - TOTEM MURIALDO

## 🔴 VULNERABILIDADES CRÍTICAS IDENTIFICADAS

### 1. CREDENCIALES HARDCODEADAS
**Archivos afectados:**
- `backend/config/config.php`
- `backend/config/db_connection.php`
- `backend/config/pdo_connection.php`

**Problemas:**
- Database: `DB_USER='root'`, `DB_PASS=''`
- Tokens de Mercado Pago expuestos
- Credenciales en texto plano

**Solución INMEDIATA:**
```php
// Crear archivo .env (NO SUBIR A GIT)
DB_HOST=localhost
DB_NAME=totem_bd
DB_USER=totem_user
DB_PASS=password_seguro_aleatorio
MP_ACCESS_TOKEN=tu_token_real
MP_PUBLIC_KEY=tu_key_real
```

### 2. INYECCIÓN SQL
**Archivos afectados:**
- `backend/api/api_kiosco.php` (líneas 144, 174, 185)
- `backend/admin/api/api_productos.php` (líneas 97, 125, 138)
- `backend/api/usuarios/agregar.php`

**Problemas:**
- Uso inconsistente de prepared statements
- Validación insuficiente de entrada
- Concatenación directa de SQL

**Solución INMEDIATA:**
```php
// ANTES (VULNERABLE)
$stmt = $pdo->query("SELECT * FROM productos WHERE id = " . $_GET['id']);

// DESPUÉS (SEGURO)
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$_GET['id']]);
```

### 3. SUBIDA DE ARCHIVOS INSEGURA
**Archivos afectados:**
- `backend/admin/api/upload_image.php`
- `backend/admin/api/api_productos.php`
- `backend/api/api_kiosco.php`

**Problemas:**
- Solo validación MIME type (fácil de eludir)
- Sin verificación de contenido real
- Archivos subidos en webroot público
- Sin límites de tamaño adecuados

**Solución INMEDIATA:**
```php
// Validación robusta de archivos
function validarImagenSegura($file) {
    // 1. Verificar extensión permitida
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        throw new Exception('Extensión no permitida');
    }
    
    // 2. Verificar MIME type
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedMimes)) {
        throw new Exception('Tipo MIME no permitido');
    }
    
    // 3. Verificar contenido real con getimagesize
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('Archivo no es una imagen válida');
    }
    
    // 4. Verificar tamaño
    if ($file['size'] > 2 * 1024 * 1024) { // 2MB max
        throw new Exception('Archivo demasiado grande');
    }
    
    return true;
}

// Mover archivos FUERA del webroot
$uploadDir = __DIR__ . '/../../../uploads/images/'; // Fuera de htdocs
```

### 4. CORS Y HEADERS DE SEGURIDAD
**Archivos afectados:**
- Todos los endpoints API

**Problemas:**
- `Access-Control-Allow-Origin: *` (permite cualquier origen)
- Sin headers de seguridad (CSP, HSTS, etc.)
- Sin protección CSRF

**Solución INMEDIATA:**
```php
// En cada endpoint API
header('Access-Control-Allow-Origin: https://tu-dominio.com'); // NO usar *
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Implementar tokens CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
```

### 5. EXPOSICIÓN DE INFORMACIÓN
**Archivos afectados:**
- `backend/api/login.php`
- `backend/api/back.php`
- Múltiples archivos con debug habilitado

**Problemas:**
- `display_errors = 1` en producción
- Información técnica en mensajes de error
- Logs con datos sensibles

**Solución INMEDIATA:**
```php
// Configuración segura para producción
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/secure/logs/php_errors.log');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Manejo seguro de errores
function handleSecureError($message, $logDetails = '') {
    // Log detallado para desarrollo
    error_log("SECURITY ERROR: $logDetails");
    
    // Respuesta genérica para usuario
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    exit;
}
```

### 6. AUTENTICACIÓN DÉBIL
**Archivos afectados:**
- `backend/admin/api/pin_admin.php`
- `backend/config/auth.php`
- `backend/api/login.php`

**Problemas:**
- PIN admin sin hash
- Sesiones sin configuración segura
- Sin rate limiting

**Solución INMEDIATA:**
```php
// Hash seguro para PINs
function hashPin($pin) {
    return password_hash($pin, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

// Configuración segura de sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Rate limiting básico
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
    $key = "rate_limit_$identifier";
    $attempts = $_SESSION[$key] ?? 0;
    
    if ($attempts >= $maxAttempts) {
        $lastAttempt = $_SESSION[$key . '_time'] ?? 0;
        if (time() - $lastAttempt < $timeWindow) {
            throw new Exception('Demasiados intentos. Intenta más tarde.');
        } else {
            $_SESSION[$key] = 0; // Reset counter
        }
    }
    
    $_SESSION[$key] = $attempts + 1;
    $_SESSION[$key . '_time'] = time();
}
```

## 📋 CHECKLIST DE IMPLEMENTACIÓN

### PRIORIDAD 1 (CRÍTICO - Implementar HOY)
- [ ] Cambiar credenciales de base de datos
- [ ] Crear usuario de DB específico con permisos limitados
- [ ] Mover tokens de MP a variables de entorno
- [ ] Deshabilitar display_errors en producción
- [ ] Implementar validación robusta de archivos subidos

### PRIORIDAD 2 (ALTA - Esta semana)
- [ ] Revisar y corregir todas las consultas SQL
- [ ] Implementar headers de seguridad en todos los endpoints
- [ ] Configurar CORS específico (no usar *)
- [ ] Hash del PIN de administrador
- [ ] Configuración segura de sesiones

### PRIORIDAD 3 (MEDIA - Próximas 2 semanas)
- [ ] Implementar sistema de logs seguro
- [ ] Rate limiting en endpoints críticos
- [ ] Tokens CSRF en formularios
- [ ] Auditoría completa de permisos de archivos
- [ ] Backup y plan de recuperación

### PRIORIDAD 4 (BAJA - Próximo mes)
- [ ] Implementar Content Security Policy (CSP)
- [ ] Monitoreo de seguridad automatizado
- [ ] Pruebas de penetración
- [ ] Documentación de seguridad

## 🚨 ACCIONES INMEDIATAS REQUERIDAS

1. **CAMBIAR CREDENCIALES DE DB AHORA**
2. **DESHABILITAR DEBUG MODE EN PRODUCCIÓN**
3. **REVISAR LOGS POR POSIBLES COMPROMISOS**
4. **IMPLEMENTAR RESPALDOS INMEDIATOS**
5. **NOTIFICAR A USUARIOS DE POSIBLE EXPOSICIÓN**

## 📞 CONTACTO PARA SOPORTE
Si necesitas ayuda implementando estas correcciones, documenta cada paso y realiza pruebas en un entorno de desarrollo primero.
