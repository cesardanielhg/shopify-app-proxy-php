<?php
// save-tags.php
// Subir a Railway

error_reporting(E_ALL);
ini_set('display_errors', 0); // Desactivar para no contaminar JSON

// Solo enviar JSON limpio
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Función de log que guarda en archivo
function logToFile($message, $data = null) {
    $log = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $log .= ' | Data: ' . json_encode($data, JSON_PRETTY_PRINT);
    }
    $log .= "\n";
    file_put_contents('debug.log', $log, FILE_APPEND);
}

logToFile('🚀 Script iniciado');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logToFile('✅ Método POST detectado');
    
    $rawInput = file_get_contents('php://input');
    logToFile('📥 Raw input recibido', $rawInput);
    
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        logToFile('❌ Error al decodificar JSON');
        echo json_encode(['success' => false, 'error' => 'Invalid JSON', 'raw' => $rawInput]);
        exit;
    }
    
    logToFile('✅ JSON decodificado correctamente', $data);
    
    $customerId = $data['customer_id'] ?? null;
    $newTags = $data['tags'] ?? [];
    $shop = $data['shop'] ?? null;
    
    logToFile('👤 Customer ID', $customerId);
    logToFile('🏷️ Nuevos tags', $newTags);
    logToFile('🏪 Shop', $shop);
    
    if (!$customerId || !$shop || empty($newTags)) {
        logToFile('❌ Faltan datos requeridos');
        echo json_encode(['success' => false, 'error' => 'Missing required fields: customer_id, shop, or tags']);
        exit;
    }
    
    // Tu configuración de Shopify
    $shopifyToken = getenv('SHOPIFY_ADMIN_TOKEN');
    $apiVersion = '2024-01';
    
    logToFile('🔑 Token configurado');
    
    // Obtener tags actuales del cliente
    $url = "https://{$shop}/admin/api/{$apiVersion}/customers/{$customerId}.json";
    logToFile('📡 URL de Shopify', $url);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: {$shopifyToken}",
        "Content-Type: application/json"
    ]);
    
    logToFile('📥 Obteniendo datos actuales del cliente...');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    logToFile('📊 HTTP Code GET', $httpCode);
    logToFile('📄 Response GET', $response);
    
    if ($httpCode !== 200) {
        logToFile('❌ Error al obtener cliente');
        curl_close($ch);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch customer from Shopify', 'http_code' => $httpCode, 'response' => $response]);
        exit;
    }
    
    $customerData = json_decode($response, true);
    logToFile('✅ Datos del cliente obtenidos', $customerData);
    
    $customer = $customerData['customer'] ?? null;
    if (!$customer) {
        logToFile('❌ No se encontró el objeto customer');
        curl_close($ch);
        echo json_encode(['success' => false, 'error' => 'Customer object not found in Shopify response']);
        exit;
    }
    
    $currentTags = $customer['tags'] ? explode(', ', $customer['tags']) : [];
    logToFile('🏷️ Tags actuales del cliente', $currentTags);
    
    // Agregar nuevos tags
    $allTags = array_merge($currentTags, $newTags);
    $allTags = array_unique($allTags);
    $tagsString = implode(', ', $allTags);
    
    logToFile('🔄 Tags combinados', $allTags);
    logToFile('📝 String final de tags', $tagsString);
    
    // Actualizar cliente
    $updateData = [
        'customer' => [
            'id' => $customerId,
            'tags' => $tagsString
        ]
    ];
    
    $updateJson = json_encode($updateData);
    logToFile('📤 Datos de actualización', $updateData);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: {$shopifyToken}",
        "Content-Type: application/json"
    ]);
    
    logToFile('🔄 Actualizando cliente en Shopify...');
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    logToFile('📊 HTTP Code PUT', $httpCode);
    logToFile('📥 Respuesta PUT', $result);
    
    if ($httpCode === 200) {
        logToFile('✅ Cliente actualizado exitosamente');
        echo json_encode(['success' => true, 'tags' => $allTags, 'message' => 'Customer updated successfully']);
    } else {
        logToFile('❌ Error al actualizar cliente');
        echo json_encode(['success' => false, 'error' => 'Failed to update customer in Shopify', 'http_code' => $httpCode, 'response' => $result]);
    }
} else {
    logToFile('❌ Método no permitido', $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
}
?>