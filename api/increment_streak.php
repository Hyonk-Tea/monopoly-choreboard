<?php
header("Content-Type: application/json");

$streakFile = __DIR__ . '/../data/streak.json';

$streak = ["weeks" => 0];

if (file_exists($streakFile)) {
    $raw = file_get_contents($streakFile);
    $data = json_decode($raw, true);
    if (is_array($data) && isset($data["weeks"])) {
        $streak = $data;
    }
}

$streak["weeks"]++;

file_put_contents($streakFile, json_encode($streak, JSON_PRETTY_PRINT));

echo json_encode(["status" => "ok", "weeks" => $streak["weeks"]]);
