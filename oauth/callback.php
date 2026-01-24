<?php

$code = $_GET['code'] ?? null;

if (!$code) {
  exit('❌ No authorization code received');
}

$response = file_get_contents(
  "https://" . getenv('SHOP') . "/admin/oauth/access_token",
  false,
  stream_context_create([
    "http" => [
      "method" => "POST",
      "header" => "Content-Type: application/json",
      "content" => json_encode([
        "client_id"     => getenv('SHOPIFY_API_KEY'),
        "client_secret" => getenv('SHOPIFY_API_SECRET'),
        "code"          => $code
      ])
    ]
  ])
);

$data = json_decode($response, true);

file_put_contents(__DIR__ . '/../token.txt', $data['access_token']);

echo "✅ App instalada correctamente. Token guardado.".$data['access_token'];
