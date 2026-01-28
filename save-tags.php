<?php
// save-tags.php
// Subir a Railway

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Función de log
function logMessage($message, $data = null) {
    $log = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $log .= ' | Data: ' . json_encode($data);
    }
    error_log($log);
    echo json_encode(['log' => $log]) . "\n";
    flush();
}

logMessage('🚀 Script iniciado');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logMessage('✅ Método POST detectado');
    
    $rawInput = file_get_contents('php://input');
    logMessage('📥 Raw input recibido', $rawInput);
    
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        logMessage('❌ Error al decodificar JSON');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    
    logMessage('✅ JSON decodificado correctamente', $data);
    
    $customerId = $data['customer_id'] ?? null;
    $newTags = $data['tags'] ?? [];
    $shop = $data['shop'] ?? null;
    
    logMessage('👤 Customer ID', $customerId);
    logMessage('🏷️ Nuevos tags', $newTags);
    logMessage('🏪 Shop', $shop);
    
    if (!$customerId || !$shop || empty($newTags)) {
        logMessage('❌ Faltan datos requeridos');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    // Tu configuración de Shopify
    $shopifyToken = getenv('SHOPIFY_ADMIN_TOKEN');
    $apiVersion = '2024-01';
    
    logMessage('🔑 Token configurado (primeros 10 chars)', substr($shopifyToken, 0, 10) . '...');
    
    // Obtener tags actuales del cliente
    $url = "https://{$shop}/admin/api/{$apiVersion}/customers/{$customerId}.json";
    logMessage('📡 URL de Shopify', $url);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: {$shopifyToken}",
        "Content-Type: application/json"
    ]);
    
    logMessage('📥 Obteniendo datos actuales del cliente...');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    logMessage('📊 HTTP Code GET', $httpCode);
    
    if ($httpCode !== 200) {
        logMessage('❌ Error al obtener cliente', $response);
        curl_close($ch);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch customer', 'details' => $response]);
        exit;
    }
    
    $customerData = json_decode($response, true);
    logMessage('✅ Datos del cliente obtenidos', $customerData);
    
    $customer = $customerData['customer'] ?? null;
    if (!$customer) {
        logMessage('❌ No se encontró el objeto customer en la respuesta');
        curl_close($ch);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Customer object not found']);
        exit;
    }
    
    $currentTags = $customer['tags'] ? explode(', ', $customer['tags']) : [];
    logMessage('🏷️ Tags actuales del cliente', $currentTags);
    
    // Agregar nuevos tags
    $allTags = array_merge($currentTags, $newTags);
    $allTags = array_unique($allTags);
    $tagsString = implode(', ', $allTags);
    
    logMessage('🔄 Tags combinados', $allTags);
    logMessage('📝 String final de tags', $tagsString);
    
    // Actualizar cliente
    $updateData = [
        'customer' => [
            'id' => $customerId,
            'tags' => $tagsString
        ]
    ];
    
    $updateJson = json_encode($updateData);
    logMessage('📤 Datos de actualización', $updateJson);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateJson);
    
    logMessage('🔄 Actualizando cliente en Shopify...');
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    logMessage('📊 HTTP Code PUT', $httpCode);
    logMessage('📥 Respuesta de Shopify', $result);
    
    if ($httpCode === 200) {
        logMessage('✅ Cliente actualizado exitosamente');
        echo json_encode(['success' => true, 'tags' => $allTags]);
    } else {
        logMessage('❌ Error al actualizar cliente');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update customer', 'details' => $result, 'http_code' => $httpCode]);
    }
} else {
    logMessage('❌ Método no permitido', $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>