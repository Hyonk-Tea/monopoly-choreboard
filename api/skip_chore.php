<?php
header('Content-Type: application/json');

error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');


$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input["choreId"])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "error" => "Missing choreId"]);
    exit;
}

$choreId = $input["choreId"];

$choreFile = __DIR__ . '/../data/chores.json';
if (!file_exists($choreFile)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "error" => "chores.json not found"]);
    exit;
}

$chores = json_decode(file_get_contents($choreFile), true);
if (!is_array($chores)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "error" => "Invalid JSON in chores.json"]);
    exit;
}

$today = (new DateTime())->format("Y-m-d");

$found = false;
foreach ($chores as &$chore) {
    if (isset($chore["id"]) && $chore["id"] === $choreId) {
        $chore["lastMarkedDate"] = $today;
        $chore["lastMarkedBy"] = "skipped";

        // DO NOT increment timesMarkedOff
        // DO NOT add user stats
        // DO NOT add points

        $found = true;
        break;
    }
}
unset($chore);

if (!$found) {
    http_response_code(404);
    echo json_encode(["status" => "error", "error" => "Chore not found"]);
    exit;
}

// Save file
$fp = fopen($choreFile, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode(["status" => "error", "error" => "Failed to write chores.json"]);
    exit;
}

flock($fp, LOCK_EX);
ftruncate($fp, 0);
fwrite($fp, json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(["status" => "ok"]);
