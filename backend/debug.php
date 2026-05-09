<?php
declare(strict_types=1);

require __DIR__ . '/http.php';

set_cors();

$info = [
    'php_version' => phpversion(),
    'server_time' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
    'raw_input' => file_get_contents('php://input'),
    'parsed_json' => read_json_body(),
];

json_response(200, ['success' => true, 'debug' => $info]);
?>
