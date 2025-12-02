<?php
header("Content-Type: application/json");

$streakFile = __DIR__ . '/../data/streak.json';

if (!file_exists($streakFile)) {
    echo json_encode(["weeks" => 0]);
    exit;
}

$raw = file_get_contents($streakFile);
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data["weeks"])) {
    echo json_encode(["weeks" => 0]);
    exit;
}

echo json_encode($data);
