<?php


$SHOPIFY_SECRET = getenv('SHOPIFY_API_SECRET'); 
$SHOP = getenv('SHOP'); 
$TOKEN = getenv('SHOPIFY_ADMIN_TOKEN');

header('Content-Type: application/json');

/* -------------------------------------------------
   1. Validar método
--------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Invalid request']);
  exit;
}

/* -------------------------------------------------
   2. Validar HMAC (App Proxy)
--------------------------------------------------*/
$params = $_POST;
$hmac = $_GET['hmac'] ?? '';

unset($params['hmac']);
ksort($params);

$query = urldecode(http_build_query($params));
$calculated = hash_hmac('sha256', $query, $SHOPIFY_SECRET);

if (!hash_equals($hmac, $calculated)) {
  echo json_encode(['success' => false, 'error' => 'HMAC validation failed']);
  exit;
}

/* -------------------------------------------------
   3. Validar customer
--------------------------------------------------*/
$customerId = $_POST['customer_id'] ?? null;
if (!$customerId) {
  echo json_encode(['success' => false, 'error' => 'Missing customer ID']);
  exit;
}

/* -------------------------------------------------
   4. Construir tags nuevos
--------------------------------------------------*/
$tags = [];

foreach ($_POST as $key => $value) {
  if (in_array($key, ['gender_','dob_', 'nationality_', 'phone_', 'profile_completed']) && !empty($value)) {
    $tags[] = $key;
  }
}

if (empty($tags)) {
  echo json_encode(['success' => false, 'error' => 'No tags to save']);
  exit;
}

/* -------------------------------------------------
   5. Shopify Admin API
--------------------------------------------------*/
$url = "https://$SHOP/admin/api/2025-01/customers/$customerId.json";

$payload = json_encode([
  'customer' => [
    'id' => $customerId,
    'tags' => implode(',', $tags)
  ]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'PUT',
  CURLOPT_HTTPHEADER => [
    "X-Shopify-Access-Token: $TOKEN",
    "Content-Type: application/json"
  ],
  CURLOPT_POSTFIELDS => $payload
]);

$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/* -------------------------------------------------
   6. Respuesta final
--------------------------------------------------*/
if ($http >= 200 && $http < 300) {
  echo json_encode(['success' => true]);
} else {
  echo json_encode([
    'success' => false,
    'error' => 'Shopify API error',
    'response' => $response
  ]);
}
