<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// App Proxy (NO comparación exacta)
if (strpos($path, '/apps/customer-tags') === 0) {
  require __DIR__ . '/customer.php';
  exit;
}

// OAuth
if (strpos($path, '/oauth/callback') === 0) {
  require __DIR__ . '/oauth/callback.php';
  exit;
}

// Default
echo 'PHP backend running';
