<?php
error_log('--- APP PROXY REQUEST ---');
error_log(print_r($_GET, true));



header('Content-Type: application/json');

// App Proxy = GET
$data = $_GET;

$shop = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? null;
$customerId = $data['customer_id'] ?? null;
$tags = $data['tags'] ?? [];


error_log('--- START SAVE TAGS ---');
error_log('Customer ID: ' . $customerId);
error_log('Tags received: ' . print_r($tags, true));


if (!$shop || !$customerId || empty($tags) || !is_array($tags)) {
  echo json_encode(['success' => false, 'error' => 'Invalid request']);
  exit;
}

$ACCESS_TOKEN = getenv('SHOPIFY_ADMIN_TOKEN');
$API_VERSION = '2024-10';

/**
 * Obtener cliente
 */
$getUrl = "https://$shop/admin/api/$API_VERSION/customers/$customerId.json";

$ch = curl_init($getUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "X-Shopify-Access-Token: $ACCESS_TOKEN"
  ]
]);

error_log('GET CUSTOMER RESPONSE: ' . $response);


$response = curl_exec($ch);
curl_close($ch);

$customer = json_decode($response, true)['customer'] ?? null;
if (!$customer) {
  echo json_encode(['success' => false]);
  exit;
}

$currentTags = array_filter(array_map('trim', explode(',', $customer['tags'])));
$finalTags = array_unique(array_merge($currentTags, $tags));

/**
 * Guardar
 */
$putUrl = "https://$shop/admin/api/$API_VERSION/customers/$customerId.json";



$payload = json_encode([
  'customer' => [
    'id' => $customerId,
    'tags' => implode(', ', $finalTags)
  ]
]);

error_log('PUT PAYLOAD: ' . $payload);


$ch = curl_init($putUrl);
curl_setopt_array($ch, [
  CURLOPT_CUSTOMREQUEST => 'PUT',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POSTFIELDS => $payload,
  CURLOPT_HTTPHEADER => [
    "Content-Type: application/json",
    "X-Shopify-Access-Token: $ACCESS_TOKEN"
  ]
]);

curl_exec($ch);
curl_close($ch);

echo json_encode(['success' => true]);
