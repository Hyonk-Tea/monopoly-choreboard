<?php
header('Content-Type: application/json');
error_reporting(E_ERROR);
date_default_timezone_set('America/Chicago');

$pointsDir = __DIR__ . '/../data/points';
$metaFile  = __DIR__ . '/../data/points_meta.json';

$users = ['ash','vast','sephy','hope','cylis','phil','selina'];

if (!is_dir($pointsDir)) {
    mkdir($pointsDir, 0775, true);
}

$result = [];

// load point files
foreach ($users as $u) {
    $file = "$pointsDir/$u.json";
    $default = ['points' => 0];

    if (file_exists($file)) {
        $json = json_decode(file_get_contents($file), true);
        if (is_array($json) && isset($json['points'])) {
            $default['points'] = (float) $json['points'];
        }
    } else {
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT));
    }

    $result[$u] = $default;
}

// meta
$lastReset = null;
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
    if (isset($meta['lastReset'])) $lastReset = $meta['lastReset'];
}

$result['_meta'] = ['lastReset' => $lastReset];

echo json_encode($result);
