<?php

// Just store the data for now.

$body = file_get_contents('php://input');
$bodyJson = json_decode($body);
if (json_last_error() !== JSON_ERROR_NONE) {
    $bodyJson = null;
}

$saved = [
    'headers' => [],
    'bodyJson' => $bodyJson,
    'bodyRaw' => $body,
];

foreach ($_SERVER as $k => $v) {
    if (strtoupper(substr($k, 0, 5)) === 'HTTP_') {
        $saved['headers'][$k] = $v;
    }
}

$path = __DIR__ . '/../../logs/patreon/' . microtime(true) . '.json';
file_put_contents($path, json_encode($saved, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
