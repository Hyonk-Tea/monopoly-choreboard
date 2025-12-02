<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');


$choreFile = __DIR__ . '/../data/chores.json';

if (!file_exists($choreFile)) {
    echo json_encode([]);
    exit;
}

$json = file_get_contents($choreFile);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to read chores.json']);
    exit;
}

echo $json;
