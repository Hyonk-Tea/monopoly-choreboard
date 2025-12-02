<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');

$pointsDir = __DIR__ . '/../data/points';
$metaFile  = __DIR__ . '/../data/points_meta.json';

if (!is_dir($pointsDir)) {
    mkdir($pointsDir, 0775, true);
}

$files = glob($pointsDir . '/*.json');
if ($files !== false) {
    foreach ($files as $file) {
        $data = [
            'points' => 0,
            'weekStart' => ""  // deprecated technically but fuck you
        ];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

// Update lastReset meta
$meta = [
    'lastReset' => (new DateTime())->format("Y-m-d")
];

file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(['status' => 'ok']);
