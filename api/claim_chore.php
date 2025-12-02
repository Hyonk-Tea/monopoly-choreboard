<?php
header("Content-Type: application/json");
error_reporting(E_ERROR | E_PARSE);
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
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

if (!$input) {
    log_event("[claim_chore.php] ERROR: Invalid JSON input: " . $raw);
    echo json_encode(["status" => "error", "error" => "Invalid JSON input", "raw" => $raw]);
    exit;
}

if (!isset($input["choreId"], $input["userName"])) {
    log_event("[claim_chore.php] ERROR: Missing choreId or userName");
    echo json_encode(["status" => "error", "error" => "Missing choreId or userName"]);
    exit;
}

$choreId  = $input["choreId"];
$userName = strtolower(trim($input["userName"]));
$todayStr = date("Y-m-d");

log_event("[claim_chore.php] Claim request: choreId={$choreId}, user={$userName}");

// --------------------------------------------------
// LOAD CHORE FILE
// --------------------------------------------------
$choreFile = __DIR__ . "/../data/chores.json";

if (!file_exists($choreFile)) {
    log_event("[claim_chore.php] ERROR: chores.json not found");
    echo json_encode(["status" => "error", "error" => "Chores file not found"]);
    exit;
}

$chores = json_decode(file_get_contents($choreFile), true);
if (!is_array($chores)) {
    log_event("[claim_chore.php] ERROR: chores.json corrupted");
    echo json_encode(["status" => "error", "error" => "Invalid chores.json"]);
    exit;
}

// --------------------------------------------------
// APPLY CLAIM
// --------------------------------------------------
$found = false;
foreach ($chores as &$c) {
    if (($c["id"] ?? null) === $choreId) {

        $beforeAssigned = $c["assignedTo"] ?? "";
        $beforePool     = $c["inPool"] ?? "unset";

        // Assign for today
        $c["assignedTo"]  = $userName;
        $c["inPool"]      = false;

        // Track claim metadata so it can reset automatically
        $c["claimed"]     = true;
        $c["claimedDate"] = $todayStr;

        $found = true;

        log_event("[claim_chore.php] Chore {$choreId} claimed by {$userName}. "
                . "Prev assignedTo={$beforeAssigned}, Prev inPool={$beforePool}");
        break;
    }
}
unset($c);

if (!$found) {
    log_event("[claim_chore.php] ERROR: Chore not found: {$choreId}");
    echo json_encode(["status" => "error", "error" => "Chore not found"]);
    exit;
}

// --------------------------------------------------
// SAVE UPDATED CHORES
// --------------------------------------------------
if ($fp = fopen($choreFile, "c+")) {
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    log_event("[claim_chore.php] SUCCESS: Saved chore claim for {$userName} on {$choreId}");
} else {
    log_event("[claim_chore.php] ERROR: Unable to write chores.json");
    echo json_encode(["status" => "error", "error" => "Unable to write chores.json"]);
    exit;
}

echo json_encode(["status" => "ok"]);
