<?php
// ========================================
// CONFIGURACIÓN MERCADO PAGO - FLUJO CHECKOUTPRO ÚNICAMENTE
// ========================================

// Credenciales específicas para CheckoutPro
define('MP_ACCESS_TOKEN', 'APP_USR-4512908108166614-102219-65ed4fc78fc6258a3e3c2c23d378c29f-2477837851');
define('MP_PUBLIC_KEY', 'APP_USR-4e020fd1-2b51-484d-8009-63ce5c861b75');
define('MP_CLIENT_ID', '4512908108166614');
define('MP_CLIENT_SECRET', 'LpiKtbUFdecMUCakFW1Gps9ABCRMHVqL');

// User ID específico para CheckoutPro
define('MP_USER_ID', '2477837851');

// Configuración del entorno
define('MP_ENVIRONMENT', 'production');
define('MP_NOTIFICATION_URL', 'https://ilm2025.webhop.net/Totem_Murialdo/backend-checkoutpro/api/webhook_checkoutpro.php');

// Función para obtener credenciales CheckoutPro
function get_mp_credentials($legacy_param = false) {
    // Nota: $legacy_param se mantiene por compatibilidad pero se ignora
    // Este backend SIEMPRE devuelve credenciales CheckoutPro
    return [
        'access_token' => MP_ACCESS_TOKEN,
        'public_key' => MP_PUBLIC_KEY,
        'client_id' => MP_CLIENT_ID,
        'client_secret' => MP_CLIENT_SECRET
    ];
}

// Configuración de la aplicación
define('APP_NAME', 'CheckoutPro');
define('APP_VERSION', '1.0.0');
?> 