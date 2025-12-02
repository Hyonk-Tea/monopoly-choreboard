<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('America/Chicago');

/**
 * Simple daily logger -> data/logs/YYYY-MM-DD.txt
 */
if (!function_exists('log_event')) {
    function log_event($msg) {
        $logDir = __DIR__ . '/../data/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $file = $logDir . '/' . date('Y-m-d') . '.txt';
        $timestamp = date('Y-m-d H:i:s');

        @file_put_contents($file, "[$timestamp] $msg\n", FILE_APPEND);
    }
}

$choreFile = __DIR__ . '/../data/chores.json';

if (!file_exists($choreFile)) {
    log_event("[get_chores.php] chores.json missing → returning empty array.");
    echo json_encode([]);
    exit;
}

$raw = file_get_contents($choreFile);
$chores = json_decode($raw, true);

if (!is_array($chores)) {
    log_event("[get_chores.php] chores.json invalid JSON. Raw length=" . strlen($raw));
    echo json_encode([]);
    exit;
}

$todayStr = date('Y-m-d');
$changed  = false;

foreach ($chores as &$c) {
    // Only touch chores that were explicitly claimed
    if (!empty($c['claimed']) && !empty($c['claimedDate'])) {

        if ($c['claimedDate'] !== $todayStr) {
            // Claim is stale → return chore to public pool
            log_event("[get_chores.php] Auto-reset claim on chore '{$c['id']}' (claimedDate={$c['claimedDate']} != today={$todayStr})");

            $c['claimed']     = false;
            $c['claimedDate'] = '';

            $c['assignedTo']  = '';
            $c['inPool']      = true;

            $changed = true;
        }
    }
}
unset($c);

if ($changed) {
    file_put_contents(
        $choreFile,
        json_encode($chores, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    log_event("[get_chores.php] Saved updated chores.json with auto-reset claims.");
}

// Always return the current list
log_event("[get_chores.php] Returned chores list (" . count($chores) . " chores).");
echo json_encode($chores);
