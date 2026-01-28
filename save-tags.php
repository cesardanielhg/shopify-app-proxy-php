<?php
// save-tags.php
// Subir a Railway

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $customerId = $data['customer_id'];
    $newTags = $data['tags'];
    $shop = $data['shop'];
    
    // Tu configuración de Shopify
    $shopifyToken = getenv('SHOPIFY_ADMIN_TOKEN');
    $apiVersion = '2024-01';
    
    // Obtener tags actuales del cliente
    $url = "https://{$shop}/admin/api/{$apiVersion}/customers/{$customerId}.json";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: {$shopifyToken}",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $customer = json_decode($response, true)['customer'];
    $currentTags = $customer['tags'] ? explode(', ', $customer['tags']) : [];
    
    // Agregar nuevos tags
    $allTags = array_merge($currentTags, $newTags);
    $tagsString = implode(', ', array_unique($allTags));
    
    // Actualizar cliente
    $updateData = json_encode([
        'customer' => [
            'id' => $customerId,
            'tags' => $tagsString
        ]
    ]);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>