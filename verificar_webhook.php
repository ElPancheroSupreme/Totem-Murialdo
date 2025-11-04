<?php
// Verificador de accesibilidad del webhook
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Verificador de Webhook</h2>";

$webhook_urls = [
    'https://ilm2025.webhop.net/Totem_Murialdo/backend/api/webhook.php',
    'https://ilm2025.webhop.net/Totem_Murialdo/backend/api/webhook.php?source=checkoutpro',
];

foreach ($webhook_urls as $url) {
    echo "<h3>Probando: $url</h3>";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // Solo HEAD request
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo "❌ Error cURL: $curl_error<br>";
    } else {
        echo "Código HTTP: <strong style='color: " . ($http_code == 200 ? 'green' : 'red') . "'>$http_code</strong><br>";
        if ($http_code == 200) {
            echo "✅ Webhook accesible<br>";
        } else {
            echo "❌ Webhook no accesible<br>";
        }
    }
    echo "<br>";
}

echo "<h3>Explorando directorio backend/api/</h3>";
$api_dir = __DIR__ . '/backend/api/';
if (is_dir($api_dir)) {
    echo "✅ Directorio backend/api existe<br>";
    $files = scandir($api_dir);
    echo "Archivos encontrados:<br>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "• $file<br>";
        }
    }
} else {
    echo "❌ Directorio backend/api NO existe<br>";
    echo "Directorio actual: " . __DIR__ . "<br>";
    echo "Contenido del directorio actual:<br>";
    $current_files = scandir(__DIR__);
    foreach ($current_files as $file) {
        if ($file != '.' && $file != '..') {
            echo "• $file" . (is_dir(__DIR__ . '/' . $file) ? ' (directorio)' : '') . "<br>";
        }
    }
}

echo "<h3>Buscando archivos de orden</h3>";
$orden_dirs = [
    __DIR__ . '/backend/ordenes_status/',
    '/var/www/html/Totem_Murialdo/backend/ordenes_status/',
    '/tmp/ordenes_status/'
];

foreach ($orden_dirs as $dir) {
    echo "<h4>$dir</h4>";
    if (is_dir($dir)) {
        echo "✅ Directorio existe<br>";
        $files = glob($dir . '*_pre.json');
        if (!empty($files)) {
            echo "Archivos _pre.json encontrados:<br>";
            foreach (array_slice($files, -5) as $file) { // Últimos 5
                $filename = basename($file);
                $time = date('Y-m-d H:i:s', filemtime($file));
                echo "• $filename - $time<br>";
            }
        } else {
            echo "❌ No se encontraron archivos _pre.json<br>";
        }
    } else {
        echo "❌ Directorio NO existe<br>";
    }
    echo "<br>";
}
?>