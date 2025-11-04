<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/config.php';

try {
    $accessToken = MP_ACCESS_TOKEN;
    $userId = MP_USER_ID;
    
    echo "=== CREAR STORE Y LUEGO POS ===\n";
    echo "Access Token: " . substr($accessToken, 0, 20) . "...\n";
    echo "User ID: $userId\n\n";
    
    // PASO 1: Crear la Store usando la estructura exacta del curl
    echo "PASO 1: Creando Store...\n";
    
    $storeData = [
        'name' => 'Totem_Murialdo',
        'external_id' => 'SUC002',
        'location' => [
            'street_number' => '945',
            'street_name' => 'Jose Hernandez',
            'city_name' => 'Tres de febrero',
            'state_name' => 'Buenos Aires',
            'latitude' => -34.586412,
            'longitude' => -58.574368,
            'reference' => 'Adentro del colegio Murialdo'
        ]
    ];
    
    echo "Datos para crear Store:\n";
    echo json_encode($storeData, JSON_PRETTY_PRINT) . "\n\n";
    
    $storeUrl = "https://api.mercadopago.com/users/$userId/stores";
    
    $ch1 = curl_init();
    curl_setopt($ch1, CURLOPT_URL, $storeUrl);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($storeData));
    curl_setopt($ch1, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $storeResponse = curl_exec($ch1);
    $storeHttpCode = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
    $storeCurlError = curl_error($ch1);
    curl_close($ch1);
    
    echo "Store HTTP Code: $storeHttpCode\n";
    echo "Store cURL Error: " . ($storeCurlError ?: 'Ninguno') . "\n";
    echo "Store Response:\n";
    echo $storeResponse . "\n\n";
    
    $storeResponseData = json_decode($storeResponse, true);
    
    if ($storeHttpCode === 201 && isset($storeResponseData['id'])) {
        echo "✅ Store creada exitosamente!\n";
        echo "Store ID: " . $storeResponseData['id'] . "\n";
        echo "External ID: " . $storeResponseData['external_id'] . "\n\n";
        
        $storeId = $storeResponseData['id'];
        
        // PASO 2: Crear el POS usando el store_id real
        echo "PASO 2: Creando POS con Store ID real...\n";
        
        $posData = [
            'name' => 'First POS',
            'fixed_amount' => true,
            'store_id' => $storeId, // Usar el ID real de la store creada
            'external_store_id' => 'SUC001',
            'external_id' => 'SUC001POS001',
            'category' => 621102
        ];
        
        echo "Datos para crear POS:\n";
        echo json_encode($posData, JSON_PRETTY_PRINT) . "\n\n";
        
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, 'https://api.mercadopago.com/pos');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($posData));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $posResponse = curl_exec($ch2);
        $posHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $posCurlError = curl_error($ch2);
        curl_close($ch2);
        
        echo "POS HTTP Code: $posHttpCode\n";
        echo "POS cURL Error: " . ($posCurlError ?: 'Ninguno') . "\n";
        echo "POS Response:\n";
        echo $posResponse . "\n\n";
        
        $posResponseData = json_decode($posResponse, true);
        
        if ($posHttpCode === 201 && isset($posResponseData['id'])) {
            echo "✅ POS creado exitosamente!\n";
            echo "POS ID: " . $posResponseData['id'] . "\n";
            echo "External POS ID: " . $posResponseData['external_id'] . "\n";
            echo "QR Template ID: " . ($posResponseData['qr']['template_id'] ?? 'No disponible') . "\n\n";
            
            echo "🎉 CONFIGURACIÓN COMPLETA! 🎉\n";
            echo "Ya puedes usar back.php para generar códigos QR\n";
            
        } else {
            echo "❌ Error creando POS\n";
            if ($posResponseData) {
                echo "Detalles: " . json_encode($posResponseData, JSON_PRETTY_PRINT) . "\n";
            }
        }
        
    } else {
        echo "❌ Error creando Store\n";
        if ($storeResponseData) {
            echo "Detalles: " . json_encode($storeResponseData, JSON_PRETTY_PRINT) . "\n";
        }
        
        // Si la store ya existe, intentar actualizarla o usar la existente
        if (isset($storeResponseData['error']) && strpos($storeResponseData['message'], 'already exists') !== false) {
            echo "\n⚠️ Store ya existe. Intentando listar y actualizar...\n";
            
            $listUrl = "https://api.mercadopago.com/users/$userId/stores";
            
            $ch3 = curl_init();
            curl_setopt($ch3, CURLOPT_URL, $listUrl);
            curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch3, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken
            ]);
            
            $listResponse = curl_exec($ch3);
            $listHttpCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
            curl_close($ch3);
            
            echo "List Stores HTTP Code: $listHttpCode\n";
            echo "Stores existentes:\n";
            echo $listResponse . "\n\n";
            
            if ($listHttpCode === 200) {
                $listData = json_decode($listResponse, true);
                
                if (isset($listData['results']) && !empty($listData['results'])) {
                    // Buscar store por external_id o usar la primera
                    $targetStore = null;
                    foreach ($listData['results'] as $store) {
                        if ($store['external_id'] === $storeData['external_id']) {
                            $targetStore = $store;
                            break;
                        }
                    }
                    
                    if (!$targetStore) {
                        $targetStore = $listData['results'][0]; // Usar la primera store disponible
                    }
                    
                    $existingStoreId = $targetStore['id'];
                    echo "🔄 Intentando actualizar store ID: $existingStoreId\n";
                    
                    // PASO 1.5: Actualizar Store existente con PUT
                    $updateStoreData = [
                        'name' => 'Totem_Murialdo',
                        'external_id' => 'SUC001',
                        'location' => [
                            'street_number' => '945',
                            'street_name' => 'Jose Hernandez',
                            'city_name' => 'Tres de febrero',
                            'state_name' => 'Buenos Aires',
                            'latitude' => -34.586412,
                            'longitude' => -58.574368,
                            'reference' => 'Adentro del colegio Murialdo'
                        ]
                    ];
                    
                    echo "Datos para actualizar Store:\n";
                    echo json_encode($updateStoreData, JSON_PRETTY_PRINT) . "\n\n";
                    
                    $updateUrl = "https://api.mercadopago.com/users/$userId/stores/$existingStoreId";
                    
                    $ch4 = curl_init();
                    curl_setopt($ch4, CURLOPT_URL, $updateUrl);
                    curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch4, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch4, CURLOPT_POSTFIELDS, json_encode($updateStoreData));
                    curl_setopt($ch4, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $accessToken
                    ]);
                    
                    $updateResponse = curl_exec($ch4);
                    $updateHttpCode = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
                    $updateCurlError = curl_error($ch4);
                    curl_close($ch4);
                    
                    echo "Update Store HTTP Code: $updateHttpCode\n";
                    echo "Update Store cURL Error: " . ($updateCurlError ?: 'Ninguno') . "\n";
                    echo "Update Store Response:\n";
                    echo $updateResponse . "\n\n";
                    
                    $updateResponseData = json_decode($updateResponse, true);
                    
                    if ($updateHttpCode === 200 && isset($updateResponseData['id'])) {
                        echo "✅ Store actualizada exitosamente!\n";
                        echo "Store ID: " . $updateResponseData['id'] . "\n";
                        echo "External ID: " . $updateResponseData['external_id'] . "\n\n";
                        
                        $storeId = $updateResponseData['id'];
                        
                        // Continuar con la creación del POS usando la store actualizada
                        echo "PASO 2: Creando POS con Store actualizada...\n";
                        
                        $posData = [
                            'name' => 'Totem POS',
                            'fixed_amount' => true,
                            'store_id' => $storeId,
                            'external_store_id' => 'SUC002',
                            'external_id' => 'SUC002POS001',
                            'category' => 621102
                        ];
                        
                        echo "Datos para crear POS:\n";
                        echo json_encode($posData, JSON_PRETTY_PRINT) . "\n\n";
                        
                        $ch5 = curl_init();
                        curl_setopt($ch5, CURLOPT_URL, 'https://api.mercadopago.com/pos');
                        curl_setopt($ch5, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch5, CURLOPT_POST, true);
                        curl_setopt($ch5, CURLOPT_POSTFIELDS, json_encode($posData));
                        curl_setopt($ch5, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $accessToken
                        ]);
                        
                        $posResponse = curl_exec($ch5);
                        $posHttpCode = curl_getinfo($ch5, CURLINFO_HTTP_CODE);
                        $posCurlError = curl_error($ch5);
                        curl_close($ch5);
                        
                        echo "POS HTTP Code: $posHttpCode\n";
                        echo "POS cURL Error: " . ($posCurlError ?: 'Ninguno') . "\n";
                        echo "POS Response:\n";
                        echo $posResponse . "\n\n";
                        
                        $posResponseData = json_decode($posResponse, true);
                        
                        if ($posHttpCode === 201 && isset($posResponseData['id'])) {
                            echo "✅ POS creado exitosamente con store actualizada!\n";
                            echo "POS ID: " . $posResponseData['id'] . "\n";
                            echo "External POS ID: " . $posResponseData['external_id'] . "\n";
                            echo "QR Template ID: " . ($posResponseData['qr']['template_id'] ?? 'No disponible') . "\n\n";
                            
                            echo "🎉 CONFIGURACIÓN COMPLETA! 🎉\n";
                            echo "Store actualizada y POS creado exitosamente\n";
                            echo "Ya puedes usar back.php para generar códigos QR\n";
                            
                        } else {
                            echo "❌ Error creando POS con store actualizada\n";
                            if ($posResponseData) {
                                echo "Detalles: " . json_encode($posResponseData, JSON_PRETTY_PRINT) . "\n";
                            }
                        }
                        
                    } else {
                        echo "❌ Error actualizando Store\n";
                        if ($updateResponseData) {
                            echo "Detalles: " . json_encode($updateResponseData, JSON_PRETTY_PRINT) . "\n";
                        }
                        
                        // Si no se puede actualizar, usar la store existente tal como está
                        echo "\n⚠️ Usando store existente sin actualizar...\n";
                        $storeId = $existingStoreId;
                        
                        // Continuar con POS usando store existente
                        echo "PASO 2: Creando POS con Store existente...\n";
                        
                        $posData = [
                            'name' => 'Totem POS',
                            'fixed_amount' => true,
                            'store_id' => $storeId,
                            'external_store_id' => $targetStore['external_id'],
                            'external_id' => $targetStore['external_id'] . 'POS001',
                            'category' => 621102
                        ];
                        
                        echo "Datos para crear POS:\n";
                        echo json_encode($posData, JSON_PRETTY_PRINT) . "\n\n";
                        
                        $ch6 = curl_init();
                        curl_setopt($ch6, CURLOPT_URL, 'https://api.mercadopago.com/pos');
                        curl_setopt($ch6, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch6, CURLOPT_POST, true);
                        curl_setopt($ch6, CURLOPT_POSTFIELDS, json_encode($posData));
                        curl_setopt($ch6, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $accessToken
                        ]);
                        
                        $posResponse = curl_exec($ch6);
                        $posHttpCode = curl_getinfo($ch6, CURLINFO_HTTP_CODE);
                        curl_close($ch6);
                        
                        $posResponseData = json_decode($posResponse, true);
                        
                        if ($posHttpCode === 201 && isset($posResponseData['id'])) {
                            echo "✅ POS creado exitosamente con store existente!\n";
                            echo "POS ID: " . $posResponseData['id'] . "\n";
                            echo "External POS ID: " . $posResponseData['external_id'] . "\n";
                            
                            echo "🎉 CONFIGURACIÓN COMPLETA! 🎉\n";
                            echo "POS creado usando store existente\n";
                        } else {
                            echo "❌ Error creando POS con store existente\n";
                            if ($posResponseData) {
                                echo "Detalles: " . json_encode($posResponseData, JSON_PRETTY_PRINT) . "\n";
                            }
                        }
                    }
                } else {
                    echo "❌ No se encontraron stores existentes\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Excepción: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
?>
