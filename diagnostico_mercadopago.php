<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>Diagnóstico de Conectividad MercadoPago</h1>";

// Activar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<style>
.success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
.error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
.warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 10px 0; }
.info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
h3 { margin-top: 30px; }
</style>";

// Función para mostrar mensajes formateados
function show_message($type, $message) {
    echo "<div class='{$type}'>{$message}</div>";
}

// Paso 1: Verificar la existencia del vendor autoload
echo "<h3>1. Verificando vendor/autoload.php</h3>";
$vendor_path = __DIR__ . '/vendor/autoload.php';

if (file_exists($vendor_path)) {
    show_message('success', "✅ El archivo vendor/autoload.php existe en la ruta: {$vendor_path}");
} else {
    show_message('error', "❌ El archivo vendor/autoload.php NO existe en la ruta: {$vendor_path}");
}

// Paso 2: Verificar la existencia de los directorios de backend
echo "<h3>2. Verificando estructura de directorios</h3>";
$backend_paths = [
    'backend' => __DIR__ . '/backend',
    'backend-qr' => __DIR__ . '/backend-qr',
    'backend-checkoutpro' => __DIR__ . '/backend-checkoutpro',
    'backend-shared' => __DIR__ . '/backend-shared',
];

foreach ($backend_paths as $name => $path) {
    if (is_dir($path)) {
        show_message('success', "✅ Directorio {$name} existe en: {$path}");
    } else {
        show_message('error', "❌ Directorio {$name} NO existe en: {$path}");
    }
}

// Paso 3: Verificar archivos de configuración
echo "<h3>3. Verificando archivos de configuración</h3>";
$config_files = [
    'backend/config/config.php' => __DIR__ . '/backend/config/config.php',
    'backend-qr/config/config.php' => __DIR__ . '/backend-qr/config/config.php',
    'backend-checkoutpro/config/config.php' => __DIR__ . '/backend-checkoutpro/config/config.php',
];

foreach ($config_files as $name => $path) {
    if (file_exists($path)) {
        show_message('success', "✅ Archivo de configuración {$name} existe");
        
        // Intentar cargar el archivo para ver si tiene errores de sintaxis
        try {
            include $path;
            show_message('info', "ℹ️ Archivo {$name} cargado sin errores de sintaxis");
            
            // Verificar si las constantes están definidas
            $constants_to_check = ['MP_ACCESS_TOKEN', 'MP_PUBLIC_KEY', 'MP_USER_ID', 'MP_EXTERNAL_POS_ID'];
            foreach ($constants_to_check as $const) {
                if (defined($const)) {
                    $value = constant($const);
                    $masked_value = substr($value, 0, 10) . '...';
                    show_message('info', "ℹ️ Constante {$const} está definida con valor: {$masked_value}");
                } else {
                    show_message('warning', "⚠️ Constante {$const} NO está definida en {$name}");
                }
            }
        } catch (Throwable $e) {
            show_message('error', "❌ Error al cargar {$name}: " . $e->getMessage());
        }
    } else {
        show_message('error', "❌ Archivo de configuración {$name} NO existe");
    }
}

// Paso 4: Verificar conectividad con MercadoPago API
echo "<h3>4. Verificando conectividad con MercadoPago API</h3>";

$mp_api_endpoints = [
    'API Status' => 'https://api.mercadopago.com/v1/payment_methods',
    'QR API' => 'https://api.mercadopago.com/instore/orders/qr/seller/collectors',
];

// Cargar configuración específica QR
if (file_exists(__DIR__ . '/backend-qr/config/config.php')) {
    include_once __DIR__ . '/backend-qr/config/config.php';
}

