<?php

function verifyShopifyProxy($query) {
  if (!isset($query['signature'])) return false;

  $signature = $query['signature'];
  unset($query['signature']);
  ksort($query);

  $computed = hash_hmac(
    'sha256',
    http_build_query($query),
    getenv('SHOPIFY_API_SECRET')
  );

  return hash_equals($signature, $computed);
}

parse_str($_SERVER['QUERY_STRING'], $query);

if (!verifyShopifyProxy($query)) {
  http_response_code(401);
  exit('Invalid Shopify signature');
}

$customerId = $_POST['customer_id'] ?? null;
if (!$customerId) {
  http_response_code(400);
  exit('Missing customer_id');
}

$newTags = [];

if (!empty($_POST['gender'])) {
  $newTags[] = 'gender_' . strtolower($_POST['gender']);
}
if (!empty($_POST['dob'])) {
  $newTags[] = 'DOB_' . $_POST['dob'];
}
if (!empty($_POST['nationality'])) {
  $newTags[] = 'nationality_' . strtolower(str_replace(' ', '_', $_POST['nationality']));
}
if (!empty($_POST['phone'])) {
  $newTags[] = 'phone_' . preg_replace('/\D+/', '', $_POST['phone']);
}

$shop  = getenv('SHOP');
$token = trim(file_get_contents(__DIR__ . '/../token.txt'));
$api   = "https://$shop/admin/api/2026-01/customers/$customerId.json";

/* Obtener tags actuales */
$ch = curl_init($api);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "X-Shopify-Access-Token: $token"
  ]
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$existing = array_map('trim', explode(',', $data['customer']['tags'] ?? ''));

/* Unir */
$finalTags = array_unique(array_merge($existing, $newTags));

/* Guardar */
$payload = [
  'customer' => [
    'id'   => $customerId,
    'tags' => implode(', ', $finalTags)
  ]
];

$ch = curl_init($api);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'PUT',
  CURLOPT_POSTFIELDS => json_encode($payload),
  CURLOPT_HTTPHEADER => [
    "X-Shopify-Access-Token: $token",
    "Content-Type: application/json"
  ]
]);
curl_exec($ch);
curl_close($ch);

header('Location: /account');
exit;
