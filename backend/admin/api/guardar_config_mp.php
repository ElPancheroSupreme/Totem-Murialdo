<?php
// backend/admin/api/guardar_config_mp.php
header('Content-Type: application/json');

$configFile = __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

// Lista de claves y defines
$defines = [
    'MP_ACCESS_TOKEN',
    'MP_PUBLIC_KEY',
    'MP_CLIENT_ID',
    'MP_CLIENT_SECRET',
    'MP_USER_ID',
    'MP_EXTERNAL_POS_ID',
    'MP_SPONSOR_ID',
    'MP_ENVIRONMENT',
    'MP_NOTIFICATION_URL',
    'APP_NAME',
    'APP_VERSION'
];

// Leer el archivo actual
$lines = file($configFile);
foreach ($lines as &$line) {
    foreach ($defines as $key) {
        if (preg_match("/define\(['\"]" . preg_quote($key, '/') . "['\"],/", $line)) {
            $value = isset($data[$key]) ? $data[$key] : '';
            // Si es numérico, no poner comillas
            if (is_numeric($value) && $key !== 'MP_ACCESS_TOKEN' && $key !== 'MP_PUBLIC_KEY' && $key !== 'MP_CLIENT_SECRET' && $key !== 'MP_ENVIRONMENT' && $key !== 'MP_NOTIFICATION_URL' && $key !== 'APP_NAME' && $key !== 'APP_VERSION') {
                $line = "define('$key', $value);\n";
            } else {
                $line = "define('$key', '" . addslashes($value) . "');\n";
            }
        }
    }
}

// Guardar el archivo
file_put_contents($configFile, implode('', $lines));
echo json_encode(['success' => true]);
