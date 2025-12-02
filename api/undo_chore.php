<?php
header("Content-Type: application/json");
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input["choreId"])) {
    echo json_encode(["status" => "error", "error" => "Missing choreId"]);
    exit;
}

$choreId = $input["choreId"];

$choreFile   = __DIR__ . '/../data/chores.json';
$historyDir  = __DIR__ . '/../data/history';
$pointsDir   = __DIR__ . '/../data/points';

// Ensure directories exist
if (!is_dir($historyDir)) mkdir($historyDir, 0775, true);
if (!is_dir($pointsDir)) mkdir($pointsDir, 0775, true);

$historyFile = $historyDir . "/undo_" . $choreId . ".json";

if (!file_exists($historyFile)) {
    echo json_encode([
        "status" => "error",
        "error" => "No undo history for this chore"
    ]);
    exit;
}

// Load undo snapshot
$prev = json_decode(file_get_contents($historyFile), true);
if (!$prev) {
    echo json_encode([
        "status" => "error",
        "error" => "Undo data corrupt"
    ]);
    exit;
}

// Load chores
$chores = json_decode(file_get_contents($choreFile), true);

$found = false;
$choreValue = 0;
$lastUsers = [];

foreach ($chores as &$chore) {
    if ($chore["id"] === $choreId) {
        // Store chore value for points rollback
        $choreValue = floatval($chore["value"] ?? 0);

        // Determine who originally got the points
        if (!empty($chore["lastMarkedBy"])) {
            $lastUsers = array_map('trim', explode(",", strtolower($chore["lastMarkedBy"])));
        }

        // Restore stats
        $chore["lastMarkedDate"] = $prev["lastMarkedDate"];
        $chore["lastMarkedBy"] = $prev["lastMarkedBy"];
        $chore["timesMarkedOff"] = max(0, intval($prev["timesMarkedOff"]));

        $found = true;
        break;
    }
}
unset($chore);

if (!$found) {
    echo json_encode(["status" => "error", "error" => "Chore not found"]);
    exit;
}

// ----------------------------------------------
// ROLLBACK POINTS
// ----------------------------------------------
if (!empty($lastUsers) && $choreValue > 0) {
    $pointsEach = $choreValue / count($lastUsers);

    foreach ($lastUsers as $u) {
        if (!$u) continue;

        $file = "$pointsDir/$u.json";
        $info = file_exists($file)
            ? json_decode(file_get_contents($file), true)
            : ["points" => 0];

        if (!is_array($info)) $info = ["points" => 0];

        // Subtract safely (no negatives)
        $info["points"] = max(0, ($info["points"] ?? 0) - $pointsEach);

        file_put_contents($file, json_encode($info, JSON_PRETTY_PRINT));
    }
}

// ----------------------------------------------
// Save updated chores
// ----------------------------------------------
file_put_contents($choreFile, json_encode($chores, JSON_PRETTY_PRINT));

// Delete undo file (single undo)
unlink($historyFile);

echo json_encode(["status" => "ok"]);