if (!defined('MP_ACCESS_TOKEN') || empty(MP_ACCESS_TOKEN)) {
    show_message('error', "❌ No se pudo obtener el ACCESS_TOKEN de MercadoPago. Verifica la configuración.");
} else {
    $access_token = MP_ACCESS_TOKEN;
    $masked_token = substr($access_token, 0, 10) . '...' . substr($access_token, -5);
    show_message('info', "ℹ️ Usando Access Token: {$masked_token}");
    
    foreach ($mp_api_endpoints as $name => $url) {
        echo "<h4>Probando {$name}: {$url}</h4>";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        if ($curl_error) {
            show_message('error', "❌ Error de conectividad: {$curl_error}");
        } else {
            if ($http_code >= 200 && $http_code < 300) {
                show_message('success', "✅ Conexión exitosa a {$name} (HTTP {$http_code})");
                
                // Mostrar parte de la respuesta si es JSON válido
                $json_response = json_decode($response, true);
                if ($json_response) {
                    echo "<pre>" . json_encode($json_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</pre>";
                } else {
                    // Mostrar los primeros 500 caracteres de la respuesta
                    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . (strlen($response) > 500 ? '...' : '') . "</pre>";
                }
            } else {
                show_message('error', "❌ Error al conectar con {$name}: HTTP {$http_code}");
                echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . (strlen($response) > 500 ? '...' : '') . "</pre>";
            }
        }
        
        curl_close($ch);
    }
}

// Paso 5: Probar creación de QR
if (defined('MP_ACCESS_TOKEN') && defined('MP_USER_ID') && defined('MP_EXTERNAL_POS_ID')) {
    echo "<h3>5. Probando creación de QR</h3>";
    
    $user_id = MP_USER_ID;
    $external_pos_id = MP_EXTERNAL_POS_ID;
    $post_url = "https://api.mercadopago.com/instore/orders/qr/seller/collectors/{$user_id}/pos/{$external_pos_id}/qrs";
    
    show_message('info', "ℹ️ URL API QR: {$post_url}");
    show_message('info', "ℹ️ User ID: {$user_id}");
    show_message('info', "ℹ️ External POS ID: {$external_pos_id}");
    
    $post_data = [
        'external_reference' => 'test_' . uniqid(),
        'title' => 'Test QR Diagnóstico',
        'description' => 'Prueba de diagnóstico',
        'total_amount' => 100,
        'items' => [
            [
                'sku_number' => 'TEST001',
                'category' => 'services',
                'title' => 'Producto de prueba',
                'description' => 'Prueba de diagnóstico',
                'unit_price' => 100,
                'quantity' => 1,
                'unit_measure' => 'unit',
                'total_amount' => 100
            ]
        ]
    ];
    
    echo "<h4>Datos a enviar:</h4>";
    echo "<pre>" . json_encode($post_data, JSON_PRETTY_PRINT) . "</pre>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $post_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . MP_ACCESS_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $post_response = curl_exec($ch);
    $post_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $post_error = curl_error($ch);
    curl_close($ch);
    
    if ($post_error) {
        show_message('error', "❌ Error al conectar con API QR: {$post_error}");
    } else {
        if ($post_http_code >= 200 && $post_http_code < 300) {
            show_message('success', "✅ QR creado exitosamente (HTTP {$post_http_code})");
            
            $response_data = json_decode($post_response, true);
            if ($response_data && isset($response_data['qr_data'])) {
                show_message('success', "✅ QR data recibido correctamente");
                
                // Mostrar el código QR generado
                echo "<h4>Código QR generado:</h4>";
                echo "<img src='https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($response_data['qr_data']) . "' />";
                
                echo "<h4>Respuesta completa:</h4>";
                echo "<pre>" . json_encode($response_data, JSON_PRETTY_PRINT) . "</pre>";
            } else {
                show_message('warning', "⚠️ QR creado pero no se recibió qr_data en la respuesta");
                echo "<pre>" . json_encode(json_decode($post_response, true), JSON_PRETTY_PRINT) . "</pre>";
            }
        } else {
            show_message('error', "❌ Error al crear QR: HTTP {$post_http_code}");
            echo "<pre>" . htmlspecialchars($post_response) . "</pre>";
        }
    }
} else {
    show_message('warning', "⚠️ No se pudo probar la creación de QR porque faltan configuraciones necesarias");
}

// Información del sistema
echo "<h3>Información del sistema</h3>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "Script Filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'Unknown') . "\n";
echo "JSON Support: " . (function_exists('json_encode') ? 'Enabled' : 'Disabled') . "\n";
echo "cURL Support: " . (function_exists('curl_init') ? 'Enabled' : 'Disabled') . "\n";
echo "</pre>";

echo "<h3>Extensiones PHP cargadas</h3>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";
?>