<?php
header("Content-Type: application/json");

date_default_timezone_set('America/Chicago');

// --------------------------------------------------
// LOGGING
// --------------------------------------------------
function log_event($msg) {
    $logDir = __DIR__ . '/../data/logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $file = $logDir . '/' . date('Y-m-d') . '.txt';
    $timestamp = date('Y-m-d H:i:s');

    file_put_contents($file, "[$timestamp] $msg\n", FILE_APPEND);
}

// --------------------------------------------------
// INPUT
// --------------------------------------------------
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    log_event("[add_points.php] ERROR: Invalid JSON input: " . file_get_contents("php://input"));
    echo json_encode(["status" => "error", "error" => "Invalid JSON"]);
    exit;
}

$user = $input["user"] ?? null;
$value = floatval($input["value"] ?? 0);

if (!$user || $value <= 0) {
    log_event("[add_points.php] ERROR: Invalid parameters. user={$user}, value={$value}");
    echo json_encode(["status" => "error", "error" => "Invalid parameters"]);
    exit;
}

// --------------------------------------------------
// FILE PATHS
// --------------------------------------------------

// Path: data/points/{user}.json
$pointsFile = __DIR__ . "/../data/points/" . $user . ".json";

// Ensure directory exists
$dir = dirname($pointsFile);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// --------------------------------------------------
// LOAD OR INIT USER POINTS
// --------------------------------------------------
if (!file_exists($pointsFile)) {
    $data = ["points" => 0];
} else {
    $raw = file_get_contents($pointsFile);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = ["points" => 0];
}

// --------------------------------------------------
// UPDATE
// --------------------------------------------------
$before = $data["points"];
$data["points"] += $value;
$after = $data["points"];

// Save
file_put_contents($pointsFile, json_encode($data, JSON_PRETTY_PRINT));

// --------------------------------------------------
// LOG SUCCESS
// --------------------------------------------------
log_event("[add_points.php] Added {$value} points to {$user}. Before={$before}, After={$after}");

// --------------------------------------------------
// RESPONSE
// --------------------------------------------------
echo json_encode([
    "status" => "ok",
    "user" => $user,
    "added" => $value,
    "newTotal" => $data["points"]
]);
