<?php
header('Content-Type: application/json; charset=utf-8');

$allowed_ip = '192.168.65.1';
//$allowed_ip = '49.13.3.52';

$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ??
    $_SERVER['HTTP_X_FORWARDED_FOR'] ??
    $_SERVER['REMOTE_ADDR'];

if ($client_ip !== $allowed_ip)
{
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Error: Access denied'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

//
$server_ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
$web_server = $_SERVER['SERVER_SOFTWARE'] ?? 'Неизвестно';
$php_version = PHP_VERSION;

// Проверка TC WAF
$waf_enabled = false;
$waf_params = [];
$waf_config_path = __DIR__ . '/wp-content/tc-waf-static/config.php';

if (file_exists($waf_config_path))
{
    $waf_enabled = true;
    $waf_params = include $waf_config_path;
}

echo json_encode([
    'success' => true,
    'data' => [
        'server_ip'   => $server_ip,
        'server_info'  => $web_server,
        'server_php' => $php_version,
        'is_hb_waf_plugin_active' => [
            'status' => $waf_enabled,
            'options'  => [
                'general' => $waf_params,
                'other' => []
            ]
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
