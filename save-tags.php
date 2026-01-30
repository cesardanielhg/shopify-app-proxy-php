<?php
// save-tags.php
// Subir a Railway

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Función de log
function logToFile($message, $data = null) {
    $log = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $log .= ' | ' . print_r($data, true);
    }
    $log .= "\n";
    file_put_contents('debug.log', $log, FILE_APPEND);
}

logToFile('========== NUEVO REQUEST ==========');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logToFile('✅ Método POST recibido');
    
    $rawInput = file_get_contents('php://input');
    logToFile('📥 Raw input', $rawInput);
    
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        logToFile('❌ Error decodificando JSON');
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit;
    }
    
    logToFile('✅ JSON decodificado', $data);
    
    $customerId = $data['customer_id'] ?? null;
    $newTags = $data['tags'] ?? [];
    $shop = $data['shop'] ?? null;
    
    logToFile('📊 Datos extraídos', [
        'customer_id' => $customerId,
        'shop' => $shop,
        'tags_count' => count($newTags),
        'tags' => $newTags
    ]);
    
    if (!$customerId || !$shop || empty($newTags)) {
        logToFile('❌ Faltan datos requeridos');
        echo json_encode([
            'success' => false, 
            'error' => 'Missing required fields',
            'received' => [
                'customer_id' => $customerId ? 'OK' : 'MISSING',
                'shop' => $shop ? 'OK' : 'MISSING',
                'tags' => !empty($newTags) ? 'OK' : 'MISSING'
            ]
        ]);
        exit;
    }
    
    // ============================================
    // CONFIGURACIÓN DE SHOPIFY - ¡MODIFICA ESTO!
    // ============================================
    $shopifyToken = getenv('SHOPIFY_ADMIN_TOKEN');
    $apiVersion = '2024-01';
    
    logToFile('🔑 Configuración Shopify', [
        'shop' => $shop,
        'api_version' => $apiVersion,
        'token_length' => strlen($shopifyToken)
    ]);
    
    // URL para obtener cliente
    $getUrl = "https://{$shop}/admin/api/{$apiVersion}/customers/{$customerId}.json";
    logToFile('📡 GET URL', $getUrl);
    
    // Inicializar cURL para GET
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $getUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: {$shopifyToken}",
            "Content-Type: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    logToFile('📥 Obteniendo datos del cliente...');
    $getResponse = curl_exec($ch);
    $getHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    logToFile('📊 Respuesta GET', [
        'http_code' => $getHttpCode,
        'curl_error' => $curlError,
        'response_length' => strlen($getResponse)
    ]);
    
    if ($curlError) {
        logToFile('❌ cURL Error', $curlError);
        curl_close($ch);
        echo json_encode([
            'success' => false, 
            'error' => 'Connection error to Shopify',
            'details' => $curlError
        ]);
        exit;
    }
    
    if ($getHttpCode !== 200) {
        logToFile('❌ HTTP Error en GET', [
            'code' => $getHttpCode,
            'response' => $getResponse
        ]);
        curl_close($ch);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to fetch customer from Shopify',
            'http_code' => $getHttpCode,
            'shopify_response' => $getResponse,
            'url_used' => $getUrl
        ]);
        exit;
    }
    
    logToFile('📄 Respuesta GET completa', $getResponse);
    
    $customerData = json_decode($getResponse, true);
    
    if (!$customerData || !isset($customerData['customer'])) {
        logToFile('❌ Estructura de respuesta inválida');
        curl_close($ch);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid customer data structure',
            'response' => $customerData
        ]);
        exit;
    }
    
    $customer = $customerData['customer'];
    logToFile('✅ Cliente obtenido', [
        'id' => $customer['id'],
        'email' => $customer['email'],
        'current_tags' => $customer['tags']
    ]);
    
    // Obtener tags actuales
    $currentTags = $customer['tags'] ? explode(', ', $customer['tags']) : [];
    logToFile('🏷️ Tags actuales (array)', $currentTags);
    
    // Combinar tags (agregando los nuevos)
    $allTags = array_merge($currentTags, $newTags);
    $allTags = array_unique($allTags);
    $tagsString = implode(', ', $allTags);
    
    logToFile('🔄 Tags finales', [
        'old_count' => count($currentTags),
        'new_count' => count($newTags),
        'total_count' => count($allTags),
        'tags_string' => $tagsString
    ]);
    
    // Preparar actualización
    $updateData = [
        'customer' => [
            'id' => $customerId,
            'tags' => $tagsString
        ]
    ];
    
    $updateJson = json_encode($updateData);
    logToFile('📤 Datos para UPDATE', $updateJson);
    
    // URL para actualizar
    $putUrl = "https://{$shop}/admin/api/{$apiVersion}/customers/{$customerId}.json";
    logToFile('📡 PUT URL', $putUrl);
    
    // Configurar cURL para PUT
    curl_setopt_array($ch, [
        CURLOPT_URL => $putUrl,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $updateJson,
        CURLOPT_HTTPHEADER => [
            "X-Shopify-Access-Token: {$shopifyToken}",
            "Content-Type: application/json"
        ]
    ]);
    
    logToFile('🔄 Actualizando cliente...');
    $putResponse = curl_exec($ch);
    $putHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $putCurlError = curl_error($ch);
    
    curl_close($ch);
    
    logToFile('📊 Respuesta PUT', [
        'http_code' => $putHttpCode,
        'curl_error' => $putCurlError,
        'response' => $putResponse
    ]);
    
    if ($putHttpCode === 200) {
        logToFile('✅ Cliente actualizado exitosamente');
        echo json_encode([
            'success' => true, 
            'message' => 'Customer updated successfully',
            'tags_added' => $newTags,
            'total_tags' => count($allTags)
        ]);
    } else {
        logToFile('❌ Error al actualizar');
        echo json_encode([
            'success' => false, 
            'error' => '❌Failed to update customer in Shopify',
            'http_code' => $putHttpCode,
            'shopify_response' => $putResponse
        ]);
    }
    
} else {
    logToFile('❌ Método no permitido', $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
}
?>