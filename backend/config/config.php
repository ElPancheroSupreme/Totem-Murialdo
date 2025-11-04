<?php
// Configuración de Mercado Pago
// Reemplaza estos valores por tus credenciales reales

// Access Token de Mercado Pago
// Para pruebas: Usa un Access Token de cuenta de prueba con QR Dinámico habilitado
// Para producción: Usa el Access Token de tu cuenta real de vendedor
define('MP_ACCESS_TOKEN', 'APP_USR-4841458334764350-090119-69dbd5508dffb1b46300b7bdbb2a1629-2477837851');

// Public Key (opcional, para frontend si lo necesitas)
define('MP_PUBLIC_KEY', 'APP_USR-bdb5347e-3bb2-48d3-9db6-d2075ba02e2a');

// Client ID y Client Secret (para OAuth, webhooks y APIs avanzadas)
define('MP_CLIENT_ID', '4841458334764350');
define('MP_CLIENT_SECRET', 'xjAE8W18IO0VvZbCDK2zSgS0Dm43WlGG');

// Configuración para QR Dinámico
// User ID de tu cuenta de Mercado Pago (encuentra esto en tu panel de desarrollador)
define('MP_USER_ID', '2477837851');

// External POS ID (identificador personalizado para tu punto de venta)
define('MP_EXTERNAL_POS_ID', 'SUC001POS001');

// Sponsor ID (ID del patrocinador, puede variar según tu cuenta)
define('MP_SPONSOR_ID', '');

// Configuración adicional
define('MP_ENVIRONMENT', 'production'); // Cambiar a 'production' en producción
define('MP_NOTIFICATION_URL', 'https://ilm2025.webhop.net/Totem_Murialdo/backend/api/webhook.php');

// IMPORTANTE: Para desarrollo usa credenciales TEST, para producción usa APP_USR
// Verifica en: https://www.mercadopago.com.ar/developers/panel/app

// ========================================
// CONFIGURACIÓN ESPECÍFICA PARA CHECKOUTPRO
// ========================================
// Para CheckoutPro necesitas credenciales de TEST específicas
// Consigue estas credenciales en: https://www.mercadopago.com.ar/developers/panel/app

define('MP_CHECKOUTPRO_ACCESS_TOKEN', 'APP_USR-6644021912940284-092514-0557e9a0bd8032b990b16edb60938efd-2477837851');
define('MP_CHECKOUTPRO_PUBLIC_KEY', 'APP_USR-300221d2-c35c-462d-ad2b-cc42b3c21f17');
define('MP_CHECKOUTPRO_CLIENT_ID', '6644021912940284');
define('MP_CHECKOUTPRO_CLIENT_SECRET', 'lCAYoRkYHKjtw9Gbxp7Rh2hYXCJdqMvn');

// Para determinar qué credenciales usar
function get_mp_credentials($for_checkoutpro = false) {
    if ($for_checkoutpro) {
        return [
            'access_token' => MP_CHECKOUTPRO_ACCESS_TOKEN,
            'public_key' => MP_CHECKOUTPRO_PUBLIC_KEY,
            'client_id' => MP_CHECKOUTPRO_CLIENT_ID,
            'client_secret' => MP_CHECKOUTPRO_CLIENT_SECRET
        ];
    } else {
        return [
            'access_token' => MP_ACCESS_TOKEN,
            'public_key' => MP_PUBLIC_KEY,
            'client_id' => MP_CLIENT_ID,
            'client_secret' => MP_CLIENT_SECRET
        ];
    }
}

// Configuración de la aplicación
define('APP_NAME', 'QR Mercado Pago');
define('APP_VERSION', '1.0.0');
?> 