<?php
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$appRoot = realpath(__DIR__ . '/..');
$cookiePath = '/';
if ($documentRoot !== false && $appRoot !== false) {
    $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
    $appRoot = str_replace('\\', '/', $appRoot);
    if (stripos($appRoot, $documentRoot . '/') === 0) {
        $cookiePath = '/' . trim(substr($appRoot, strlen($documentRoot)), '/') . '/';
    }
}

$secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookiePath,
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
