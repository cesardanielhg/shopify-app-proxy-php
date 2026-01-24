<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {

  case '/oauth/callback':
    require __DIR__ . '/oauth/callback.php';
    exit;

  case '/apps/customer-tags':
    require __DIR__ . '/customer.php';
    exit;

  default:
    echo 'PHP backend running';
    exit;
}
