<?php
// Script para verificar y configurar directorios de imágenes en el servidor
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Configuración de Directorios de Imágenes</h1>\n";

$baseDir = __DIR__ . '/../../../frontend/assets/images';
$realBaseDir = realpath($baseDir);

echo "<h2>1. Directorio Base</h2>\n";
echo "Ruta relativa: $baseDir<br>\n";
echo "Ruta absoluta: " . ($realBaseDir ? $realBaseDir : 'NO EXISTE') . "<br>\n";
echo "Existe: " . (is_dir($baseDir) ? 'SÍ' : 'NO') . "<br>\n";
echo "Escribible: " . (is_writable($baseDir) ? 'SÍ' : 'NO') . "<br>\n";

if (is_dir($baseDir)) {
    echo "Permisos: " . substr(sprintf('%o', fileperms($baseDir)), -4) . "<br>\n";
}

echo "<h2>2. Intentar crear subdirectorios necesarios</h2>\n";

$subdirs = [
    'Buffet/Bebidas',
    'Buffet/Comida',
    'Buffet/Postres',
    'Kiosco/Bebidas',
    'Kiosco/Golosinas',
    'Kiosco/Snacks'
];

foreach ($subdirs as $subdir) {
    $fullPath = $baseDir . '/' . $subdir;
    echo "<h3>$subdir</h3>\n";
    
    if (is_dir($fullPath)) {
        echo "✅ Ya existe<br>\n";
        echo "Escribible: " . (is_writable($fullPath) ? 'SÍ' : 'NO') . "<br>\n";
    } else {
        echo "📁 Intentando crear... ";
        if (@mkdir($fullPath, 0755, true)) {
            echo "✅ CREADO<br>\n";
            echo "Escribible: " . (is_writable($fullPath) ? 'SÍ' : 'NO') . "<br>\n";
        } else {
            echo "❌ ERROR<br>\n";
            $error = error_get_last();
            echo "Error: " . ($error ? $error['message'] : 'Desconocido') . "<br>\n";
        }
    }
}

echo "<h2>3. Test de escritura</h2>\n";

$testFile = $baseDir . '/test_write.txt';
if (@file_put_contents($testFile, 'Test de escritura - ' . date('Y-m-d H:i:s'))) {
    echo "✅ Escritura de archivos: OK<br>\n";
    @unlink($testFile);
} else {
    echo "❌ Escritura de archivos: ERROR<br>\n";
}

echo "<h2>4. Información del servidor</h2>\n";
echo "Usuario PHP: " . get_current_user() . "<br>\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "<br>\n";
echo "Script actual: " . __FILE__ . "<br>\n";

if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $userInfo = posix_getpwuid(posix_geteuid());
    echo "Usuario sistema: " . $userInfo['name'] . "<br>\n";
}

echo "<h2>5. Configuración PHP</h2>\n";
echo "file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "<br>\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>\n";
echo "post_max_size: " . ini_get('post_max_size') . "<br>\n";

echo "\n<hr>\n";
echo "<p><strong>NOTA:</strong> Si los directorios no se pueden crear automáticamente, ";
echo "tendrás que crearlos manualmente en el servidor con los permisos correctos (755).</p>\n";
?>